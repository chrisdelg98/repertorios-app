<?php

namespace App\Actions\Services;

use App\Models\Service;

class DuplicateService
{
    public function execute(Service $service, string $newDate): Service
    {
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

        return $copy;
    }
}
