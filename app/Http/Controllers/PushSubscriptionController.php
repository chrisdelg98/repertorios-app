<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
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
