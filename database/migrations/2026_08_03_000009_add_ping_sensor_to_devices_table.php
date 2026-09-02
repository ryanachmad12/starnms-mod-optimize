<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('ping_enabled')->default(false);
            $table->unsignedInteger('ping_timeout_ms')->default(5000);
            $table->unsignedSmallInteger('ping_packet_size')->default(32);
            $table->unsignedSmallInteger('ping_count')->default(5);
            $table->unsignedInteger('ping_delay_ms')->default(5);
            $table->string('ping_method', 16)->default('multiple');
            $table->boolean('ping_auto_ack')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['ping_enabled', 'ping_timeout_ms', 'ping_packet_size', 'ping_count', 'ping_delay_ms', 'ping_method', 'ping_auto_ack']);
        });
    }
};
