<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\Service;
use App\Models\ServiceSong;
use App\Models\SharedLink;
use App\Models\Song;
use App\Models\SongVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ServiceSongNotesTest extends TestCase
{
    use DatabaseTransactions;

    private Band $band;
    private User $admin;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->band = Band::create([
            'name'         => 'Notes Band',
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

        $this->service = Service::create([
            'band_id' => $this->band->id,
            'date'    => '2026-10-04',
            'type'    => 'Domingo',
        ]);
    }

    private function version(?string $notes = null): SongVersion
    {
        $song = Song::create([
            'band_id'         => $this->band->id,
            'name'            => 'Al Rey ' . uniqid(),
            'normalized_name' => 'al rey ' . uniqid(),
            'artist'          => 'Marcos Witt',
        ]);

        return $song->versions()->create([
            'band_id' => $this->band->id,
            'name'    => 'Original',
            'notes'   => $notes,
        ]);
    }

    private function addToService(SongVersion $version, ?string $notes = null): ServiceSong
    {
        return $this->service->serviceSongs()->create([
            'song_version_id' => $version->id,
            'position'        => 1,
            'notes'           => $notes,
        ]);
    }

    public function test_the_songs_own_note_shows_when_the_service_has_not_overridden_it(): void
    {
        $serviceSong = $this->addToService($this->version('Empieza suave'));

        $this->assertSame('Empieza suave', $serviceSong->fresh()->effective_notes);
    }

    public function test_a_service_note_takes_precedence_over_the_songs_note(): void
    {
        $serviceSong = $this->addToService($this->version('Empieza suave'), 'Hoy la hacemos completa');

        $this->assertSame('Hoy la hacemos completa', $serviceSong->fresh()->effective_notes);
    }

    public function test_a_service_note_works_on_a_song_that_has_none_of_its_own(): void
    {
        $serviceSong = $this->addToService($this->version(), 'Entra en el 1:13');

        $this->assertSame('Entra en el 1:13', $serviceSong->fresh()->effective_notes);
    }

    public function test_no_note_anywhere_means_no_note(): void
    {
        $serviceSong = $this->addToService($this->version());

        $this->assertNull($serviceSong->fresh()->effective_notes);
    }

    public function test_deleting_the_songs_note_leaves_the_service_override_standing(): void
    {
        $version     = $this->version('Nota global');
        $serviceSong = $this->addToService($version, 'Nota del servicio');

        $version->update(['notes' => null]);

        $this->assertSame('Nota del servicio', $serviceSong->fresh()->effective_notes);
    }

    public function test_deleting_the_songs_note_leaves_nothing_when_there_is_no_override(): void
    {
        $version     = $this->version('Nota global');
        $serviceSong = $this->addToService($version);

        $version->update(['notes' => null]);

        $this->assertNull($serviceSong->fresh()->effective_notes);
    }

    public function test_the_note_can_be_written_for_one_service_only(): void
    {
        $version     = $this->version('Nota global');
        $serviceSong = $this->addToService($version);

        $this->actingAs($this->admin)
            ->patch("/services/{$this->service->id}/songs/{$serviceSong->id}", [
                'notes' => 'Hoy empieza en el 1:13',
            ])->assertRedirect();

        $this->assertSame('Hoy empieza en el 1:13', $serviceSong->fresh()->notes);
        // The song itself is untouched — other services keep seeing the original.
        $this->assertSame('Nota global', $version->fresh()->notes);
    }

    public function test_clearing_the_override_brings_the_songs_note_back(): void
    {
        $version     = $this->version('Nota global');
        $serviceSong = $this->addToService($version, 'Solo hoy');

        $this->actingAs($this->admin)
            ->patch("/services/{$this->service->id}/songs/{$serviceSong->id}", ['notes' => ''])
            ->assertRedirect();

        $fresh = $serviceSong->fresh();
        $this->assertNull($fresh->notes);
        $this->assertSame('Nota global', $fresh->effective_notes);
    }

    public function test_a_read_only_member_cannot_write_notes(): void
    {
        $member = User::create([
            'active_band_id'    => $this->band->id,
            'name'              => 'Member',
            'email'             => uniqid() . '@example.test',
            'password'          => bcrypt('secret1234'),
            'email_verified_at' => now(),
        ]);
        $member->joinBand($this->band, 'member');

        $serviceSong = $this->addToService($this->version());

        $this->actingAs($member)
            ->patch("/services/{$this->service->id}/songs/{$serviceSong->id}", ['notes' => 'nope'])
            ->assertForbidden();

        $this->assertNull($serviceSong->fresh()->notes);
    }

    public function test_a_song_cannot_be_annotated_through_another_service(): void
    {
        $other = Service::create([
            'band_id' => $this->band->id,
            'date'    => '2026-10-11',
            'type'    => 'Domingo',
        ]);
        $serviceSong = $this->addToService($this->version());

        $this->actingAs($this->admin)
            ->patch("/services/{$other->id}/songs/{$serviceSong->id}", ['notes' => 'nope'])
            ->assertForbidden();
    }

    public function test_a_note_typed_for_a_brand_new_song_becomes_the_songs_own_note(): void
    {
        $this->actingAs($this->admin)->post("/services/{$this->service->id}/songs", [
            'song_name' => 'Cancion Nueva',
            'artist'    => 'Alguien',
            'notes'     => 'Nota de la cancion',
        ])->assertRedirect();

        $serviceSong = ServiceSong::where('service_id', $this->service->id)->latest('id')->first();

        $this->assertNull($serviceSong->notes, 'it should not be pinned to this service');
        $this->assertSame('Nota de la cancion', $serviceSong->songVersion->notes);
        $this->assertSame('Nota de la cancion', $serviceSong->effective_notes);
    }

    public function test_a_note_typed_for_an_existing_song_is_scoped_to_this_service(): void
    {
        $version = $this->version('Nota global');

        $this->actingAs($this->admin)->post("/services/{$this->service->id}/songs", [
            'song_version_id' => $version->id,
            'notes'           => 'Solo para este domingo',
        ])->assertRedirect();

        $serviceSong = ServiceSong::where('service_id', $this->service->id)->latest('id')->first();

        $this->assertSame('Solo para este domingo', $serviceSong->notes);
        $this->assertSame('Nota global', $version->fresh()->notes);
    }

    public function test_the_shared_public_link_shows_the_effective_note(): void
    {
        $version = $this->version('Nota global');
        $this->addToService($version, 'Nota del servicio');

        $this->actingAs($this->admin)->post("/services/{$this->service->id}/share");

        $token = SharedLink::latest('id')->first()->token;

        $this->get("/r/{$token}")
            ->assertInertia(fn ($page) => $page->where('service.songs.0.notes', 'Nota del servicio'));
    }
}
