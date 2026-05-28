<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BandAware;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    use BandAware;

    public function index(): Response
    {
        $this->requireAdmin();

        $members = User::where('band_id', $this->bandId())
            ->orderBy('created_at')
            ->get(['id', 'name', 'email', 'created_at']);

        return Inertia::render('Settings/Members', [
            'members' => $members,
        ]);
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->requireAdmin();

        abort_if($user->id === Auth::id(), 403);
        abort_if($user->band_id !== $this->bandId(), 403);

        $user->delete();

        return back()->with('success', true);
    }
}
