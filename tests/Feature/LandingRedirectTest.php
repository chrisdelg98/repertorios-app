<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LandingRedirectTest extends TestCase
{
    use DatabaseTransactions;

    private function band(): Band
    {
        return Band::create([
            'name'         => 'Landing Band',
            'code'         => Band::generateCode(),
            'access_pin'   => Band::generatePin(),
            'invite_token' => Band::generateToken(),
        ]);
    }

    public function test_a_visitor_with_no_session_sees_the_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Welcome'));
    }

    public function test_a_logged_in_user_goes_straight_to_the_dashboard(): void
    {
        $band = $this->band();

        $user = User::create([
            'active_band_id'    => $band->id,
            'name'              => 'Christian',
            'email'             => uniqid() . '@example.test',
            'password'          => bcrypt('secret1234'),
            'email_verified_at' => now(),
        ]);
        $user->joinBand($band, 'admin');

        $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_a_guest_who_came_through_a_pin_or_invite_link_also_skips_the_landing(): void
    {
        $band = $this->band();

        $this->withSession(['band_id' => $band->id, 'access_level' => 'member'])
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }

    public function test_logging_out_lands_back_on_the_login_page(): void
    {
        $band = $this->band();

        $user = User::create([
            'active_band_id'    => $band->id,
            'name'              => 'Christian',
            'email'             => uniqid() . '@example.test',
            'password'          => bcrypt('secret1234'),
            'email_verified_at' => now(),
        ]);
        $user->joinBand($band, 'admin');

        $this->actingAs($user)->post('/logout')->assertRedirect(route('auth.login'));

        // And the landing page is reachable again once the session is gone.
        $this->get('/')->assertOk();
    }
}
