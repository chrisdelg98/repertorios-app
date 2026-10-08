<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Concerns\BandAware;
use App\Http\Controllers\Controller;
use App\Http\Requests\Services\StoreServiceAssignmentRequest;
use App\Http\Requests\Services\UpdateServiceAssignmentRequest;
use App\Models\Service;
use App\Services\PushNotifier;
use App\Models\ServiceAssignment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ServiceAssignmentController extends Controller
{
    use BandAware;

    /**
     * Assign several people at once.
     *
     * Adding a band of ten one request at a time is slow and, worse, can stop
     * halfway: six are on the service, four are not, and nothing on screen
     * says which failed. One call either takes the lot or takes none.
     *
     * Anyone already holding the same role on this service is skipped rather
     * than refused — ticking a box for someone who is already there means
     * "they should be on it", and they are.
     */
    public function storeMany(Request $request, Service $service): JsonResponse
    {
        $this->requireAdmin();
        abort_unless($service->band_id === $this->bandId(), 403);

        $data = $request->validate([
            'rows'                   => ['required', 'array', 'min:1', 'max:60'],
            'rows.*.user_id'         => ['required', 'integer', 'exists:users,id'],
            'rows.*.band_role_type_id' => ['required', 'integer', 'exists:band_role_types,id'],
        ]);

        $members = User::whereIn('id', collect($data['rows'])->pluck('user_id')->unique())
            ->get()
            ->filter(fn (User $user) => $user->belongsToBand((int) $service->band_id))
            ->keyBy('id');

        $taken = $service->assignments()
            ->get(['user_id', 'band_role_type_id'])
            ->map(fn ($a) => $a->user_id . ':' . $a->band_role_type_id)
            ->flip();

        $position = (int) $service->assignments()->max('position');
        $created = [];
        $notify = [];

        DB::transaction(function () use ($data, $members, $taken, $service, &$position, &$created, &$notify) {
            foreach ($data['rows'] as $row) {
                $user = $members->get((int) $row['user_id']);
                $roleId = (int) $row['band_role_type_id'];

                if (!$user || $taken->has($user->id . ':' . $roleId)) {
                    continue;
                }

                $assignment = $service->assignments()->create([
                    'band_role_type_id' => $roleId,
                    'user_id'           => $user->id,
                    'manual_name'       => null,
                    'position'          => ++$position,
                ]);

                $assignment->load(['role', 'user']);
                $created[] = $assignment;

                if ($user->id !== Auth::id()) {
                    $notify[] = [$user, $assignment];
                }
            }
        });

        // One notification each, after the response: the admin's screen should
        // never wait on the push service.
        if ($notify) {
            $service->loadMissing('band');

            foreach ($notify as [$user, $assignment]) {
                $role = $assignment->role;

                defer(fn () => app(PushNotifier::class)->toUser($user, $service->band, [
                    'body_key'    => $role ? 'push.assigned' : 'push.assigned_norole',
                    'body_params' => [
                        'role'    => $role?->name_es ?? '',
                        'service' => fn (string $locale) => $service->labelIn($locale),
                    ],
                    'url' => '/services/' . $service->id,
                    'tag' => 'assignment-' . $assignment->id,
                ]));
            }
        }

        return response()->json([
            'assignments' => array_map(fn ($a) => $this->serializeAssignment($a), $created),
        ], 201);
    }

    public function store(StoreServiceAssignmentRequest $request, Service $service): JsonResponse
    {
        $this->requireAdmin();
        abort_unless($service->band_id === $this->bandId(), 403);

        $data = $request->validated();
        $hasUser = !empty($data['user_id']);
        $hasManualName = !empty($data['manual_name']);

        if (($hasUser && $hasManualName) || (!$hasUser && !$hasManualName)) {
            return response()->json(['message' => 'Provide either user_id or manual_name.'], 422);
        }

        if ($hasUser) {
            $user = User::findOrFail((int) $data['user_id']);
            if (!$user->belongsToBand((int) $service->band_id)) {
                return response()->json(['message' => 'Selected user is not in this band.'], 422);
            }

            $alreadyExists = ServiceAssignment::query()
                ->where('service_id', $service->id)
                ->where('user_id', $data['user_id'])
                ->where('band_role_type_id', $data['band_role_type_id'])
                ->exists();

            if ($alreadyExists) {
                return response()->json(['message' => 'Ya esta asignado a ese rol', 'code' => 'dupe'], 409);
            }
        }

        $position = ((int) $service->assignments()->max('position')) + 1;
        $assignment = $service->assignments()->create([
            'band_role_type_id' => (int) $data['band_role_type_id'],
            'user_id' => $hasUser ? (int) $data['user_id'] : null,
            'manual_name' => $hasUser ? null : $data['manual_name'],
            'position' => $position,
        ]);

        $assignment->load(['role', 'user']);

        // Tell the musician they are on. Only for registered users — a guest
        // added by name has no account to notify. Sent after the response so
        // the admin's screen never waits on the push service.
        if ($hasUser && $user && $user->id !== Auth::id()) {
            $service->loadMissing('band');
            $role = $assignment->role;

            defer(fn () => app(PushNotifier::class)->toUser($user, $service->band, [
                'body_key'    => $role ? 'push.assigned' : 'push.assigned_norole',
                'body_params' => [
                    'role'    => $role?->name_es ?? '',
                    'service' => fn (string $locale) => $service->labelIn($locale),
                ],
                'url' => '/services/' . $service->id,
                'tag' => 'assignment-' . $assignment->id,
            ]));
        }

        return response()->json([
            'assignment' => $this->serializeAssignment($assignment),
        ], 201);
    }

    public function update(UpdateServiceAssignmentRequest $request, ServiceAssignment $assignment): JsonResponse
    {
        $this->requireAdmin();
        $assignment->loadMissing('service');
        abort_unless($assignment->service && $assignment->service->band_id === $this->bandId(), 403);

        $data = $request->validated();

        if ($assignment->user_id === null && empty($data['manual_name'])) {
            return response()->json(['message' => 'manual_name is required for manual assignments.'], 422);
        }

        if ($assignment->user_id !== null) {
            $alreadyExists = ServiceAssignment::query()
                ->where('service_id', $assignment->service_id)
                ->where('user_id', $assignment->user_id)
                ->where('band_role_type_id', $data['band_role_type_id'])
                ->where('id', '!=', $assignment->id)
                ->exists();

            if ($alreadyExists) {
                return response()->json(['message' => 'Ya esta asignado a ese rol', 'code' => 'dupe'], 409);
            }
        }

        $assignment->update([
            'band_role_type_id' => (int) $data['band_role_type_id'],
            'manual_name' => $assignment->user_id === null ? ($data['manual_name'] ?? null) : null,
        ]);

        $assignment->load(['role', 'user']);

        return response()->json([
            'assignment' => $this->serializeAssignment($assignment),
        ]);
    }

    public function destroy(ServiceAssignment $assignment): JsonResponse
    {
        $this->requireAdmin();
        $assignment->loadMissing('service');
        abort_unless($assignment->service && $assignment->service->band_id === $this->bandId(), 403);

        $assignment->delete();

        return response()->json(['success' => true]);
    }

    private function serializeAssignment(ServiceAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'service_id' => $assignment->service_id,
            'user_id' => $assignment->user_id,
            'manual_name' => $assignment->manual_name,
            'display_name' => $assignment->display_name,
            'is_manual' => $assignment->is_manual,
            'position' => $assignment->position,
            'band_role_type_id' => $assignment->band_role_type_id,
            'role_name_es' => $assignment->role?->name_es,
            'role_name_en' => $assignment->role?->name_en,
        ];
    }
}
