<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('band_role_type_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('manual_name', 50)->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['service_id', 'user_id', 'band_role_type_id'], 'svc_user_role_unique');
            $table->index(['service_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_assignments');
    }
};
