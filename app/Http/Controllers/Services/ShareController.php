<?php

namespace App\Http\Controllers\Services;

use App\Actions\Services\GenerateShareLink;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BandAware;
use App\Models\SharedLink;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ShareController extends Controller
{
    use BandAware;

    public function store(Service $service, GenerateShareLink $action): JsonResponse
    {
        abort_unless($service->band_id === $this->bandId(), 403);

        $link = $action->execute($service);
        $service->load('serviceSongs.songVersion.song');

        $shareUrl = route('share.show', $link->token);

        return response()->json([
            'url'           => $shareUrl,
            'whatsapp_url'  => 'https://wa.me/?text=' . rawurlencode($this->buildText($service, $shareUrl)),
        ]);
    }

    public function show(string $token): Response
    {
        $link = SharedLink::where('token', $token)->firstOrFail();

        if ($link->isExpired()) {
            return Inertia::render('Public/Repertoire', ['expired' => true, 'service' => null]);
        }

        $link->load('service.serviceSongs.songVersion.song');
        $service = $link->service;

        return Inertia::render('Public/Repertoire', [
            'expired' => false,
            'service' => [
                'type'  => $service->type,
                'date'  => $service->date->toDateString(),
                'time'  => $service->time,
                'notes' => $service->notes,
                'songs' => $service->serviceSongs->map(fn ($ss) => [
                    'name'        => $ss->songVersion->song->name,
                    'artist'      => $ss->songVersion->song->artist ?? '',
                    'version'     => $ss->songVersion->name,
                    'key'         => $ss->songVersion->key,
                    'bpm'         => $ss->songVersion->bpm,
                    'notes'       => $ss->songVersion->notes,
                    'youtube_url' => $ss->songVersion->youtube_url,
                ])->values(),
            ],
        ]);
    }

    private function buildText(Service $service, string $url): string
    {
        $type = $service->type === 'other' ? 'Servicio' : $service->type;
        $date = Carbon::parse($service->date)->format('d/m/Y');
        $time = $service->time ? ' · ' . substr($service->time, 0, 5) : '';

        $lines = [$type, $date . $time, ''];

        foreach ($service->serviceSongs->sortBy('position') as $i => $ss) {
            $name   = $ss->songVersion->song->name;
            $artist = $ss->songVersion->song->artist ? ' (' . $ss->songVersion->song->artist . ')' : '';
            $key    = $ss->songVersion->key ? ' — ' . $ss->songVersion->key : '';
            $lines[] = ($i + 1) . '. ' . $name . $artist . $key;
        }

        $lines[] = '';
        $lines[] = $url;

        return implode("\n", $lines);
    }
}
