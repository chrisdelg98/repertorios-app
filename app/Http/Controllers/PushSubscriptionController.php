<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * Register this browser to receive notifications.
     *
     * The endpoint is unique across the table, so a browser that re-subscribes
     * (new keys after a permission reset, say) updates its row instead of
     * leaving a dead one behind. It is also re-pointed at whoever is logged in
     * now, which matters on a shared device.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint'         => ['required', 'string', 'max:700', 'url'],
            'keys.p256dh'      => ['required', 'string', 'max:255'],
            'keys.auth'        => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'max:20'],
            'locale'           => ['nullable', 'string', 'in:es,en'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $subscription = PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id'          => $user->id,
                'public_key'       => $data['keys']['p256dh'],
                'auth_token'       => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aesgcm',
                'user_agent'       => substr((string) $request->userAgent(), 0, 255),
                'locale'           => $data['locale'] ?? 'es',
                'last_used_at'     => now(),
            ]
        );

        return response()->json(['id' => $subscription->id], 201);
    }

    /**
     * Send a notification to the user's own devices.
     *
     * Real notifications deliberately skip whoever caused them, so with a
     * single account there is otherwise no way to check that a phone is set up
     * correctly. It also answers the first question when someone reports that
     * nothing reaches them: is it their device, or is it the sending?
     */
    public function test(Request $request, PushNotifier $notifier): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $devices = $user->pushSubscriptions()->count();
        $band = $user->activeBand;

        // Each failure needs a different fix, so say which one it is rather
        // than reporting a bare zero and leaving the person to guess.
        $reason = match (true) {
            !$notifier->isConfigured() => 'not_configured',  // VAPID keys missing on the server
            $devices === 0             => 'no_devices',      // this browser never registered
            !$band                     => 'no_band',
            default                    => null,
        };

        if ($reason) {
            return response()->json(['sent' => 0, 'devices' => $devices, 'reason' => $reason]);
        }

        $sent = $notifier->toUser($user, $band, [
            'body_key' => 'push.test',
            'url'      => '/settings',
            'tag'      => 'push-test',
        ]);

        return response()->json([
            'sent'    => $sent,
            'devices' => $devices,
            // Zero here means the push services rejected every device; the
            // reason per endpoint is in the log.
            'reason'  => $sent > 0 ? 'ok' : 'rejected',
        ]);
    }

    /** Unsubscribe this browser. */
    public function destroy(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:700'],
        ]);

        PushSubscription::where('endpoint', $data['endpoint'])
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(['deleted' => true]);
    }

    /** Remove one of the user's other devices, from the settings list. */
    public function destroyDevice(Request $request, PushSubscription $pushSubscription): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        abort_unless($pushSubscription->user_id === $user->id, 403);

        $pushSubscription->delete();

        return response()->json(['deleted' => true]);
    }
}
