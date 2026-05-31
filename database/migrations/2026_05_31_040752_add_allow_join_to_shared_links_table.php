<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_links', function (Blueprint $table) {
            $table->boolean('allow_join')->default(false)->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('shared_links', function (Blueprint $table) {
            $table->dropColumn('allow_join');
        });
    }
};
