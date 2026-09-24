<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\BandRoleType;
use App\Models\PushSubscription;
use App\Models\Service;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use DatabaseTransactions;

    private function band(string $name): Band
    {
        return Band::create([
            'name'         => $name,
            'code'         => Band::generateCode(),
            'access_pin'   => Band::generatePin(),
            'invite_token' => Band::generateToken(),
        ]);
    }

    private function user(Band $band, string $role = 'admin'): User
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

    private function device(User $user, string $locale = 'es'): PushSubscription
    {
        return PushSubscription::create([
            'user_id'    => $user->id,
            'endpoint'   => 'https://push.example.test/' . uniqid(),
            'public_key' => str_repeat('a', 87),
            'auth_token' => str_repeat('b', 22),
            'locale'     => $locale,
        ]);
    }

    private function service(Band $band): Service
    {
        return Service::create([
            'band_id' => $band->id,
            'date'    => '2026-10-04',
            'type'    => 'Domingo',
        ]);
    }

    // ── Subscribing ──────────────────────────────────────────────────────

    public function test_a_browser_registers_itself_against_the_logged_in_user(): void
    {
        $band = $this->band('A');
        $user = $this->user($band);

        $this->actingAs($user)->postJson('/push/subscribe', [
            'endpoint' => 'https://push.example.test/abc',
            'keys'     => ['p256dh' => 'pub-key', 'auth' => 'auth-key'],
            'locale'   => 'es',
        ])->assertCreated();

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id'  => $user->id,
            'endpoint' => 'https://push.example.test/abc',
            'locale'   => 'es',
        ]);
    }

    public function test_resubscribing_the_same_browser_updates_instead_of_duplicating(): void
    {
        $band = $this->band('A');
        $user = $this->user($band);

        $payload = [
            'endpoint' => 'https://push.example.test/same',
            'keys'     => ['p256dh' => 'first', 'auth' => 'auth-key'],
        ];

        $this->actingAs($user)->postJson('/push/subscribe', $payload)->assertCreated();

        $payload['keys']['p256dh'] = 'second';
        $this->actingAs($user)->postJson('/push/subscribe', $payload)->assertCreated();

        $this->assertSame(1, PushSubscription::where('endpoint', $payload['endpoint'])->count());
        $this->assertSame('second', PushSubscription::where('endpoint', $payload['endpoint'])->value('public_key'));
    }

    public function test_a_guest_cannot_register_a_device(): void
    {
        $this->postJson('/push/subscribe', [
            'endpoint' => 'https://push.example.test/nope',
            'keys'     => ['p256dh' => 'a', 'auth' => 'b'],
        ])->assertUnauthorized();
    }

    public function test_a_user_cannot_delete_another_users_device(): void
    {
        $band = $this->band('A');
        $mine = $this->user($band);
        $theirs = $this->user($band);

        $device = $this->device($theirs);

        $this->actingAs($mine)->deleteJson("/push/devices/{$device->id}")->assertForbidden();
        $this->assertDatabaseHas('push_subscriptions', ['id' => $device->id]);
    }

    // ── The band boundary ────────────────────────────────────────────────

    public function test_notifying_a_band_only_reaches_that_bands_members(): void
    {
        $mine  = $this->band('Elim');
        $other = $this->band('Otra iglesia');

        $mineMember  = $this->user($mine, 'member');
        $otherMember = $this->user($other, 'member');

        $this->device($mineMember);
        $this->device($otherMember);

        $notifier = new PushNotifier();

        $this->assertSame(1, $notifier->reachableDeviceCount($mine));
        $this->assertSame(1, $notifier->reachableDeviceCount($other));
    }

    public function test_a_person_in_two_bands_is_counted_for_each_one_separately(): void
    {
        $a = $this->band('A');
        $b = $this->band('B');

        $user = $this->user($a, 'admin');
        $user->joinBand($b, 'member');
        $this->device($user);

        $onlyA = $this->user($a, 'member');
        $this->device($onlyA);

        $notifier = new PushNotifier();

        $this->assertSame(2, $notifier->reachableDeviceCount($a));
        $this->assertSame(1, $notifier->reachableDeviceCount($b));
    }

    public function test_someone_removed_from_the_band_stops_being_reachable(): void
    {
        $band = $this->band('A');
        $member = $this->user($band, 'member');
        $this->device($member);

        $notifier = new PushNotifier();
        $this->assertSame(1, $notifier->reachableDeviceCount($band));

        $member->bands()->detach($band->id);

        $this->assertSame(0, $notifier->reachableDeviceCount($band));
    }

    public function test_notifying_one_user_refuses_when_they_do_not_belong_to_that_band(): void
    {
        $a = $this->band('A');
        $b = $this->band('B');

        $outsider = $this->user($a, 'member');
        $this->device($outsider);

        // Not a member of B, so nothing is sent no matter what the caller asks.
        $this->assertSame(0, (new PushNotifier())->toUser($outsider, $b, ['body' => 'hola']));
    }

    // ── Triggers ─────────────────────────────────────────────────────────

    public function test_notify_team_sends_to_the_services_own_band(): void
    {
        $band  = $this->band('Elim');
        $admin = $this->user($band, 'admin');
        $service = $this->service($band);

        $spy = Mockery::mock(PushNotifier::class);
        $spy->shouldReceive('toBand')
            ->once()
            ->withArgs(fn (Band $b) => $b->id === $band->id)
            ->andReturn(1);
        $this->app->instance(PushNotifier::class, $spy);

        $this->actingAs($admin)->post("/services/{$service->id}/notify")->assertRedirect();

        $this->assertNotNull($service->fresh()->team_notified_at);
    }

    public function test_notify_team_is_refused_on_another_bands_service(): void
    {
        $mine   = $this->band('Mine');
        $theirs = $this->band('Theirs');

        $admin = $this->user($mine, 'admin');
        $service = $this->service($theirs);

        $this->actingAs($admin)->post("/services/{$service->id}/notify")->assertForbidden();
    }

    public function test_a_read_only_member_cannot_notify_the_team(): void
    {
        $band = $this->band('A');
        $member = $this->user($band, 'member');
        $service = $this->service($band);

        $this->actingAs($member)->post("/services/{$service->id}/notify")->assertForbidden();
    }

    public function test_assigning_a_registered_user_notifies_only_them(): void
    {
        $band  = $this->band('Elim');
        $admin = $this->user($band, 'admin');
        $musician = $this->user($band, 'member');
        $service = $this->service($band);
        $role = BandRoleType::first();

        $spy = Mockery::mock(PushNotifier::class);
        $spy->shouldReceive('toUser')
            ->once()
            ->withArgs(fn (User $u, Band $b) => $u->id === $musician->id && $b->id === $band->id)
            ->andReturn(1);
        $spy->shouldNotReceive('toBand');
        $this->app->instance(PushNotifier::class, $spy);

        $this->actingAs($admin)->postJson("/services/{$service->id}/assignments", [
            'user_id'           => $musician->id,
            'band_role_type_id' => $role->id,
        ])->assertCreated();
    }

    public function test_assigning_a_guest_by_name_notifies_nobody(): void
    {
        $band  = $this->band('Elim');
        $admin = $this->user($band, 'admin');
        $service = $this->service($band);
        $role = BandRoleType::first();

        $spy = Mockery::mock(PushNotifier::class);
        $spy->shouldNotReceive('toUser');
        $this->app->instance(PushNotifier::class, $spy);

        $this->actingAs($admin)->postJson("/services/{$service->id}/assignments", [
            'manual_name'       => 'Invitado sin cuenta',
            'band_role_type_id' => $role->id,
        ])->assertCreated();
    }

    // ── The deep link ────────────────────────────────────────────────────

    public function test_opening_a_service_from_another_of_my_bands_switches_instead_of_failing(): void
    {
        $a = $this->band('A');
        $b = $this->band('B');

        $user = $this->user($a, 'admin');
        $user->joinBand($b, 'member');

        $service = $this->service($b);

        $this->actingAs($user)->get("/services/{$service->id}")->assertOk();

        $this->assertSame($b->id, $user->fresh()->active_band_id, 'the active band should follow the link');
    }

    public function test_opening_a_service_of_a_band_i_do_not_belong_to_is_still_refused(): void
    {
        $mine   = $this->band('Mine');
        $theirs = $this->band('Theirs');

        $user = $this->user($mine, 'admin');
        $service = $this->service($theirs);

        $this->actingAs($user)->get("/services/{$service->id}")->assertForbidden();
        $this->assertSame($mine->id, $user->fresh()->active_band_id);
    }
}
