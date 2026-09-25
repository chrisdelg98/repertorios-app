<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use DatabaseTransactions;

    private Band $band;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->band = $this->makeBand('Calendar Band');
        $this->admin = $this->makeUser($this->band, 'admin');
    }

    private function makeBand(string $name): Band
    {
        return Band::create([
            'name'         => $name,
            'code'         => Band::generateCode(),
            'access_pin'   => Band::generatePin(),
            'invite_token' => Band::generateToken(),
        ]);
    }

    private function makeUser(Band $band, string $role): User
    {
        $user = User::create([
            'active_band_id'    => $band->id,
            'name'              => 'User ' . uniqid(),
            'email'             => uniqid() . '@example.test',
            'password'          => bcrypt('secret1234'),
            'email_verified_at' => now(),
        ]);
        $user->joinBand($band, $role);

        return $user;
    }

    private function entry(string $kind, string $date, ?Band $band = null): Service
    {
        return Service::create([
            'band_id' => ($band ?? $this->band)->id,
            'kind'    => $kind,
            'date'    => $date,
            'type'    => ucfirst($kind),
        ]);
    }

    // ── The separation the whole design rests on ─────────────────────────

    public function test_rehearsals_and_meetings_stay_out_of_the_services_list(): void
    {
        $this->entry(Service::KIND_SERVICE, '2026-10-04');
        $this->entry(Service::KIND_REHEARSAL, '2026-10-02');
        $this->entry(Service::KIND_MEETING, '2026-10-03');
        $this->entry(Service::KIND_OTHER, '2026-10-05');

        $this->actingAs($this->admin)->get('/services')
            ->assertInertia(fn ($page) => $page->has('services', 1));
    }

    public function test_the_dashboard_counters_only_count_services(): void
    {
        $this->entry(Service::KIND_SERVICE, now()->addDays(3)->toDateString());
        $this->entry(Service::KIND_REHEARSAL, now()->addDay()->toDateString());
        $this->entry(Service::KIND_MEETING, now()->addDays(2)->toDateString());

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('stats.services_total', 1)
                ->where('stats.services_upcoming', 1)
                ->has('upcoming_services', 1)
            );
    }

    public function test_the_next_service_is_never_a_rehearsal(): void
    {
        // The rehearsal is sooner, and must not take the headline.
        $this->entry(Service::KIND_REHEARSAL, now()->addDay()->toDateString());
        $service = $this->entry(Service::KIND_SERVICE, now()->addDays(5)->toDateString());

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('upcoming_services.0.id', $service->id)
                ->where('next_entry.kind', 'rehearsal')
            );
    }

    public function test_the_dashboard_shows_nothing_secondary_when_there_is_nothing(): void
    {
        $this->entry(Service::KIND_SERVICE, now()->addDays(3)->toDateString());

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('next_entry', null));
    }

    public function test_a_rehearsal_cannot_be_opened_as_a_service(): void
    {
        // Otherwise it renders with a setlist to edit, a share button and a
        // "play all" — none of which mean anything for a rehearsal.
        $rehearsal = $this->entry(Service::KIND_REHEARSAL, '2026-10-02');

        $this->actingAs($this->admin)->get("/services/{$rehearsal->id}")->assertNotFound();
    }

    // ── The calendar ─────────────────────────────────────────────────────

    public function test_the_calendar_shows_every_kind_for_the_month(): void
    {
        $this->entry(Service::KIND_SERVICE, '2026-10-04');
        $this->entry(Service::KIND_REHEARSAL, '2026-10-02');
        $this->entry(Service::KIND_MEETING, '2026-10-03');

        $this->actingAs($this->admin)->get('/calendar?month=2026-10')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('entries', 3)->where('month', '2026-10'));
    }

    public function test_the_grid_reaches_into_the_neighbouring_months(): void
    {
        // 1 October 2026 is a Thursday, so the grid opens on 27 September.
        $this->entry(Service::KIND_REHEARSAL, '2026-09-28');

        $this->actingAs($this->admin)->get('/calendar?month=2026-10')
            ->assertInertia(fn ($page) => $page->has('entries', 1));
    }

    public function test_a_broken_month_falls_back_to_this_one_instead_of_failing(): void
    {
        $this->actingAs($this->admin)->get('/calendar?month=not-a-month')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('month', now()->format('Y-m')));
    }

    public function test_another_bands_entries_are_not_visible(): void
    {
        $this->entry(Service::KIND_REHEARSAL, '2026-10-02', $this->makeBand('Otra iglesia'));

        $this->actingAs($this->admin)->get('/calendar?month=2026-10')
            ->assertInertia(fn ($page) => $page->has('entries', 0));
    }

    // ── Writing ──────────────────────────────────────────────────────────

    public function test_a_rehearsal_can_be_created_with_a_start_and_an_end(): void
    {
        $this->actingAs($this->admin)->post('/calendar', [
            'kind'     => 'rehearsal',
            'type'     => 'Ensayo general',
            'date'     => '2026-10-02',
            'time'     => '18:00',
            'end_time' => '19:00',
            'color'    => 'sky',
        ])->assertRedirect();

        $entry = Service::where('band_id', $this->band->id)->calendarOnly()->first();

        $this->assertSame('Ensayo general', $entry->type);
        $this->assertSame('18:00:00', $entry->end_time ? substr($entry->end_time, 0, 8) : null);
    }

    public function test_an_end_before_the_start_is_refused(): void
    {
        $this->actingAs($this->admin)->post('/calendar', [
            'kind'     => 'rehearsal',
            'type'     => 'Ensayo',
            'date'     => '2026-10-02',
            'time'     => '19:00',
            'end_time' => '18:00',
        ])->assertSessionHasErrors('end_time');
    }

    public function test_a_service_cannot_be_created_from_the_calendar(): void
    {
        // Services are born under Services, where they get a setlist and a
        // team. Two doors into the same thing means two halves of the rules.
        $this->actingAs($this->admin)->post('/calendar', [
            'kind' => 'service',
            'type' => 'Domingo',
            'date' => '2026-10-04',
        ])->assertSessionHasErrors('kind');
    }

    public function test_a_service_cannot_be_edited_or_deleted_from_the_calendar(): void
    {
        $service = $this->entry(Service::KIND_SERVICE, '2026-10-04');

        $this->actingAs($this->admin)->put("/calendar/{$service->id}", [
            'kind' => 'rehearsal',
            'type' => 'Secuestrado',
            'date' => '2026-10-04',
        ])->assertForbidden();

        $this->actingAs($this->admin)->delete("/calendar/{$service->id}")->assertForbidden();

        $this->assertSame('service', $service->fresh()->kind);
    }

    public function test_another_bands_entry_cannot_be_touched(): void
    {
        $theirs = $this->entry(Service::KIND_REHEARSAL, '2026-10-02', $this->makeBand('Otra'));

        $this->actingAs($this->admin)->delete("/calendar/{$theirs->id}")->assertForbidden();
        $this->assertNotNull($theirs->fresh());
    }

    public function test_a_read_only_member_can_look_but_not_write(): void
    {
        $member = $this->makeUser($this->band, 'member');
        $entry = $this->entry(Service::KIND_REHEARSAL, '2026-10-02');

        $this->actingAs($member)->get('/calendar?month=2026-10')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));

        $this->actingAs($member)->post('/calendar', [
            'kind' => 'rehearsal', 'type' => 'Ensayo', 'date' => '2026-10-02',
        ])->assertForbidden();

        $this->actingAs($member)->delete("/calendar/{$entry->id}")->assertForbidden();
    }

    public function test_an_entry_defaults_to_being_a_service(): void
    {
        // Everything that existed before this column was added is a service.
        $entry = Service::create([
            'band_id' => $this->band->id,
            'date'    => '2026-10-04',
            'type'    => 'Domingo',
        ]);

        $this->assertSame('service', $entry->fresh()->kind);
        $this->assertTrue($entry->fresh()->isService());
    }
}
