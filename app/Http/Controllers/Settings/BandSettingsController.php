<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BandAware;
use App\Models\Band;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BandSettingsController extends Controller
{
    use BandAware;

    public function show(): Response
    {
        $this->requireAdmin();

        $band = Band::findOrFail($this->bandId());

        return Inertia::render('Settings/Band', [
            'band' => [
                'id'           => $band->id,
                'name'         => $band->name,
                'logo_url'     => $band->logo ? asset('storage/' . $band->logo) : null,
                'code'         => $band->code,
                'access_pin'   => $band->access_pin,
                'has_edit_pin' => (bool) $band->edit_pin,
                'invite_url'   => $band->invite_token
                    ? route('band.join', ['token' => $band->invite_token])
                    : null,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->requireAdmin();

        $band = Band::findOrFail($this->bandId());

        $data = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $band->update($data);

        return back()->with('success', true);
    }

    public function updateLogo(Request $request): RedirectResponse
    {
        $this->requireAdmin();

        $request->validate([
            'logo' => ['required', 'image', 'mimes:webp,jpg,jpeg,png,gif', 'max:10240'],
        ]);

        $band = Band::findOrFail($this->bandId());

        if ($band->logo) {
            Storage::disk('public')->delete($band->logo);
        }

        $path = $request->file('logo')->store('bands', 'public');
        $band->update(['logo' => $path]);

        return back()->with('success', true);
    }

    public function regenerateCode(): RedirectResponse
    {
        $this->requireAdmin();

        $band = Band::findOrFail($this->bandId());
        $band->update(['code' => Band::generateCode()]);

        return back()->with('success', true);
    }

    public function regeneratePin(): RedirectResponse
    {
        $this->requireAdmin();

        $band = Band::findOrFail($this->bandId());
        $band->update(['access_pin' => Band::generatePin()]);

        return back()->with('success', true);
    }

    public function regenerateToken(): RedirectResponse
    {
        $this->requireAdmin();

        $band = Band::findOrFail($this->bandId());
        $band->update(['invite_token' => Band::generateToken()]);

        return back()->with('success', true);
    }
}
