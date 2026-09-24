<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (user, device). A person who uses the app on their phone and
 * their laptop has two, and a notification goes to both.
 *
 * Deliberately NOT tied to a band: the subscription belongs to the browser,
 * not to a membership. Which band a notification may reach is decided when
 * picking recipients, never here — see PushNotifier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The push service URL the browser gave us. Long: Apple's run past 300 chars.
            $table->string('endpoint', 700);
            $table->string('public_key', 255);   // p256dh
            $table->string('auth_token', 255);   // auth
            $table->string('content_encoding', 20)->default('aesgcm');
            $table->string('user_agent', 255)->nullable();
            // The device's language, captured when it subscribes. The push text
            // is built on the server, but the app's locale lives in the browser,
            // so this is the only way to write in the language the person reads.
            $table->string('locale', 5)->default('es');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // The endpoint identifies the device globally, so re-subscribing
            // updates the row instead of piling up duplicates.
            $table->unique('endpoint', 'push_subscriptions_endpoint_unique');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
