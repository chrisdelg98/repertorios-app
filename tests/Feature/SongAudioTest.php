<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\Song;
use App\Models\SongVersion;
use App\Models\User;
use App\Services\R2Signer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class SongAudioTest extends TestCase
{
    use DatabaseTransactions;

    private Band $band;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->band = $this->band('Audio Band');
        $this->admin = $this->user($this->band, 'admin');

        // Nothing here should reach Cloudflare; the signer is stubbed so the
        // tests cover our rules, not the network.
        $signer = Mockery::mock(R2Signer::class);
        $signer->shouldReceive('isConfigured')->andReturn(true);
        $signer->shouldReceive('presignPut')->andReturn('https://storage.test/signed-put');
        $signer->shouldReceive('presignGet')->andReturn('https://storage.test/signed-get');
        $signer->shouldReceive('delete')->andReturn(true);
        $this->app->instance(R2Signer::class, $signer);
    }

    private function band(string $name): Band
    {
        return Band::create([
            'name'         => $name,
            'code'         => Band::generateCode(),
            'access_pin'   => Band::generatePin(),
            'invite_token' => Band::generateToken(),
        ]);
    }

    private function user(Band $band, string $role): User
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

    private function version(?Band $band = null): SongVersion
    {
        $band ??= $this->band;

        $song = Song::create([
            'band_id'         => $band->id,
            'name'            => 'Cancion ' . uniqid(),
            'normalized_name' => 'cancion ' . uniqid(),
        ]);

        return $song->versions()->create(['band_id' => $band->id, 'name' => 'Original']);
    }

    // ── Signing an upload ────────────────────────────────────────────────

    public function test_an_admin_gets_a_signed_url_and_a_key_inside_their_own_prefix(): void
    {
        $version = $this->version();

        $response = $this->actingAs($this->admin)
            ->postJson("/song-versions/{$version->id}/audio/presign", [
                'mime' => 'audio/mpeg',
                'size' => 5_000_000,
            ])->assertOk();

        $this->assertSame('https://storage.test/signed-put', $response->json('url'));
        $this->assertStringStartsWith(
            "bands/{$this->band->id}/audio/{$version->id}/",
            $response->json('key')
        );
        $this->assertStringEndsWith('.mp3', $response->json('key'));
    }

    public function test_a_file_over_the_limit_is_refused_before_it_is_uploaded(): void
    {
        $version = $this->version();

        $this->actingAs($this->admin)
            ->postJson("/song-versions/{$version->id}/audio/presign", [
                'mime' => 'audio/mpeg',
                'size' => 40 * 1024 * 1024,
            ])->assertStatus(422);
    }

    public function test_a_format_that_is_not_audio_is_refused(): void
    {
        $version = $this->version();

        $this->actingAs($this->admin)
            ->postJson("/song-versions/{$version->id}/audio/presign", [
                'mime' => 'application/pdf',
                'size' => 1000,
            ])->assertStatus(422);
    }

    public function test_a_read_only_member_cannot_upload(): void
    {
        $member = $this->user($this->band, 'member');
        $version = $this->version();

        $this->actingAs($member)
            ->postJson("/song-versions/{$version->id}/audio/presign", [
                'mime' => 'audio/mpeg',
                'size' => 1000,
            ])->assertForbidden();
    }

    public function test_another_bands_song_cannot_be_touched(): void
    {
        $theirs = $this->version($this->band('Otra iglesia'));

        $this->actingAs($this->admin)
            ->postJson("/song-versions/{$theirs->id}/audio/presign", [
                'mime' => 'audio/mpeg',
                'size' => 1000,
            ])->assertForbidden();
    }

    // ── Recording the upload ─────────────────────────────────────────────

    public function test_attaching_records_the_track(): void
    {
        $version = $this->version();
        $key = "bands/{$this->band->id}/audio/{$version->id}/abc.mp3";

        $this->actingAs($this->admin)
            ->putJson("/song-versions/{$version->id}/audio", [
                'key'  => $key,
                'name' => 'secuencia.mp3',
                'size' => 4_200_000,
                'mime' => 'audio/mpeg',
            ])->assertOk();

        $fresh = $version->fresh();
        $this->assertSame($key, $fresh->audio_path);
        $this->assertSame('secuencia.mp3', $fresh->audio_name);
        $this->assertTrue($fresh->hasAudio());
    }

    public function test_a_key_outside_the_versions_prefix_is_rejected(): void
    {
        $version = $this->version();

        // The prefix is derived on the server, so a client pointing at another
        // band's folder must not be able to claim it.
        $this->actingAs($this->admin)
            ->putJson("/song-versions/{$version->id}/audio", [
                'key'  => 'bands/999/audio/1/stolen.mp3',
                'name' => 'x.mp3',
                'size' => 1000,
                'mime' => 'audio/mpeg',
            ])->assertStatus(422);

        $this->assertNull($version->fresh()->audio_path);
    }

    public function test_replacing_a_track_keeps_only_the_new_one(): void
    {
        $version = $this->version();
        $prefix = "bands/{$this->band->id}/audio/{$version->id}/";

        foreach (['first.mp3', 'second.mp3'] as $file) {
            $this->actingAs($this->admin)->putJson("/song-versions/{$version->id}/audio", [
                'key'  => $prefix . $file,
                'name' => $file,
                'size' => 1000,
                'mime' => 'audio/mpeg',
            ])->assertOk();
        }

        $this->assertSame($prefix . 'second.mp3', $version->fresh()->audio_path);
    }

    // ── Removing ─────────────────────────────────────────────────────────

    public function test_removing_clears_every_audio_column(): void
    {
        $version = $this->version();
        $version->update([
            'audio_path' => "bands/{$this->band->id}/audio/{$version->id}/a.mp3",
            'audio_name' => 'a.mp3',
            'audio_size' => 1000,
            'audio_mime' => 'audio/mpeg',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/song-versions/{$version->id}/audio")
            ->assertOk();

        $fresh = $version->fresh();
        $this->assertNull($fresh->audio_path);
        $this->assertNull($fresh->audio_name);
        $this->assertFalse($fresh->hasAudio());
    }

    // ── Reading ──────────────────────────────────────────────────────────

    public function test_a_version_serializes_its_track_with_a_signed_url(): void
    {
        $version = $this->version();
        $version->update([
            'audio_path' => "bands/{$this->band->id}/audio/{$version->id}/a.mp3",
            'audio_name' => 'a.mp3',
            'audio_size' => 1000,
            'audio_mime' => 'audio/mpeg',
        ]);

        $audio = $version->fresh()->audio;

        $this->assertSame('a.mp3', $audio['name']);
        $this->assertSame('https://storage.test/signed-get', $audio['url']);
    }

    public function test_the_object_key_never_reaches_the_browser(): void
    {
        $version = $this->version();
        $version->update([
            'audio_path' => "bands/{$this->band->id}/audio/{$version->id}/secret.mp3",
            'audio_name' => 'a.mp3',
            'audio_size' => 1000,
            'audio_mime' => 'audio/mpeg',
        ]);

        $this->assertArrayNotHasKey('audio_path', $version->fresh()->toArray());
    }

    public function test_a_version_with_no_track_serializes_as_null(): void
    {
        $this->assertNull($this->version()->audio);
    }
}
