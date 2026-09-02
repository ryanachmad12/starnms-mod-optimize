<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('snmp_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('oid', 160);
            $table->string('label', 100);
            $table->double('numeric_value')->nullable();
            $table->text('raw_value')->nullable();
            $table->string('data_type', 30)->nullable();
            $table->timestampTz('polled_at')->index();
            $table->index(['device_id', 'oid', 'polled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snmp_metrics');
    }
};
