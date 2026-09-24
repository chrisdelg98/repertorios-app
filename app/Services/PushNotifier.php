<?php

namespace App\Services;

use App\Models\Band;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Every push the app sends goes through here.
 *
 * The point of funnelling it is the band boundary: several churches use this
 * app and a notification must never cross from one to another. Recipients are
 * therefore only ever derived from a band's membership — there is no method
 * here that sends to "all users", and a caller cannot pass a recipient list of
 * its own. Membership is checked again at send time, because someone can be
 * removed from a band between choosing recipients and the push going out.
 */
class PushNotifier
{
    /**
     * Notify one person about something in a band, but only if they still
     * belong to it.
     */
    public function toUser(User $user, Band $band, array $payload): int
    {
        if (!$user->belongsToBand($band->id)) {
            return 0;
        }

        return $this->deliver(
            PushSubscription::where('user_id', $user->id)->get(),
            $this->stamp($payload, $band)
        );
    }

    /**
     * Notify everyone in a band. The recipient list is the band's own
     * membership and nothing else.
     */
    public function toBand(Band $band, array $payload, ?int $exceptUserId = null): int
    {
        $memberIds = $band->members()->pluck('users.id');

        if ($exceptUserId) {
            $memberIds = $memberIds->reject(fn ($id) => (int) $id === (int) $exceptUserId);
        }

        if ($memberIds->isEmpty()) {
            return 0;
        }

        return $this->deliver(
            PushSubscription::whereIn('user_id', $memberIds)->get(),
            $this->stamp($payload, $band)
        );
    }

    /** How many devices would receive a notification for this band right now. */
    public function reachableDeviceCount(Band $band, ?int $exceptUserId = null): int
    {
        $memberIds = $band->members()->pluck('users.id')
            ->reject(fn ($id) => $exceptUserId && (int) $id === (int) $exceptUserId);

        return $memberIds->isEmpty()
            ? 0
            : PushSubscription::whereIn('user_id', $memberIds)->count();
    }

    /**
     * The band name goes in the title because someone who plays in two churches
     * needs to know which one is asking before they open anything.
     */
    private function stamp(array $payload, Band $band): array
    {
        return [
            'title' => $band->name,
            'icon'  => asset('icons/icon-192x192.png'),
            'badge' => asset('icons/icon-192x192.png'),
            'url'   => '/dashboard',
            ...$payload,
            'band_id' => $band->id,
        ];
    }

    /**
     * Resolves the body for one device.
     *
     * Callers pass a translation key rather than finished text, because the
     * language the reader wants is a property of their device, not of the
     * server that happens to be sending.
     */
    private function bodyFor(array $payload, string $locale): string
    {
        if (isset($payload['body'])) {
            return (string) $payload['body'];
        }

        if (isset($payload['body_key'])) {
            // A param may be a closure when its text depends on the language
            // too — a formatted date, for instance.
            $params = collect($payload['body_params'] ?? [])
                ->map(fn ($value) => $value instanceof \Closure ? $value($locale) : $value)
                ->all();

            return (string) __($payload['body_key'], $params, $locale);
        }

        return '';
    }

    private function deliver(Collection $subscriptions, array $payload): int
    {
        if ($subscriptions->isEmpty() || !$this->configured()) {
            return 0;
        }

        // A notification is never important enough to break the request that
        // triggered it. Anything thrown along the way — a malformed VAPID pair,
        // a subscription the library rejects, the push service refusing the
        // connection — is logged and swallowed.
        $sent = 0;
        $expired = [];

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject'    => config('services.webpush.subject'),
                    'publicKey'  => config('services.webpush.public_key'),
                    'privateKey' => config('services.webpush.private_key'),
                ],
            ]);

            foreach ($subscriptions as $subscription) {
                $forDevice = $payload;
                $forDevice['body'] = $this->bodyFor($payload, $subscription->locale ?: 'es');
                unset($forDevice['body_key'], $forDevice['body_params']);

                $webPush->queueNotification(
                    Subscription::create($subscription->toSubscriptionArray()),
                    json_encode($forDevice, JSON_UNESCAPED_UNICODE)
                );
            }

            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getEndpoint();

                if ($report->isSuccess()) {
                    $sent++;
                    continue;
                }

                // 404/410 mean the browser threw the subscription away — so do
                // we, otherwise dead endpoints pile up and slow every send.
                if ($report->isSubscriptionExpired()) {
                    $expired[] = $endpoint;
                    continue;
                }

                Log::warning('Push delivery rejected', [
                    'endpoint' => $endpoint,
                    'reason'   => $report->getReason(),
                    'response' => $report->getResponseContent(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Push send failed', [
                'class'   => $e::class,
                'message' => $e->getMessage(),
                'file'    => $e->getFile() . ':' . $e->getLine(),
            ]);

            return 0;
        }

        if ($expired) {
            PushSubscription::whereIn('endpoint', $expired)->delete();
        }

        if ($sent) {
            PushSubscription::whereIn('endpoint', $subscriptions->pluck('endpoint'))
                ->update(['last_used_at' => now()]);
        }

        return $sent;
    }

    /** Whether the VAPID pair is present. Without it nothing can be sent. */
    public function isConfigured(): bool
    {
        return $this->configured();
    }

    private function configured(): bool
    {
        return filled(config('services.webpush.public_key'))
            && filled(config('services.webpush.private_key'));
    }
}
