<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ServiceColorTest extends TestCase
{
    use DatabaseTransactions;

    private Band $band;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->band = Band::create([
            'name'         => 'Colour Band',
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
        $this->band->update(['creator_id' => $this->admin->id]);
    }

    private function service(string $color = 'indigo'): Service
    {
        return Service::create([
            'band_id' => $this->band->id,
            'date'    => '2026-10-04',
            'type'    => 'Domingo',
            'color'   => $color,
        ]);
    }

    public function test_a_service_created_without_choosing_a_colour_gets_the_brand_default(): void
    {
        $this->actingAs($this->admin)->post('/services', [
            'date' => '2026-10-04',
            'type' => 'Domingo',
        ])->assertRedirect();

        $this->assertSame('indigo', Service::latest('id')->first()->color);
    }

    public function test_a_colour_can_be_chosen_when_creating(): void
    {
        $this->actingAs($this->admin)->post('/services', [
            'date'  => '2026-10-04',
            'type'  => 'Especial',
            'color' => 'amber',
        ])->assertRedirect();

        $this->assertSame('amber', Service::latest('id')->first()->color);
    }

    public function test_a_colour_outside_the_palette_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/services', [
            'date'  => '2026-10-04',
            'type'  => 'Domingo',
            'color' => 'hotpink',
        ])->assertSessionHasErrors('color');
    }

    public function test_the_colour_can_be_changed_from_the_card_menu(): void
    {
        $service = $this->service();

        $this->actingAs($this->admin)
            ->patch("/services/{$service->id}/color", ['color' => 'rose'])
            ->assertRedirect();

        $this->assertSame('rose', $service->fresh()->color);
    }

    public function test_a_read_only_member_cannot_change_the_colour(): void
    {
        $member = User::create([
            'active_band_id'    => $this->band->id,
            'name'              => 'Member',
            'email'             => uniqid() . '@example.test',
            'password'          => bcrypt('secret1234'),
            'email_verified_at' => now(),
        ]);
        $member->joinBand($this->band, 'member');

        $service = $this->service('sky');

        $this->actingAs($member)
            ->patch("/services/{$service->id}/color", ['color' => 'rose'])
            ->assertForbidden();

        $this->assertSame('sky', $service->fresh()->color);
    }

    public function test_the_colour_cannot_be_changed_on_another_bands_service(): void
    {
        $other = Band::create([
            'name'         => 'Other Band',
            'code'         => Band::generateCode(),
            'access_pin'   => Band::generatePin(),
            'invite_token' => Band::generateToken(),
        ]);
        $theirs = Service::create([
            'band_id' => $other->id, 'date' => '2026-10-04', 'type' => 'Domingo',
        ]);

        $this->actingAs($this->admin)
            ->patch("/services/{$theirs->id}/color", ['color' => 'rose'])
            ->assertForbidden();
    }

    public function test_duplicating_a_service_keeps_its_colour(): void
    {
        $service = $this->service('emerald');

        $this->actingAs($this->admin)
            ->post("/services/{$service->id}/duplicate", ['date' => '2026-10-11'])
            ->assertRedirect();

        $this->assertSame('emerald', Service::latest('id')->first()->color);
    }

    public function test_the_colour_reaches_the_pages_that_paint_it(): void
    {
        $service = $this->service('violet');

        $this->actingAs($this->admin)->get('/services')
            ->assertInertia(fn ($page) => $page->where('services.0.color', 'violet'));

        $this->actingAs($this->admin)->get("/services/{$service->id}")
            ->assertInertia(fn ($page) => $page->where('service.color', 'violet'));
    }
}
