<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bands', function (Blueprint $table) {
            $table->foreignId('creator_id')->nullable()->after('id')
                ->constrained('users')->nullOnDelete();
        });

        // Back-fill: existing bands get their first admin user as creator
        DB::table('bands')->whereNull('creator_id')->orderBy('id')->each(function ($band) {
            $first = DB::table('users')
                ->where('band_id', $band->id)
                ->where('role', 'admin')
                ->orderBy('id')
                ->first();
            if ($first) {
                DB::table('bands')->where('id', $band->id)->update(['creator_id' => $first->id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('bands', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creator_id');
        });
    }
};
