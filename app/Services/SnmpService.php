<?php

namespace App\Services;

use App\Models\Device;
use App\Models\SnmpMetric;
use RuntimeException;

class SnmpService
{
    public function poll(Device $device): array
    {
        if (! $device->snmp_enabled) {
            return ['status' => 'disabled', 'metrics' => []];
        }
        $version = $device->snmp_version === '1' ? '1' : '2c';
        if (($version === '1' && ! function_exists('snmpget')) || ($version === '2c' && ! function_exists('snmp2_get'))) {
            throw new RuntimeException('Ekstensi PHP SNMP belum aktif.');
        }

        $device->syncSnmpSensors();
        $sensors = $device->snmpSensors()->where('is_active', true)->get()->keyBy('oid');

        $metrics = [];
        $timeoutMicroseconds = max(100000, (int) $device->snmp_timeout_ms * 1000);
        $previousMetrics = $device->snmpMetrics()
            ->select('oid', 'raw_value', 'polled_at')
            ->whereIn('oid', collect($device->snmp_oids ?? [])->pluck('oid')->filter()->all())
            ->orderByDesc('polled_at')->get()->unique('oid')->keyBy('oid');
        foreach ($device->snmp_oids ?? [] as $item) {
            if (($item['sensor_type'] ?? null) === 'ping' || strtolower($item['label'] ?? '') === 'ping') continue;
            $oid = trim($item['oid'] ?? '');
            if ($oid === '') continue;
            $label = trim($item['label'] ?? $oid);
            $arguments = [$device->ip_address.':'.$device->snmp_port, $device->snmp_community, $oid, $timeoutMicroseconds, 1];
            $raw = $version === '1' ? @snmpget(...$arguments) : @snmp2_get(...$arguments);
            if ($raw === false) continue;

            [$type, $value] = $this->normalize($raw);
            $numericValue = is_numeric($value) ? (float) $value : null;
            $valueType = $item['value_type'] ?? 'gauge';
            $multiplier = (float) ($item['multiplier'] ?? 1);
            $divisor = (float) ($item['divisor'] ?? 1);
            if ($divisor == 0.0) $divisor = 1.0;

            if ($numericValue !== null && $valueType === 'delta') {
                $previous = $previousMetrics->get($oid);
                if ($previous && is_numeric($previous->raw_value)) {
                    $elapsedSeconds = max(1, $previous->polled_at->diffInSeconds(now()));
                    $difference = $numericValue - (float) $previous->raw_value;
                    $numericValue = $difference >= 0 ? $difference / $elapsedSeconds : null;
                } else {
                    $numericValue = null;
                }
            }
            if ($numericValue !== null) $numericValue = ($numericValue * $multiplier) / $divisor;

            $metric = SnmpMetric::create([
                'device_id' => $device->id,
                'sensor_id' => $sensors->get($oid)?->id,
                'oid' => $oid,
                'label' => $label,
                'numeric_value' => $numericValue,
                'raw_value' => (string) $value,
                'data_type' => strtoupper($valueType).' / '.$type,
                'sample_status' => $numericValue === null ? 'invalid' : 'ok',
                'polled_at' => now(),
            ]);
            $metrics[] = $metric;
        }

        $status = count($metrics) ? 'online' : 'timeout';
        $device->update(['snmp_status' => $status, 'snmp_last_polled_at' => now()]);
        return compact('status', 'metrics');
    }

    private function normalize(string $raw): array
    {
        if (preg_match('/^([^:]+):\s*(.*)$/s', trim($raw), $matches)) {
            $type = trim($matches[1]);
            $value = trim($matches[2], " \t\n\r\0\x0B\"");
            if (preg_match('/(-?\d+(?:\.\d+)?)/', $value, $number)) {
                return [$type, $number[1]];
            }
            return [$type, $value];
        }
        return ['VALUE', trim($raw)];
    }
}
