<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('band_role_types', function (Blueprint $table) {
            $table->id();
            $table->string('name_es', 50);
            $table->string('name_en', 50);
            $table->unsignedTinyInteger('sort_order')->default(100);
            $table->timestamps();
            $table->unique('name_es');
        });

        Schema::create('user_band_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('band_role_type_id')->constrained('band_role_types')->cascadeOnDelete();
            $table->primary(['user_id', 'band_role_type_id']);
        });

        // Seed common worship-band roles. Lower sort_order = appears first.
        $roles = [
            // Most common (10-19)
            ['Voz principal',      'Lead vocal',          10],
            ['Coros',              'Backing vocals',      11],
            ['Líder de alabanza',  'Worship leader',      12],
            ['Guitarra acústica',  'Acoustic guitar',     13],
            ['Guitarra eléctrica', 'Electric guitar',     14],
            ['Bajo',               'Bass',                15],
            ['Teclado',            'Keyboard',            16],
            ['Batería',            'Drums',               17],
            ['Sonidista',          'Sound engineer',      18],
            // Common (20-29)
            ['Piano',              'Piano',               20],
            ['Cajón',              'Cajón',               21],
            ['Percusión',          'Percussion',          22],
            ['Director musical',   'Music director',      23],
            ['Sintetizador',       'Synthesizer',         24],
            ['Streaming',          'Live streaming',      25],
            ['Cámara',             'Camera',              26],
            // Less common (30+)
            ['Violín',             'Violin',              30],
            ['Saxofón',            'Saxophone',           31],
            ['Trompeta',           'Trumpet',             32],
            ['Flauta',             'Flute',               33],
            ['Cuerdas',            'Strings',             34],
            ['Acordeón',           'Accordion',           35],
            ['Iluminación',        'Lighting',            36],
            ['Apoyo técnico',      'Tech support',        37],
        ];

        $now = now();
        DB::table('band_role_types')->insert(array_map(fn ($r) => [
            'name_es'    => $r[0],
            'name_en'    => $r[1],
            'sort_order' => $r[2],
            'created_at' => $now,
            'updated_at' => $now,
        ], $roles));
    }

    public function down(): void
    {
        Schema::dropIfExists('user_band_roles');
        Schema::dropIfExists('band_role_types');
    }
};
