<?php

return [

    /*
     * Rehearsal tracks are off until the feature ships. With this false the
     * app behaves as if audio did not exist: no upload field, no management
     * screen, no track in any payload, and the endpoints refuse.
     */
    'enabled' => (bool) env('AUDIO_UPLOADS_ENABLED', false),

    /*
     * What one band may store, in megabytes, and how far a single upload may
     * carry them past it.
     *
     * The rule is deliberately two-sided: a band already at the quota cannot
     * start another upload, but one that is still under it is never stopped
     * mid-thought by a file that happens to cross the line. Someone at 198 MB
     * uploading 5 MB lands on 203 and is told, next time, that they are full.
     */
    'band_quota_mb' => (int) env('AUDIO_BAND_QUOTA_MB', 200),
    'band_grace_mb' => (int) env('AUDIO_BAND_GRACE_MB', 10),

    /* The largest single file. MP3 at 128 kbps runs about 1 MB per minute. */
    'max_file_mb' => (int) env('AUDIO_MAX_FILE_MB', 5),

];
