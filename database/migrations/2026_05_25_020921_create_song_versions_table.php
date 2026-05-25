<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('song_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->foreignId('band_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Original');
            $table->string('key', 10)->nullable();
            $table->unsignedSmallInteger('bpm')->nullable();
            $table->text('notes')->nullable();
            $table->string('youtube_url')->nullable();
            $table->timestamps();

            $table->unique(['song_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('song_versions');
    }
};
