<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('message')->nullable();
            $table->timestampTz('checked_at')->index();
            $table->index(['device_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_checks');
    }
};
