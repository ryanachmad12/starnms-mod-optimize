<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->ipAddress('ip_address')->unique();
            $table->string('mac_address', 32)->nullable();
            $table->string('type', 80)->default('INTRACOM BS');
            $table->string('region', 40)->index();
            $table->string('city', 80)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->unsignedSmallInteger('monitor_port')->default(80);
            $table->boolean('is_active')->default(true);
            $table->string('status', 16)->default('unknown')->index();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
