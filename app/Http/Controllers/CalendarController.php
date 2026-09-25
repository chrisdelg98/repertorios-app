<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BandAware;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The band's month at a glance: services, rehearsals, meetings and whatever
 * else they schedule.
 *
 * Services are created and edited under Services, where they have a setlist
 * and a team. This controller writes only the calendar-only kinds, which is
 * why store() and update() refuse a 'service' — otherwise there would be two
 * doors into the same thing, each knowing half the rules.
 */
class CalendarController extends Controller
{
    use BandAware;

    public function index(Request $request): Response
    {
        $month = $this->monthFrom($request->query('month'));

        // A month view needs the days either side of it: the grid starts on a
        // Sunday and ends on a Saturday, and those spill into the neighbours.
        $from = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $to   = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $entries = Service::where('band_id', $this->bandId())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->orderBy('time')
            ->withCount('serviceSongs')
            ->get(['id', 'kind', 'date', 'time', 'end_time', 'type', 'color', 'notes'])
            ->map(fn (Service $entry) => [
                'id'       => $entry->id,
                'kind'     => $entry->kind,
                'date'     => $entry->date->toDateString(),
                'time'     => $entry->time ? substr($entry->time, 0, 5) : null,
                'end_time' => $entry->end_time ? substr($entry->end_time, 0, 5) : null,
                'name'     => $entry->type,
                'color'    => $entry->color,
                'notes'    => $entry->notes,
                'songs'    => $entry->service_songs_count,
            ]);

        return Inertia::render('Calendar/Index', [
            'month'     => $month->format('Y-m'),
            'from'      => $from->toDateString(),
            'to'        => $to->toDateString(),
            'entries'   => $entries,
            'can_write' => $this->canWrite(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireWrite();

        $data = $this->validated($request);

        Service::create([...$data, 'band_id' => $this->bandId()]);

        return back()->with('success', true);
    }

    public function update(Request $request, Service $entry): RedirectResponse
    {
        $this->requireWrite();
        $this->authorizeEntry($entry);

        $entry->update($this->validated($request));

        return back()->with('success', true);
    }

    public function destroy(Service $entry): RedirectResponse
    {
        $this->requireWrite();
        $this->authorizeEntry($entry);

        $entry->delete();

        return back()->with('success', true);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            // Services are born under Services, with their setlist and team.
            'kind'     => ['required', Rule::in(Service::CALENDAR_ONLY_KINDS)],
            'type'     => ['required', 'string', 'max:40'],
            'date'     => ['required', 'date'],
            'time'     => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:time'],
            'color'    => ['nullable', Rule::in(Service::COLORS)],
            'notes'    => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function authorizeEntry(Service $entry): void
    {
        abort_unless($entry->band_id === $this->bandId(), 403);

        // A service reached through this route would lose its setlist rules.
        abort_if($entry->isService(), 403, 'Services are edited from Services.');
    }

    private function monthFrom(?string $value): Carbon
    {
        try {
            return $value
                ? Carbon::createFromFormat('Y-m', $value)->startOfMonth()
                : Carbon::now()->startOfMonth();
        } catch (\Throwable) {
            return Carbon::now()->startOfMonth();
        }
    }
}
