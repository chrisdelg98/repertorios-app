<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a platform administrator did, and to whom.
 *
 * The panel is read-only but for one control, so this starts nearly empty —
 * quota changes and sign-ins. It exists now rather than later because the day
 * deletion arrives is the day a record of what was destroyed becomes the only
 * way to answer for it, and a log added after the fact knows nothing of what
 * came before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 40);
            $table->foreignId('band_id')->nullable()->constrained()->nullOnDelete();

            // Free-form so a new action does not need a new column: for a
            // quota change, the megabytes before and after.
            $table->json('context')->nullable();

            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index(['band_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
    }
};
