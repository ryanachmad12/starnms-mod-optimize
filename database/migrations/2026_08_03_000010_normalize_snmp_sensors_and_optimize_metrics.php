<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('snmp_sensors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('sensor_type', 20)->default('snmp');
            $table->string('label', 120);
            $table->string('oid', 255);
            $table->string('unit', 30)->nullable();
            $table->string('value_type', 20)->default('gauge');
            $table->jsonb('configuration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->unique(['device_id', 'oid']);
            $table->index(['device_id', 'is_active']);
        });

        Schema::table('snmp_metrics', function (Blueprint $table) {
            $table->foreignId('sensor_id')->nullable()->after('device_id')->constrained('snmp_sensors')->nullOnDelete();
            $table->string('sample_status', 20)->default('ok')->after('data_type');
            $table->index(['sensor_id', 'polled_at']);
        });

        foreach (DB::table('devices')->select('id', 'snmp_oids')->get() as $device) {
            $items = is_string($device->snmp_oids) ? json_decode($device->snmp_oids, true) : (array) $device->snmp_oids;
            foreach ($items ?: [] as $item) {
                if (empty($item['oid'])) continue;
                $sensorId = DB::table('snmp_sensors')->insertGetId([
                    'device_id' => $device->id, 'sensor_type' => $item['sensor_type'] ?? 'snmp',
                    'label' => $item['label'] ?? $item['oid'], 'oid' => $item['oid'], 'unit' => $item['unit'] ?? null,
                    'value_type' => $item['value_type'] ?? 'gauge', 'configuration' => json_encode($item),
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('snmp_metrics')->where('device_id', $device->id)->where('oid', $item['oid'])->update(['sensor_id' => $sensorId]);
                if ($item['oid'] === 'plugin:ping') {
                    $timeout = (float) ($item['ping_timeout_ms'] ?? 5000);
                    DB::table('snmp_metrics')->where('device_id', $device->id)->where('oid', 'plugin:ping')->whereNull('numeric_value')->update(['numeric_value' => $timeout, 'sample_status' => 'timeout']);
                }
            }
        }

        DB::statement('CREATE INDEX snmp_metrics_polled_at_brin ON snmp_metrics USING BRIN (polled_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS snmp_metrics_polled_at_brin');
        Schema::table('snmp_metrics', function (Blueprint $table) {
            $table->dropForeign(['sensor_id']); $table->dropIndex(['sensor_id', 'polled_at']);
            $table->dropColumn(['sensor_id', 'sample_status']);
        });
        Schema::dropIfExists('snmp_sensors');
    }
};
