<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BandAware;
use App\Models\ScheduleTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleTemplateController extends Controller
{
    use BandAware;

    public function index(): Response
    {
        $this->requireAdmin();

        $templates = ScheduleTemplate::where('band_id', $this->bandId())
            ->orderBy('sort_order')
            ->orderBy('day_of_week')
            ->orderBy('time')
            ->get();

        return Inertia::render('Settings/ScheduleTemplates', [
            'templates' => $templates,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:20'],
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'time' => ['required', 'date_format:H:i'],
        ]);

        $nextOrder = ScheduleTemplate::where('band_id', $this->bandId())->max('sort_order') ?? -1;

        ScheduleTemplate::create([
            ...$validated,
            'band_id' => $this->bandId(),
            'sort_order' => $nextOrder + 1,
        ]);

        return back()->with('success', 'Template added.');
    }

    public function update(Request $request, ScheduleTemplate $scheduleTemplate): RedirectResponse
    {
        $this->requireAdmin();
        abort_unless($scheduleTemplate->band_id === $this->bandId(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:20'],
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'time' => ['required', 'date_format:H:i'],
        ]);

        $scheduleTemplate->update($validated);

        return back()->with('success', 'Template updated.');
    }

    public function destroy(ScheduleTemplate $scheduleTemplate): RedirectResponse
    {
        $this->requireAdmin();
        abort_unless($scheduleTemplate->band_id === $this->bandId(), 403);

        $scheduleTemplate->delete();

        return back()->with('success', 'Template deleted.');
    }
}
