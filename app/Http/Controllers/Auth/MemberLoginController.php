<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\JoinRequest;
use App\Models\Band;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

class MemberLoginController extends Controller
{
    public function store(JoinRequest $request): RedirectResponse
    {
        $band = Band::where('code', $request->code)->firstOrFail();

        $matchesEdit = $band->edit_pin && Hash::check($request->pin, $band->edit_pin);
        $matchesAccess = Hash::check($request->pin, $band->access_pin);

        if (!$matchesAccess && !$matchesEdit) {
            return back()->withErrors(['pin' => 'Incorrect PIN.']);
        }

        $request->session()->put('band_id', $band->id);
        $request->session()->put('access_level', $matchesEdit ? 'editor' : 'member');
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
