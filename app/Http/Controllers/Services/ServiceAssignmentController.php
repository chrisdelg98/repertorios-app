<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Concerns\BandAware;
use App\Http\Controllers\Controller;
use App\Http\Requests\Services\StoreServiceAssignmentRequest;
use App\Http\Requests\Services\UpdateServiceAssignmentRequest;
use App\Models\Service;
use App\Models\ServiceAssignment;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ServiceAssignmentController extends Controller
{
    use BandAware;

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
            if ((int) $user->band_id !== (int) $service->band_id) {
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
