<?php

namespace App\Http\Controllers\Songs;

use App\Http\Controllers\Concerns\BandAware;
use App\Http\Controllers\Controller;
use App\Models\SongVersion;
use App\Services\R2Signer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The rehearsal track attached to a song version.
 *
 * The file goes straight from the browser to R2 and comes back the same way;
 * this server only ever signs URLs. That keeps audio out of PHP's upload limits
 * and off the hosting's bandwidth, and it is why there is no `store` here that
 * accepts a file.
 */
class SongAudioController extends Controller
{
    use BandAware;

    /**
     * The one number that decides how much a band can store. Raising it is a
     * single edit here plus the matching copy in resources/js/i18n.
     *
     * For scale: 5 MB is about a five-minute MP3 at 128 kbps, which is the
     * usual shape of a rehearsal track.
     */
    private const MAX_BYTES = 5 * 1024 * 1024;

    /** MP3 only: it is what the band already has, and it plays everywhere. */
    private const ALLOWED_MIME = ['audio/mpeg'];

    private const EXTENSIONS = ['audio/mpeg' => 'mp3'];

    /**
     * Hands back a URL the browser can upload to, and the key it will land on.
     *
     * The key is built here and never taken from the client, so nobody can
     * point an upload at another band's prefix.
     */
    public function presign(Request $request, SongVersion $songVersion, R2Signer $signer): JsonResponse
    {
        $this->authorizeVersion($songVersion);

        if (!$signer->isConfigured()) {
            return response()->json(['message' => 'storage_not_configured'], 503);
        }

        $data = $request->validate([
            'mime' => ['required', 'string', Rule::in(self::ALLOWED_MIME)],
            'size' => ['required', 'integer', 'min:1', 'max:' . self::MAX_BYTES],
        ]);

        $extension = self::EXTENSIONS[$data['mime']] ?? 'bin';
        $key = $this->prefixFor($songVersion) . Str::uuid() . '.' . $extension;

        return response()->json([
            'url' => $signer->presignPut($key, $data['mime'], 20),
            'key' => $key,
            'max_bytes' => self::MAX_BYTES,
        ]);
    }

    /**
     * Records an upload that finished. Replaces whatever was there before and
     * removes the old object, so a version never keeps two files.
     */
    public function attach(Request $request, SongVersion $songVersion, R2Signer $signer): JsonResponse
    {
        $this->authorizeVersion($songVersion);

        $data = $request->validate([
            'key'  => ['required', 'string', 'max:400'],
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:' . self::MAX_BYTES],
            'mime' => ['required', 'string', Rule::in(self::ALLOWED_MIME)],
        ]);

        // The client returns the key we issued; anything outside this version's
        // own prefix means it was tampered with.
        if (!str_starts_with($data['key'], $this->prefixFor($songVersion))) {
            return response()->json(['message' => 'key_outside_prefix'], 422);
        }

        $previous = $songVersion->audio_path;

        $songVersion->update([
            'audio_path' => $data['key'],
            'audio_name' => $data['name'],
            'audio_size' => $data['size'],
            'audio_mime' => $data['mime'],
        ]);

        if ($previous && $previous !== $data['key']) {
            $signer->delete($previous);
        }

        return response()->json([
            'audio' => $this->serialize($songVersion),
        ]);
    }

    public function destroy(SongVersion $songVersion, R2Signer $signer): JsonResponse
    {
        $this->authorizeVersion($songVersion);

        if ($songVersion->audio_path) {
            $signer->delete($songVersion->audio_path);
        }

        $songVersion->update([
            'audio_path' => null,
            'audio_name' => null,
            'audio_size' => null,
            'audio_mime' => null,
        ]);

        return response()->json(['deleted' => true]);
    }

    /** A fresh playback URL, for a player that has been open a long while. */
    public function play(SongVersion $songVersion): JsonResponse
    {
        abort_unless($songVersion->band_id === $this->bandId(), 403);

        return response()->json(['url' => $songVersion->audioUrl()]);
    }

    private function authorizeVersion(SongVersion $songVersion): void
    {
        $this->requireWrite();

        abort_unless($songVersion->band_id === $this->bandId(), 403);
    }

    /** Band first, so a stray object can always be traced to who owns it. */
    private function prefixFor(SongVersion $songVersion): string
    {
        return "bands/{$songVersion->band_id}/audio/{$songVersion->id}/";
    }

    private function serialize(SongVersion $songVersion): array
    {
        return [
            'name' => $songVersion->audio_name,
            'size' => $songVersion->audio_size,
            'mime' => $songVersion->audio_mime,
            'url'  => $songVersion->audioUrl(),
        ];
    }
}
