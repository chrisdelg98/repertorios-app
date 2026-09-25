<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\Song;
use App\Models\SongVersion;
use App\Models\User;
use App\Services\BandAudioQuota;
use App\Services\R2Signer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class AudioQuotaTest extends TestCase
{
    use DatabaseTransactions;

    private const MB = 1024 * 1024;

    private Band $band;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'audio.enabled'       => true,
            'audio.band_quota_mb' => 200,
            'audio.band_grace_mb' => 10,
            'audio.max_file_mb'   => 5,
        ]);

        $this->band = Band::create([
            'name'         => 'Quota Band',
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

        $signer = Mockery::mock(R2Signer::class);
        $signer->shouldReceive('isConfigured')->andReturn(true);
        $signer->shouldReceive('presignPut')->andReturn('https://storage.test/put');
        $signer->shouldReceive('presignGet')->andReturn('https://storage.test/get');
        $signer->shouldReceive('delete')->andReturn(true);
        $this->app->instance(R2Signer::class, $signer);
    }

    private function version(?int $audioBytes = null): SongVersion
    {
        $song = Song::create([
            'band_id'         => $this->band->id,
            'name'            => 'Cancion ' . uniqid(),
            'normalized_name' => 'cancion ' . uniqid(),
        ]);

        return $song->versions()->create([
            'band_id'    => $this->band->id,
            'name'       => 'Original',
            'audio_path' => $audioBytes ? 'bands/x/audio/y/z.mp3' : null,
            'audio_name' => $audioBytes ? 'z.mp3' : null,
            'audio_size' => $audioBytes,
            'audio_mime' => $audioBytes ? 'audio/mpeg' : null,
        ]);
    }

    private function presign(SongVersion $version, int $size)
    {
        return $this->actingAs($this->admin)
            ->postJson("/song-versions/{$version->id}/audio/presign", [
                'mime' => 'audio/mpeg',
                'size' => $size,
            ]);
    }

    // ── The feature flag ─────────────────────────────────────────────────

    public function test_with_the_feature_off_the_endpoints_do_not_exist(): void
    {
        config(['audio.enabled' => false]);

        $version = $this->version();

        $this->presign($version, self::MB)->assertNotFound();
        $this->actingAs($this->admin)->get('/settings/audio')->assertNotFound();
    }

    public function test_with_the_feature_off_a_track_is_not_serialized(): void
    {
        $version = $this->version(3 * self::MB);

        $this->assertNotNull($version->fresh()->audio);

        config(['audio.enabled' => false]);

        $this->assertNull($version->fresh()->audio);
    }

    // ── The quota rule ───────────────────────────────────────────────────

    public function test_a_band_well_under_the_quota_can_upload(): void
    {
        $this->version(50 * self::MB);

        $this->presign($this->version(), 5 * self::MB)->assertOk();
    }

    public function test_an_upload_that_crosses_the_line_is_allowed_once(): void
    {
        // 198 used + 5 lands on 203: over the quota, inside the grace.
        $this->version(198 * self::MB);

        $this->presign($this->version(), 5 * self::MB)->assertOk();
    }

    public function test_a_band_already_over_the_quota_gets_nothing_more(): void
    {
        // The band that just crossed the line comes back for another file.
        $this->version(203 * self::MB);

        $this->presign($this->version(), self::MB)
            ->assertStatus(422)
            ->assertJsonPath('message', 'quota_exceeded');
    }

    public function test_exactly_at_the_quota_counts_as_full(): void
    {
        $this->version(200 * self::MB);

        $this->presign($this->version(), self::MB)->assertStatus(422);
    }

    public function test_a_file_over_the_per_file_limit_is_refused(): void
    {
        $this->presign($this->version(), 6 * self::MB)->assertStatus(422);
    }

    public function test_the_ceiling_still_holds_when_the_file_is_large_enough_to_break_it(): void
    {
        // 195 used, and a file big enough to pass 210. Only reachable if the
        // per-file limit is raised, which is exactly when this must not give.
        config(['audio.max_file_mb' => 50]);
        $this->version(195 * self::MB);

        $this->presign($this->version(), 20 * self::MB)->assertStatus(422);
        $this->presign($this->version(), 14 * self::MB)->assertOk();
    }

    // ── Usage and freeing space ──────────────────────────────────────────

    public function test_usage_only_counts_this_bands_tracks(): void
    {
        $this->version(10 * self::MB);

        $other = Band::create([
            'name' => 'Otra', 'code' => Band::generateCode(),
            'access_pin' => Band::generatePin(), 'invite_token' => Band::generateToken(),
        ]);
        $song = Song::create([
            'band_id' => $other->id, 'name' => 'X', 'normalized_name' => 'x' . uniqid(),
        ]);
        $song->versions()->create([
            'band_id' => $other->id, 'name' => 'Original',
            'audio_path' => 'a', 'audio_size' => 99 * self::MB, 'audio_mime' => 'audio/mpeg',
        ]);

        $quota = app(BandAudioQuota::class);

        $this->assertSame(10 * self::MB, $quota->usedBytes($this->band->id));
        $this->assertSame(99 * self::MB, $quota->usedBytes($other->id));
    }

    public function test_removing_a_track_frees_the_space(): void
    {
        $version = $this->version(60 * self::MB);
        $quota = app(BandAudioQuota::class);

        $this->assertSame(60 * self::MB, $quota->usedBytes($this->band->id));

        $this->actingAs($this->admin)
            ->delete("/settings/audio/{$version->id}")
            ->assertRedirect();

        $this->assertSame(0, $quota->usedBytes($this->band->id));
        $this->assertNull($version->fresh()->audio_path);
    }

    public function test_another_bands_track_cannot_be_removed(): void
    {
        $other = Band::create([
            'name' => 'Otra', 'code' => Band::generateCode(),
            'access_pin' => Band::generatePin(), 'invite_token' => Band::generateToken(),
        ]);
        $song = Song::create([
            'band_id' => $other->id, 'name' => 'X', 'normalized_name' => 'x' . uniqid(),
        ]);
        $theirs = $song->versions()->create([
            'band_id' => $other->id, 'name' => 'Original',
            'audio_path' => 'a', 'audio_size' => self::MB, 'audio_mime' => 'audio/mpeg',
        ]);

        $this->actingAs($this->admin)
            ->delete("/settings/audio/{$theirs->id}")
            ->assertForbidden();

        $this->assertNotNull($theirs->fresh()->audio_path);
    }

    public function test_the_library_lists_this_bands_tracks_with_its_usage(): void
    {
        $this->version(30 * self::MB);
        $this->version(20 * self::MB);
        $this->version();   // no track

        $this->actingAs($this->admin)->get('/settings/audio')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('tracks', 2)
                ->where('usage.used_bytes', 50 * self::MB)
                ->where('usage.is_full', false)
            );
    }
}
