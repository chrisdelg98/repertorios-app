<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BandAware;
use App\Models\Band;
use App\Models\BandVisit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    use BandAware;

    public function index(): Response
    {
        $this->requireCreator();

        $bandId  = $this->bandId();
        $band    = Band::findOrFail($bandId);
        $members = User::where('band_id', $bandId)
            ->orderBy('created_at')
            ->get(['id', 'name', 'email', 'role', 'created_at'])
            ->map(fn ($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'role'       => $u->role,
                'is_creator' => $u->id === (int) $band->creator_id,
                'is_you'     => $u->id === Auth::id(),
            ]);

        $visits = BandVisit::where('band_id', $bandId);

        return Inertia::render('Settings/Members', [
            'members'      => $members,
            'visit_stats'  => [
                'total'        => (clone $visits)->count(),
                'last_30_days' => (clone $visits)->where('last_seen', '>=', now()->subDays(30))->count(),
            ],
        ]);
    }

    public function promote(User $user): RedirectResponse
    {
        $this->requireCreator();
        abort_if($user->band_id !== $this->bandId(), 403);
        abort_if($user->role === 'admin', 422);

        $user->update(['role' => 'admin']);

        return back()->with('success', true);
    }

    public function demote(User $user): RedirectResponse
    {
        $this->requireCreator();
        abort_if($user->band_id !== $this->bandId(), 403);

        $band = Band::findOrFail($this->bandId());
        abort_if($band->creator_id === $user->id, 422, 'Cannot demote the band creator.');
        abort_if($user->role !== 'admin', 422);

        $user->update(['role' => 'member']);

        return back()->with('success', true);
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->requireCreator();
        abort_if($user->id === Auth::id(), 403);
        abort_if($user->band_id !== $this->bandId(), 403);

        $user->delete();

        return back()->with('success', true);
    }

    public function resetVisitors(): RedirectResponse
    {
        $this->requireCreator();

        BandVisit::where('band_id', $this->bandId())->delete();

        return back()->with('success', true);
    }
}
