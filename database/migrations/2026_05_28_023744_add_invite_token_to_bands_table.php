<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bands', function (Blueprint $table) {
            $table->string('invite_token', 32)->nullable()->unique()->after('code');
        });

        DB::table('bands')->whereNull('invite_token')->orderBy('id')->each(function ($band) {
            DB::table('bands')->where('id', $band->id)->update([
                'invite_token' => Str::random(32),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('bands', function (Blueprint $table) {
            $table->dropColumn('invite_token');
        });
    }
};
