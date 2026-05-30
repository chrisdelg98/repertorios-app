<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('band_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_uuid', 36);
            $table->timestamp('first_seen')->useCurrent();
            $table->timestamp('last_seen')->useCurrent();
            $table->unique(['band_id', 'visitor_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('band_visits');
    }
};
