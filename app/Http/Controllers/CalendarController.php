<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BandAware;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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
            ->get(['id', 'kind', 'series_id', 'date', 'time', 'end_time', 'type', 'color', 'notes'])
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
                'series_id' => $entry->series_id,
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

        $repeat = $request->validate([
            'repeat_weekly' => ['nullable', 'boolean'],
            'repeat_until'  => ['nullable', 'required_if:repeat_weekly,true', 'date', 'after:date'],
        ]);

        $dates = $this->occurrenceDates(
            $data['date'],
            ($repeat['repeat_weekly'] ?? false) ? ($repeat['repeat_until'] ?? null) : null
        );

        // One row per date. Generating them now rather than deriving them on
        // read is what lets a single Friday be cancelled or annotated later.
        $seriesId = count($dates) > 1 ? (string) Str::uuid() : null;

        foreach ($dates as $date) {
            Service::create([
                ...$data,
                'band_id'   => $this->bandId(),
                'date'      => $date,
                'series_id' => $seriesId,
            ]);
        }

        return back()->with('success', true);
    }

    /**
     * Removes the rest of a repeating run, from this occurrence onward.
     *
     * Past ones are left alone: they are a record of what the band did, and
     * anyone assigned to them would find their history rewritten.
     */
    public function destroySeries(Service $entry): RedirectResponse
    {
        $this->requireWrite();
        $this->authorizeEntry($entry);

        abort_unless($entry->isRecurring(), 404);

        Service::where('band_id', $this->bandId())
            ->where('series_id', $entry->series_id)
            ->where('date', '>=', $entry->date->toDateString())
            ->delete();

        return back()->with('success', true);
    }

    /**
     * The dates a new entry covers: just the one, or every seventh day through
     * the closing date.
     *
     * Capped because the form asks for a date and someone will eventually type
     * a year that is not the one they meant.
     */
    private function occurrenceDates(string $start, ?string $until): array
    {
        $first = Carbon::parse($start)->startOfDay();

        if (!$until) {
            return [$first->toDateString()];
        }

        $last = Carbon::parse($until)->startOfDay();
        $dates = [];

        for ($date = $first->copy(); $date->lte($last) && count($dates) < 104; $date->addWeek()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
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
