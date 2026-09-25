<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CalendarRecurrenceTest extends TestCase
{
    use DatabaseTransactions;

    private Band $band;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->band = Band::create([
            'name'         => 'Recurring Band',
            'code'         => Band::generateCode(),
            'access_pin'   => Band::generatePin(),
            'invite_token' => Band::generateToken(),
        ]);

        $this->admin = User::create([
            'active_band_id'    => $this->band->id,
            'name'              => 'Admin',
            'email'             => uniqid() . '@example.test',
            'password'          => bcrypt('secret1234'),
            'email_verified_at' => now(),
        ]);
        $this->admin->joinBand($this->band, 'admin');
    }

    private function create(array $overrides = [])
    {
        return $this->actingAs($this->admin)->post('/calendar', [
            'kind'  => 'rehearsal',
            'type'  => 'Ensayo',
            'date'  => '2026-10-02',   // a Friday
            'time'  => '18:00',
            'end_time' => '19:00',
            ...$overrides,
        ]);
    }

    private function entries()
    {
        return Service::where('band_id', $this->band->id)->calendarOnly()->orderBy('date')->get();
    }

    public function test_without_repeating_only_one_entry_is_created(): void
    {
        $this->create()->assertRedirect();

        $entries = $this->entries();

        $this->assertCount(1, $entries);
        $this->assertNull($entries->first()->series_id, 'a single entry is not a series');
    }

    public function test_repeating_creates_one_entry_per_week_through_the_closing_date(): void
    {
        // 2 October to 30 October 2026: five Fridays.
        $this->create(['repeat_weekly' => true, 'repeat_until' => '2026-10-30'])->assertRedirect();

        $entries = $this->entries();

        $this->assertCount(5, $entries);
        $this->assertSame(
            ['2026-10-02', '2026-10-09', '2026-10-16', '2026-10-23', '2026-10-30'],
            $entries->map(fn ($e) => $e->date->toDateString())->all()
        );
    }

    public function test_every_occurrence_carries_the_same_details_and_series(): void
    {
        $this->create(['repeat_weekly' => true, 'repeat_until' => '2026-10-23'])->assertRedirect();

        $entries = $this->entries();
        $series = $entries->pluck('series_id')->unique();

        $this->assertCount(1, $series, 'all occurrences belong to one series');
        $this->assertNotNull($series->first());

        foreach ($entries as $entry) {
            $this->assertSame('Ensayo', $entry->type);
            $this->assertSame('18:00:00', substr($entry->time, 0, 8));
            $this->assertSame('19:00:00', substr($entry->end_time, 0, 8));
        }
    }

    public function test_a_closing_date_is_required_once_repeating_is_asked_for(): void
    {
        $this->create(['repeat_weekly' => true])->assertSessionHasErrors('repeat_until');
    }

    public function test_a_closing_date_before_the_start_is_refused(): void
    {
        $this->create(['repeat_weekly' => true, 'repeat_until' => '2026-09-01'])
            ->assertSessionHasErrors('repeat_until');
    }

    public function test_a_mistyped_year_cannot_fill_the_calendar(): void
    {
        // Someone will eventually type 2126 instead of 2026.
        $this->create(['repeat_weekly' => true, 'repeat_until' => '2126-10-02'])->assertRedirect();

        $this->assertCount(104, $this->entries(), 'generation stops at two years');
    }

    // ── Editing and removing ─────────────────────────────────────────────

    public function test_one_occurrence_can_be_changed_without_touching_the_rest(): void
    {
        $this->create(['repeat_weekly' => true, 'repeat_until' => '2026-10-23']);

        $second = $this->entries()[1];

        $this->actingAs($this->admin)->put("/calendar/{$second->id}", [
            'kind' => 'rehearsal',
            'type' => 'Ensayo con invitados',
            'date' => '2026-10-10',      // moved to the Saturday
            'time' => '16:00',
        ])->assertRedirect();

        $entries = $this->entries();

        $this->assertCount(4, $entries, 'nothing was added or removed');
        $this->assertSame('Ensayo con invitados', $second->fresh()->type);
        $this->assertSame('2026-10-10', $second->fresh()->date->toDateString());
        // The others are untouched.
        $this->assertSame('Ensayo', $entries->first()->type);
    }

    public function test_deleting_one_occurrence_leaves_the_series_alone(): void
    {
        $this->create(['repeat_weekly' => true, 'repeat_until' => '2026-10-23']);

        $second = $this->entries()[1];

        $this->actingAs($this->admin)->delete("/calendar/{$second->id}")->assertRedirect();

        $this->assertCount(3, $this->entries());
    }

    public function test_removing_the_series_takes_this_one_and_the_later_ones(): void
    {
        $this->create(['repeat_weekly' => true, 'repeat_until' => '2026-10-30']);

        $third = $this->entries()[2];   // 16 October

        $this->actingAs($this->admin)->delete("/calendar/{$third->id}/series")->assertRedirect();

        $remaining = $this->entries();

        // The two before it survive: they are a record of what the band did.
        $this->assertCount(2, $remaining);
        $this->assertSame(
            ['2026-10-02', '2026-10-09'],
            $remaining->map(fn ($e) => $e->date->toDateString())->all()
        );
    }

    public function test_an_entry_that_is_not_part_of_a_series_has_no_series_to_remove(): void
    {
        $this->create();

        $this->actingAs($this->admin)
            ->delete("/calendar/{$this->entries()->first()->id}/series")
            ->assertNotFound();
    }

    public function test_another_bands_series_cannot_be_removed(): void
    {
        $other = Band::create([
            'name' => 'Otra', 'code' => Band::generateCode(),
            'access_pin' => Band::generatePin(), 'invite_token' => Band::generateToken(),
        ]);

        $theirs = Service::create([
            'band_id'   => $other->id,
            'kind'      => 'rehearsal',
            'series_id' => (string) \Illuminate\Support\Str::uuid(),
            'date'      => '2026-10-02',
            'type'      => 'Suyo',
        ]);

        $this->actingAs($this->admin)->delete("/calendar/{$theirs->id}/series")->assertForbidden();
        $this->assertNotNull($theirs->fresh());
    }

    public function test_a_read_only_member_cannot_remove_a_series(): void
    {
        $this->create(['repeat_weekly' => true, 'repeat_until' => '2026-10-16']);

        $member = User::create([
            'active_band_id'    => $this->band->id,
            'name'              => 'Member',
            'email'             => uniqid() . '@example.test',
            'password'          => bcrypt('secret1234'),
            'email_verified_at' => now(),
        ]);
        $member->joinBand($this->band, 'member');

        $this->actingAs($member)
            ->delete("/calendar/{$this->entries()->first()->id}/series")
            ->assertForbidden();

        $this->assertCount(3, $this->entries());
    }

    public function test_the_calendar_reports_which_entries_belong_to_a_series(): void
    {
        $this->create(['repeat_weekly' => true, 'repeat_until' => '2026-10-09']);

        $this->actingAs($this->admin)->get('/calendar?month=2026-10')
            ->assertInertia(fn ($page) => $page
                ->has('entries', 2)
                ->whereNot('entries.0.series_id', null)
            );
    }
}
