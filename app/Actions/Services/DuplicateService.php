<?php

namespace App\Actions\Services;

use App\Models\Service;

class DuplicateService
{
    public function execute(Service $service, string $newDate): Service
    {
        $service->loadMissing('assignments');

        $copy = $service->replicate(['created_at', 'updated_at']);
        $copy->date = $newDate;
        $copy->save();

        foreach ($service->serviceSongs()->orderBy('position')->get() as $ss) {
            $copy->serviceSongs()->create([
                'song_version_id' => $ss->song_version_id,
                'position' => $ss->position,
                'notes' => $ss->notes,
            ]);
        }

        foreach ($service->assignments as $assignment) {
            $copy->assignments()->create([
                'band_role_type_id' => $assignment->band_role_type_id,
                'user_id' => $assignment->user_id,
                'manual_name' => $assignment->manual_name,
                'position' => $assignment->position,
            ]);
        }

        return $copy;
    }
}
