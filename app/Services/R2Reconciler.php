<?php

namespace App\Services;

use App\Models\Band;
use App\Models\SongVersion;
use Illuminate\Support\Collection;

/**
 * What the database believes against what the bucket holds.
 *
 * Every figure the app shows about storage is summed from `audio_size`, so it
 * only knows about uploads it was told about. Two kinds of disagreement are
 * possible and they cost different things:
 *
 *   Orphans — in the bucket, in no row. Paid for every month, reachable by
 *   nobody, and counted against no quota. These are the ones that cost money.
 *
 *   Missing — a row pointing at a key that is not there. Nothing is being
 *   paid, but a musician gets a broken player and no explanation.
 *
 * Read-only on purpose: it reports, it never deletes. Deciding what a stray
 * file means is a judgement, and this is a tool for making that judgement,
 * not for acting on it.
 */
class R2Reconciler
{
    /** Where every key this app writes begins. Anything else is not ours. */
    private const PREFIX = 'bands/';

    /** A thousand keys per request, so this caps the walk at fifty thousand. */
    private const MAX_PAGES = 50;

    public function __construct(private readonly R2Signer $signer)
    {
    }

    public function run(): array
    {
        if (!$this->signer->isConfigured()) {
            return ['configured' => false];
        }

        $objects = $this->walk();

        if ($objects === null) {
            return ['configured' => true, 'failed' => true];
        }

        $known = SongVersion::query()
            ->whereNotNull('audio_path')
            ->get(['id', 'band_id', 'audio_path', 'audio_size', 'audio_name'])
            ->keyBy('audio_path');

        $orphans = $objects
            ->reject(fn (array $object) => $known->has($object['key']))
            ->map(fn (array $object) => $object + ['band_id' => $this->bandIdFrom($object['key'])])
            ->sortByDesc('size')
            ->values();

        $missing = $known
            ->reject(fn ($version) => $objects->has($version->audio_path))
            ->map(fn ($version) => [
                'id'      => $version->id,
                'band_id' => $version->band_id,
                'key'     => $version->audio_path,
                'name'    => $version->audio_name,
                'size'    => (int) $version->audio_size,
            ])
            ->values();

        $bandNames = Band::whereIn('id', $orphans->pluck('band_id')->merge($missing->pluck('band_id'))->filter()->unique())
            ->pluck('name', 'id');

        $name = fn (?int $id) => $id ? ($bandNames[$id] ?? null) : null;

        return [
            'configured'   => true,
            'failed'       => false,
            'scanned_at'   => now()->toIso8601String(),

            'bucket_objects' => $objects->count(),
            'bucket_bytes'   => (int) $objects->sum('size'),
            'database_rows'  => $known->count(),
            'database_bytes' => (int) $known->sum('audio_size'),

            'orphans' => $orphans->take(100)
                ->map(fn (array $row) => $row + ['band' => $name($row['band_id'])])
                ->all(),
            'orphan_count' => $orphans->count(),
            'orphan_bytes' => (int) $orphans->sum('size'),

            'missing' => $missing->take(100)
                ->map(fn (array $row) => $row + ['band' => $name($row['band_id'])])
                ->all(),
            'missing_count' => $missing->count(),

            // A row whose size never matched the file it points at. Rare, and
            // the only one of the three that silently skews every quota.
            'mismatched' => $known
                ->filter(fn ($v) => $objects->has($v->audio_path)
                    && (int) $objects[$v->audio_path]['size'] !== (int) $v->audio_size)
                ->take(50)
                ->map(fn ($v) => [
                    'id'       => $v->id,
                    'band'     => $name($v->band_id),
                    'key'      => $v->audio_path,
                    'db_size'  => (int) $v->audio_size,
                    'real_size' => (int) $objects[$v->audio_path]['size'],
                ])
                ->values()
                ->all(),
        ];
    }

    /** Every page of the prefix, or null if the bucket stopped answering. */
    private function walk(): ?Collection
    {
        $all = collect();
        $token = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $result = $this->signer->listObjects(self::PREFIX, $token);

            foreach ($result['objects'] as $object) {
                $all->put($object['key'], $object);
            }

            $token = $result['next'];

            if (!$token) {
                return $all;
            }
        }

        // Stopping at the cap and reporting is honest; pretending the walk
        // finished would turn every unseen key into a false "missing".
        return null;
    }

    /** `bands/{id}/audio/...` — the band is the second segment. */
    private function bandIdFrom(string $key): ?int
    {
        $parts = explode('/', $key);

        return isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : null;
    }
}
