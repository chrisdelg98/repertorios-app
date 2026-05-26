<?php

namespace App\Actions\Services;

use App\Models\Service;
use App\Models\SharedLink;
use Illuminate\Support\Str;

class GenerateShareLink
{
    public function execute(Service $service): SharedLink
    {
        return SharedLink::firstOrCreate(
            ['service_id' => $service->id],
            ['token' => Str::random(16)],
        );
    }
}
