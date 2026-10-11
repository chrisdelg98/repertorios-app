<?php

namespace App\Services;

use App\Models\Band;
use App\Models\SongVersion;

/**
 * How much storage a band has used, and whether one more file fits.
 *
 * Every question about audio capacity is answered here so the rule cannot
 * drift between the endpoint that refuses an upload and the screen that tells
 * someone how much room they have left.
 */
class BandAudioQuota
{
    public function enabled(): bool
    {
        return (bool) config('audio.enabled');
    }

    /**
     * What this band may store.
     *
     * A band carries its own figure only when someone deliberately gave it
     * one. Null means "whatever the default is", so raising
     * AUDIO_BAND_QUOTA_MB reaches every band that never needed a special
     * arrangement without a single row being touched.
     */
    public function quotaBytes(?int $bandId = null): int
    {
        $megabytes = $bandId
            ? Band::whereKey($bandId)->value('audio_quota_mb')
            : null;

        return (int) ($megabytes ?? config('audio.band_quota_mb')) * 1024 * 1024;
    }

    public function graceBytes(): int
    {
        return (int) config('audio.band_grace_mb') * 1024 * 1024;
    }

    public function maxFileBytes(): int
    {
        return (int) config('audio.max_file_mb') * 1024 * 1024;
    }

    public function usedBytes(int $bandId): int
    {
        return (int) SongVersion::where('band_id', $bandId)->sum('audio_size');
    }

    /**
     * Whether a file of this size may be uploaded.
     *
     * Two conditions, and both matter. Being under the quota is what earns the
     * right to upload at all; the ceiling is what stops that one upload from
     * running away. A band at 203 MB is over and gets nothing more, even
     * though 203 is below the 210 ceiling.
     */
    public function accepts(int $bandId, int $size): bool
    {
        if ($size > $this->maxFileBytes()) {
            return false;
        }

        $used = $this->usedBytes($bandId);

        $quota = $this->quotaBytes($bandId);

        return $used < $quota && ($used + $size) <= ($quota + $this->graceBytes());
    }

    /** What the settings screen shows, in one call. */
    public function summary(int $bandId): array
    {
        $used = $this->usedBytes($bandId);
        $quota = $this->quotaBytes($bandId);

        return [
            'used_bytes'  => $used,
            'quota_bytes' => $quota,
            'grace_bytes' => $this->graceBytes(),
            'max_file_bytes' => $this->maxFileBytes(),
            // Capped so a band in the grace zone shows a full bar, not 102%.
            'percent'     => $quota > 0 ? min(100, (int) round($used / $quota * 100)) : 0,
            'is_full'     => $used >= $quota,
        ];
    }
}
