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
        $band = Band::where('code', strtoupper($request->code))->firstOrFail();

        if (!$this->checkPin($request->pin, $band->access_pin)) {
            return back()->withErrors(['pin' => 'Incorrect PIN.']);
        }

        // A code and a PIN travel by word of mouth: read aloud at rehearsal,
        // forwarded, written on a music stand. Whoever ends up holding them
        // gets to read the band's repertoire and nothing else.
        //
        // Writing belongs to admins — people the band named one by one — not
        // to whoever knows a four-digit number.
        $request->session()->put('band_id', $band->id);
        $request->session()->put('access_level', 'member');
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    private function checkPin(string $input, string $stored): bool
    {
        if ($input === $stored) return true;

        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')) {
            return Hash::check($input, $stored);
        }

        return false;
    }
}
