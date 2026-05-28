<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BandAware;
use App\Models\Band;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
                'has_edit_pin' => (bool) $band->edit_pin,
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
            'logo' => 'required|image|max:2048',
        ]);

        $band = Band::findOrFail($this->bandId());

        if ($band->logo) {
            Storage::disk('public')->delete($band->logo);
        }

        $path = $request->file('logo')->store('bands', 'public');
        $band->update(['logo' => $path]);

        return back()->with('success', true);
    }

    public function updatePins(Request $request): RedirectResponse
    {
        $this->requireAdmin();

        $request->validate([
            'access_pin' => 'required|string|min:4|max:20',
            'edit_pin'   => 'nullable|string|min:4|max:20',
        ]);

        $band = Band::findOrFail($this->bandId());

        $data = ['access_pin' => Hash::make($request->access_pin)];

        if ($request->filled('edit_pin')) {
            $data['edit_pin'] = Hash::make($request->edit_pin);
        }

        $band->update($data);

        return back()->with('success', true);
    }
}
