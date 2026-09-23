<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BandAware;
use App\Models\Band;
use App\Models\BandRoleType;
use App\Models\BandVisit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    use BandAware;

    public function index(): Response
    {
        $this->requireCreator();

        $bandId = $this->bandId();
        $band   = Band::findOrFail($bandId);

        // Instrument roles are per band, so they are read straight off the
        // pivot rather than through a relation that would ignore the scope.
        $roleIdsByUser = DB::table('user_band_roles')
            ->where('band_id', $bandId)
            ->get(['user_id', 'band_role_type_id'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('band_role_type_id')->all());

        $members = $band->members()
            ->orderBy('band_user.joined_at')
            ->orderBy('users.id')
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn ($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'role'       => $u->pivot->role,
                'is_creator' => $u->id === (int) $band->creator_id,
                'is_you'     => $u->id === Auth::id(),
                'role_ids'   => $roleIdsByUser->get($u->id, []),
            ]);

        $visits     = BandVisit::where('band_id', $bandId);
        $roleTypes  = BandRoleType::orderBy('sort_order')->orderBy('name_es')
            ->get(['id', 'name_es', 'name_en']);

        return Inertia::render('Settings/Members', [
            'members'      => $members,
            'role_types'   => $roleTypes,
            'visit_stats'  => [
                'total'        => (clone $visits)->count(),
                'last_30_days' => (clone $visits)->where('last_seen', '>=', now()->subDays(30))->count(),
            ],
        ]);
    }

    public function promote(User $user): RedirectResponse
    {
        $this->requireCreator();
        $this->requireSameBand($user);
        abort_if($user->isAdminOf($this->bandId()), 422);

        $user->bands()->updateExistingPivot($this->bandId(), ['role' => 'admin']);

        return back()->with('success', true);
    }

    public function demote(User $user): RedirectResponse
    {
        $this->requireCreator();
        $this->requireSameBand($user);

        $band = Band::findOrFail($this->bandId());
        abort_if($band->creator_id === $user->id, 422, 'Cannot demote the band creator.');
        abort_if(!$user->isAdminOf($band->id), 422);

        $user->bands()->updateExistingPivot($band->id, ['role' => 'member']);

        return back()->with('success', true);
    }

    /**
     * Remove someone from THIS band. Their account and their membership in any
     * other band stay untouched — the user row is never deleted here.
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->requireCreator();
        abort_if($user->id === Auth::id(), 403);
        $this->requireSameBand($user);

        $bandId = $this->bandId();

        DB::transaction(function () use ($user, $bandId) {
            DB::table('user_band_roles')
                ->where('band_id', $bandId)
                ->where('user_id', $user->id)
                ->delete();

            $user->bands()->detach($bandId);

            // They were looking at this band when they got removed — send them
            // to any band they still belong to, or nowhere.
            if ((int) $user->active_band_id === (int) $bandId) {
                $user->forceFill([
                    'active_band_id' => $user->bands()->value('bands.id'),
                ])->save();
            }
        });

        return back()->with('success', true);
    }

    public function resetVisitors(): RedirectResponse
    {
        $this->requireCreator();

        BandVisit::where('band_id', $this->bandId())->delete();

        return back()->with('success', true);
    }

    public function assignRoles(Request $request, User $user): RedirectResponse
    {
        // Creator OR delegated admin can assign roles.
        $this->requireAdmin();
        $this->requireSameBand($user);

        $data = $request->validate([
            'role_ids'   => ['array'],
            'role_ids.*' => ['integer', 'exists:band_role_types,id'],
        ]);

        $bandId = $this->bandId();

        $user->bandRoles($bandId)->syncWithPivotValues(
            $data['role_ids'] ?? [],
            ['band_id' => $bandId]
        );

        return back()->with('success', true);
    }
}
