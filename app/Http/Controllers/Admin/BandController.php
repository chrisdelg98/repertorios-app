<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Band;
use App\Models\Service;
use App\Models\Song;
use App\Models\SongVersion;
use App\Models\User;
use App\Services\BandAudioQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every band on the platform, for support.
 *
 * Deliberately without BandAware: the whole app filters by the band you are
 * standing in, and this does the opposite. Inheriting that trait here is how a
 * panel query would one day find its way into a member's screen.
 *
 * Counts and metadata only. Knowing a band has forty songs is support; reading
 * which forty is their business, not ours.
 */
class BandController extends Controller
{
    private const PER_PAGE = 30;

    public function __construct(private readonly BandAudioQuota $quota)
    {
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));

        $bands = Band::query()
            ->when($search !== '', fn ($q) => $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            }))
            ->withCount('members')
            ->with('creator:id,name,email')
            ->addSelect(['storage_bytes' => SongVersion::selectRaw('COALESCE(SUM(audio_size), 0)')
                ->whereColumn('song_versions.band_id', 'bands.id'),
            ])
            // The freshest sign of life among the people in it, which is the
            // figure that decides whether their files are still wanted.
            ->addSelect(['team_last_seen_at' => User::query()
                ->select('users.last_seen_at')
                ->join('band_user', 'band_user.user_id', '=', 'users.id')
                ->whereColumn('band_user.band_id', 'bands.id')
                ->orderByDesc('users.last_seen_at')
                ->limit(1),
            ])
            ->orderByDesc('storage_bytes')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Band $band) => $this->row($band));

        return Inertia::render('Admin/Bands/Index', [
            'bands'   => $bands,
            'filters' => ['q' => $search],
            'default_quota_mb' => (int) config('audio.band_quota_mb'),
        ]);
    }

    public function show(Band $band): Response
    {
        $band->loadCount('members')->load('creator:id,name,email');

        return Inertia::render('Admin/Bands/Show', [
            'band' => array_merge($this->row($band), [
                'created_at'     => $band->created_at?->toIso8601String(),
                'audio_quota_mb' => $band->audio_quota_mb,
                'songs'          => Song::where('band_id', $band->id)->count(),
                'services'       => Service::where('band_id', $band->id)->services()->count(),
                'entries'        => Service::where('band_id', $band->id)->calendarOnly()->count(),
                'tracks'         => SongVersion::where('band_id', $band->id)->whereNotNull('audio_path')->count(),
            ]),

            // Names and roles, not what anybody plays.
            'members' => $band->members()
                ->orderBy('users.name')
                ->get(['users.id', 'users.name', 'users.email', 'users.last_seen_at'])
                ->map(fn (User $user) => [
                    'id'           => $user->id,
                    'name'         => $user->name,
                    'email'        => $user->email,
                    'role'         => $user->pivot->role,
                    'last_seen_at' => $user->last_seen_at?->toIso8601String(),
                ]),

            'default_quota_mb' => (int) config('audio.band_quota_mb'),

            'history' => AdminAction::where('band_id', $band->id)
                ->with('user:id,name')
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (AdminAction $entry) => [
                    'id'         => $entry->id,
                    'action'     => $entry->action,
                    'by'         => $entry->user?->name,
                    'context'    => $entry->context,
                    'created_at' => $entry->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * The one control in this panel that writes anything.
     *
     * Clearing it returns the band to the default, which is why an empty value
     * is accepted rather than refused.
     */
    public function updateQuota(Request $request, Band $band): RedirectResponse
    {
        $data = $request->validate([
            'audio_quota_mb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
        ]);

        $before = $band->audio_quota_mb;
        $after = $data['audio_quota_mb'] ?? null;

        if ($before === $after) {
            return back();
        }

        $band->update(['audio_quota_mb' => $after]);

        AdminAction::record(AdminAction::QUOTA_CHANGED, $band->id, [
            'from' => $before,
            'to'   => $after,
            'default_mb' => (int) config('audio.band_quota_mb'),
        ]);

        return back()->with('success', true);
    }

    /** The shape every band takes on both screens. */
    private function row(Band $band): array
    {
        $used = (int) ($band->storage_bytes ?? $this->quota->usedBytes($band->id));
        $quota = $this->quota->quotaBytes($band->id);

        return [
            'id'      => $band->id,
            'name'    => $band->name,
            'code'    => $band->code,
            'creator' => $band->creator?->name,
            'members' => $band->members_count,

            'storage_bytes' => $used,
            'quota_bytes'   => $quota,
            'percent'       => $quota > 0 ? min(100, (int) round($used / $quota * 100)) : 0,
            'has_override'  => $band->audio_quota_mb !== null,

            'team_last_seen_at' => $this->lastSeen($band),
        ];
    }

    private function lastSeen(Band $band): ?string
    {
        $raw = $band->team_last_seen_at ?? DB::table('users')
            ->join('band_user', 'band_user.user_id', '=', 'users.id')
            ->where('band_user.band_id', $band->id)
            ->max('users.last_seen_at');

        return $raw ? \Illuminate\Support\Carbon::parse($raw)->toIso8601String() : null;
    }
}
