<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('sla_enabled')->default(false)->index();
            $table->timestampTz('sla_activated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devices', fn (Blueprint $table) => $table->dropColumn(['sla_enabled', 'sla_activated_at']));
    }
};
