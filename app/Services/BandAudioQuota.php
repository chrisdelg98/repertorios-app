<?php

namespace App\Services;

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

    public function quotaBytes(): int
    {
        return (int) config('audio.band_quota_mb') * 1024 * 1024;
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

        return $used < $this->quotaBytes()
            && ($used + $size) <= ($this->quotaBytes() + $this->graceBytes());
    }

    /** What the settings screen shows, in one call. */
    public function summary(int $bandId): array
    {
        $used = $this->usedBytes($bandId);
        $quota = $this->quotaBytes();

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
