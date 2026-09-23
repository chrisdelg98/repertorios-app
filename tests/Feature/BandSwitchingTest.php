<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BandSwitchingTest extends TestCase
{
    use DatabaseTransactions;

    private function band(string $name, ?int $creatorId = null): Band
    {
        return Band::create([
            'name'         => $name,
            'code'         => Band::generateCode(),
            'access_pin'   => Band::generatePin(),
            'invite_token' => Band::generateToken(),
            'creator_id'   => $creatorId,
        ]);
    }

    private function user(Band $band, string $role = 'admin'): User
    {
        $user = User::create([
            'active_band_id'    => $band->id,
            'name'              => 'Test ' . uniqid(),
            'email'             => uniqid() . '@example.test',
            'password'          => bcrypt('secret1234'),
            'email_verified_at' => now(),
        ]);
        $user->joinBand($band, $role);

        return $user;
    }

    public function test_a_user_can_be_admin_in_one_band_and_member_in_another(): void
    {
        $a = $this->band('Band A');
        $b = $this->band('Band B');

        $user = $this->user($a, 'admin');
        $user->joinBand($b, 'member');

        $this->assertTrue($user->isAdminOf($a->id));
        $this->assertFalse($user->isAdminOf($b->id));
        $this->assertSame('member', $user->roleIn($b->id));
    }

    public function test_switching_changes_the_active_band_and_the_write_permission(): void
    {
        $a = $this->band('Band A');
        $b = $this->band('Band B');
        $user = $this->user($a, 'admin');
        $user->joinBand($b, 'member');

        $this->actingAs($user)->post("/bands/{$b->id}/switch")
            ->assertRedirect('/dashboard');

        $this->assertSame($b->id, $user->fresh()->active_band_id);

        // Admin in A, plain member in B → the "new service" button must be gone.
        $this->actingAs($user->fresh())->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('auth.can_write', false));

        $this->actingAs($user->fresh())->post("/bands/{$a->id}/switch");

        $this->actingAs($user->fresh())->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('auth.can_write', true));
    }

    public function test_a_user_cannot_switch_into_a_band_they_do_not_belong_to(): void
    {
        $mine     = $this->band('Mine');
        $stranger = $this->band('Not mine');
        $user     = $this->user($mine, 'admin');

        $this->actingAs($user)->post("/bands/{$stranger->id}/switch")
            ->assertForbidden();

        $this->assertSame($mine->id, $user->fresh()->active_band_id);
    }

    public function test_data_is_scoped_to_the_active_band(): void
    {
        $a = $this->band('Band A');
        $b = $this->band('Band B');
        $user = $this->user($a, 'admin');
        $user->joinBand($b, 'admin');

        \App\Models\Song::create([
            'band_id' => $a->id, 'name' => 'Only In A', 'normalized_name' => 'only in a',
        ]);

        $this->actingAs($user->fresh())->get('/songs')
            ->assertInertia(fn ($page) => $page->has('songs', 1));

        $this->actingAs($user->fresh())->post("/bands/{$b->id}/switch");

        $this->actingAs($user->fresh())->get('/songs')
            ->assertInertia(fn ($page) => $page->has('songs', 0));
    }

    public function test_an_invite_link_joins_a_logged_in_user_instead_of_opening_a_guest_session(): void
    {
        $a = $this->band('Band A');
        $b = $this->band('Band B');
        $user = $this->user($a, 'admin');

        $this->actingAs($user)->get("/join/{$b->invite_token}")
            ->assertRedirect('/dashboard');

        $fresh = $user->fresh();
        $this->assertSame('member', $fresh->roleIn($b->id));
        $this->assertSame($b->id, $fresh->active_band_id);
        // Still admin where they were admin.
        $this->assertTrue($fresh->isAdminOf($a->id));
    }

    public function test_an_invite_link_never_demotes_an_existing_admin(): void
    {
        $a = $this->band('Band A');
        $user = $this->user($a, 'admin');

        $this->actingAs($user)->get("/join/{$a->invite_token}");

        $this->assertTrue($user->fresh()->isAdminOf($a->id));
    }

    public function test_removing_a_member_deletes_the_membership_not_the_account(): void
    {
        $a = $this->band('Band A');
        $b = $this->band('Band B');

        $creator = $this->user($a, 'admin');
        $a->update(['creator_id' => $creator->id]);

        $member = $this->user($a, 'member');
        $member->joinBand($b, 'admin');

        $this->actingAs($creator)->delete("/settings/members/{$member->id}")
            ->assertRedirect();

        $fresh = $member->fresh();
        $this->assertNotNull($fresh, 'the user account must survive');
        $this->assertNull($fresh->roleIn($a->id));
        $this->assertSame('admin', $fresh->roleIn($b->id));
        // They were looking at A when removed → moved to the band they still have.
        $this->assertSame($b->id, $fresh->active_band_id);
    }

    public function test_creating_a_second_band_keeps_the_first_one(): void
    {
        $a = $this->band('Band A');
        $user = $this->user($a, 'admin');

        $this->actingAs($user)->post('/bands', ['name' => 'Second Band'])
            ->assertRedirect('/dashboard');

        $fresh = $user->fresh();
        $this->assertSame(2, $fresh->bands()->count());
        $this->assertNotSame($a->id, $fresh->active_band_id);
        $this->assertTrue($fresh->isAdminOf($fresh->active_band_id));
        $this->assertTrue($fresh->isAdminOf($a->id));
    }

    public function test_a_user_with_no_band_left_is_sent_to_create_one(): void
    {
        $a = $this->band('Band A');
        $user = $this->user($a, 'admin');

        $user->bands()->detach();
        $user->forceFill(['active_band_id' => null])->save();

        $this->actingAs($user->fresh())->get('/dashboard')
            ->assertRedirect(route('bands.create'));
    }

    public function test_instrument_roles_are_per_band(): void
    {
        $a = $this->band('Band A');
        $b = $this->band('Band B');

        $creator = $this->user($a, 'admin');
        $a->update(['creator_id' => $creator->id]);

        $member = $this->user($a, 'member');
        $member->joinBand($b, 'member');

        $drums = \App\Models\BandRoleType::first();
        $bass  = \App\Models\BandRoleType::skip(1)->first();

        $this->actingAs($creator)->put("/settings/members/{$member->id}/roles", [
            'role_ids' => [$drums->id],
        ])->assertRedirect();

        $member->bandRoles($b->id)->syncWithPivotValues([$bass->id], ['band_id' => $b->id]);

        $this->assertSame([$drums->id], $member->bandRoles($a->id)->pluck('band_role_types.id')->all());
        $this->assertSame([$bass->id], $member->bandRoles($b->id)->pluck('band_role_types.id')->all());
    }
}
