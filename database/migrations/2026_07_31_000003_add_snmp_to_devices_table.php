<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('snmp_enabled')->default(false);
            $table->string('snmp_version', 8)->default('2c');
            $table->string('snmp_community')->default('public');
            $table->unsignedSmallInteger('snmp_port')->default(161);
            $table->unsignedSmallInteger('snmp_timeout_ms')->default(1200);
            $table->jsonb('snmp_oids')->nullable();
            $table->timestampTz('snmp_last_polled_at')->nullable();
            $table->string('snmp_status', 16)->default('disabled');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn([
                'snmp_enabled', 'snmp_version', 'snmp_community', 'snmp_port',
                'snmp_timeout_ms', 'snmp_oids', 'snmp_last_polled_at', 'snmp_status',
            ]);
        });
    }
};
