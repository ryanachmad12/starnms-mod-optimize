<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceCheck;
use App\Models\MonitoringBatch;
use App\Models\SnmpMetric;
use App\Services\PingService;
use App\Jobs\PollDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MonitoringController extends Controller
{
    public function check(Device $device, PingService $ping)
    {
        $lock = Cache::lock('device:icmp:'.$device->id, 30);
        if (! $lock->get()) {
            return response()->json(['status' => 'busy', 'message' => 'Perangkat sedang diproses oleh worker lain.'], 409);
        }
        $pingSensor = collect($device->snmp_oids ?? [])->first(fn ($sensor) => ($sensor['sensor_type'] ?? null) === 'ping' || strtolower($sensor['label'] ?? '') === 'ping');
        // A manual/project check refreshes inventory status only. It must not
        // inject extra samples into the one-second telemetry polling stream.
        try {
            $result = $this->persist($device, $ping->check($device, $pingSensor), false);
            return response()->json($result);
        } finally {
            optional($lock)->release();
        }
    }

    public function checkAll(Request $request)
    {
        $data = $request->validate(['project_id' => ['nullable', 'integer', 'exists:projects,id']]);
        // A manual batch is an explicit operator action. Unlike the scheduler,
        // it can check active legacy devices that have not opted into recurring ICMP.
        $query = Device::where('is_active', true);
        if (! empty($data['project_id'])) $query->where('project_id', $data['project_id']);

        $deviceIds = $query->pluck('id');
        abort_if($deviceIds->isEmpty(), 422, 'Tidak ada perangkat aktif pada cakupan project ini.');

        $batch = MonitoringBatch::create([
            'requested_by' => $request->user()->id,
            'project_id' => $data['project_id'] ?? null,
            'total_devices' => $deviceIds->count(),
            'status' => 'queued',
        ]);

        foreach ($deviceIds as $deviceId) PollDevice::dispatch($deviceId, $batch->id, true)->onQueue('monitoring');

        return response()->json([
            'id' => $batch->id,
            'status' => $batch->status,
            'total_devices' => $batch->total_devices,
            'message' => 'Pemeriksaan perangkat dimasukkan ke antrean monitoring.',
        ], 202);
    }

    public function batch(MonitoringBatch $batch)
    {
        abort_unless($batch->requested_by === request()->user()->id || request()->user()->isAdministrator(), 403);

        return response()->json($batch->only('id', 'total_devices', 'completed_devices', 'failed_devices', 'status', 'completed_at'))->header('Cache-Control', 'no-store, private');
    }

    public function status(Request $request)
    {
        $data = $request->validate(['since' => ['nullable', 'date']]);
        $query = Device::select('id', 'project_id', 'status', 'latency_ms', 'last_checked_at', 'updated_at')->orderBy('id');
        if (! empty($data['since'])) $query->where('updated_at', '>', $data['since']);

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'devices' => $query->get()->map(fn (Device $device) => [
                'id' => $device->id,
                'project_id' => $device->project_id,
                'status' => $device->status,
                'latency_ms' => $device->latency_ms,
                // Send an explicit UTC instant. Browser-side rendering then
                // cannot be affected by server/database display timezones.
                'last_checked_at' => $device->last_checked_at?->utc()->toIso8601String(),
                'updated_at' => $device->updated_at?->utc()->toIso8601String(),
            ]),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function persist(Device $device, array $result, bool $recordPingMetric = true): array
    {
        $now = now();
        $device->update([
            'status' => $result['status'],
            'latency_ms' => $result['latency_ms'],
            'last_checked_at' => $now,
            'last_seen_at' => $result['status'] === 'online' ? $now : $device->last_seen_at,
        ]);
        DeviceCheck::create([
            'device_id' => $device->id,
            ...$result,
            'checked_at' => $now,
        ]);
        $pingSensor = collect($device->snmp_oids ?? [])->first(fn ($sensor) => ($sensor['sensor_type'] ?? null) === 'ping' || strtolower($sensor['label'] ?? '') === 'ping');
        if ($recordPingMetric && $device->snmp_enabled && $pingSensor && str_starts_with($result['message'] ?? '', 'ICMP Ping')) {
            $device->syncSnmpSensors();
            $sensor = $device->snmpSensors()->where('oid', 'plugin:ping')->first();
            $timedOut = $result['latency_ms'] === null;
            SnmpMetric::create([
                'device_id' => $device->id,
                'sensor_id' => $sensor?->id,
                'oid' => 'plugin:ping',
                'label' => 'Ping',
                'numeric_value' => $result['latency_ms'],
                'raw_value' => $result['message'],
                'data_type' => $timedOut ? 'PING TIMEOUT' : 'BUILT-IN PING',
                'sample_status' => $timedOut ? 'timeout' : 'ok',
                'polled_at' => $now,
            ]);
        }
        return ['id' => $device->id, ...$result, 'last_checked_at' => $now->toIso8601String()];
    }
}
