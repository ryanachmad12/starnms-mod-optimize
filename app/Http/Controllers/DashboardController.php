<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceCheck;
use App\Models\SnmpMetric;
use App\Models\Project;
use App\Services\SlaService;
use App\Services\IndonesiaMapService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(SlaService $sla)
    {
        $devices = Device::with('project')->orderBy('region')->orderBy('name')->get();
        $projects = Project::where('is_active', true)->orderBy('name')->get();
        $topologyProjects = Project::orderBy('name')->get();
        $sla->applyToDevices($devices);
        $stats = [
            'total' => $devices->count(),
            'online' => $devices->where('status', 'online')->count(),
            'offline' => $devices->where('status', 'offline')->count(),
            'unknown' => $devices->where('status', 'unknown')->count(),
            'regions' => $devices->pluck('region')->filter()->unique()->count(),
        ];

        $alerts = $this->activeAlerts($devices);

        $projectMap = $this->projectMap($topologyProjects, $devices);

        return view('dashboard', compact('devices', 'projects', 'topologyProjects', 'projectMap', 'stats', 'alerts'));
    }

    public function alerts(SlaService $sla)
    {
        $devices = Device::orderBy('name')->get();
        $alerts = $this->activeAlerts($devices);

        return response()->json(['alerts' => $alerts->map(fn ($alert) => [
            'key' => $alert['key'],
            'device' => $alert['device'],
            'sensor' => $alert['sensor'],
            'value' => $alert['value'],
            'unit' => $alert['unit'],
            'time' => Carbon::parse($alert['time'])->toIso8601String(),
            'time_human' => Carbon::parse($alert['time'])->diffForHumans(),
            'speech' => $alert['speech'],
        ])->values()]);
    }

    private function activeAlerts($devices)
    {
        $deviceIds = $devices->pluck('id');
        $recentChecks = DB::query()->fromSub(
            DeviceCheck::query()
                ->select('id', 'device_id', 'status', 'checked_at')
                ->selectRaw('row_number() over (partition by device_id order by checked_at desc) as position')
                ->whereIn('device_id', $deviceIds),
            'recent_checks'
        )->where('position', '<=', 2)->orderBy('device_id')->orderBy('position')->get()->groupBy('device_id');

        $configuredOids = $devices->flatMap(fn (Device $device) => collect($device->snmp_oids ?? [])
            ->filter(fn ($sensor) => ($sensor['change_mode'] ?? 'ignore') === 'notify' && isset($sensor['threshold_value']) && ! empty($sensor['oid']))
            ->map(fn ($sensor) => $sensor['oid']))->unique()->values();
        $latestMetrics = $configuredOids->isEmpty() ? collect() : DB::query()->fromSub(
            SnmpMetric::query()
                ->select('id', 'device_id', 'oid', 'numeric_value', 'polled_at')
                ->selectRaw('row_number() over (partition by device_id, oid order by polled_at desc) as position')
                ->whereIn('device_id', $deviceIds)
                ->whereIn('oid', $configuredOids),
            'recent_metrics'
        )->where('position', 1)->get()->keyBy(fn ($metric) => $metric->device_id.'|'.$metric->oid);

        return $devices->flatMap(function (Device $device) use ($recentChecks, $latestMetrics) {
            $alerts = collect();
            $pingConfigured = $device->ping_enabled || collect($device->snmp_oids ?? [])->contains(fn ($sensor) => ($sensor['sensor_type'] ?? null) === 'ping' || strtolower($sensor['label'] ?? '') === 'ping');
            $checks = $recentChecks->get($device->id, collect());
            $latestCheck = $checks->first();
            $previousCheck = $checks->get(1);
            if ($pingConfigured) {
                if ($latestCheck && $latestCheck->status === 'offline') {
                    $alerts->push([
                        'key' => sha1('icmp|offline|'.$device->id.'|'.$latestCheck->checked_at),
                        'device' => $device->name, 'sensor' => 'ICMP Ping', 'value' => 'OFFLINE', 'unit' => '',
                        'time' => $latestCheck->checked_at,
                        'speech' => "Peringatan {$device->name}. Perangkat offline dan tidak merespons ICMP Ping.",
                    ]);
                } elseif ($latestCheck && $previousCheck && $latestCheck->status === 'online' && $previousCheck->status === 'offline') {
                    $alerts->push([
                        'key' => sha1('icmp|recovery|'.$device->id.'|'.$latestCheck->checked_at),
                        'device' => $device->name, 'sensor' => 'ICMP Ping', 'value' => 'ONLINE', 'unit' => '',
                        'time' => $latestCheck->checked_at,
                        'speech' => "Informasi {$device->name}. Perangkat kembali online.",
                    ]);
                }
            }
            $sensorAlerts = collect($device->snmp_oids ?? [])->filter(fn ($sensor) => ($sensor['change_mode'] ?? 'ignore') === 'notify' && isset($sensor['threshold_value']))->map(function ($sensor) use ($device, $latestMetrics) {
                $metric = $latestMetrics->get($device->id.'|'.($sensor['oid'] ?? ''));
                if (! $metric || $metric->numeric_value === null) return null;
                $value = (float) $metric->numeric_value; $threshold = (float) $sensor['threshold_value'];
                $triggered = match ($sensor['threshold_operator'] ?? 'gt') {
                    'gte' => $value >= $threshold, 'lt' => $value < $threshold, 'lte' => $value <= $threshold,
                    'eq' => $value == $threshold, 'neq' => $value != $threshold, default => $value > $threshold,
                };
                $unit = $sensor['unit'] ?? '';
                $label = $sensor['label'] ?? $sensor['oid'];
                return $triggered ? [
                    'key' => sha1($device->id.'|'.$metric->id.'|'.$sensor['oid']),
                    'device' => $device->name, 'sensor' => $label, 'value' => $value,
                    'unit' => $unit, 'time' => $metric->polled_at,
                    'speech' => "Peringatan {$device->name}. Sensor {$label}, nilai {$value} {$unit}, melewati ambang batas.",
                ] : null;
            })->filter();
            $slaValue = $device->getAttribute('sla_percentage');
            if ($device->sla_enabled && $device->sla_threshold_percentage !== null && $slaValue !== null && $slaValue < (float) $device->sla_threshold_percentage) {
                $sensorAlerts->push([
                    'key' => sha1('sla|'.$device->id.'|'.number_format($slaValue, 2)),
                    'device' => $device->name, 'sensor' => 'SLA', 'value' => number_format($slaValue, 2), 'unit' => '%',
                    'time' => $device->last_checked_at ?? now(),
                    'speech' => "Peringatan {$device->name}. SLA {$slaValue} persen berada di bawah threshold {$device->sla_threshold_percentage} persen.",
                ]);
            }
            return $alerts->concat($sensorAlerts);
        })->sortByDesc('time')->values();
    }

    private function projectMap($projects, $devices)
    {
        return $projects->map(function (Project $project) use ($devices) {
            $projectDevices = $devices->where('project_id', $project->id);
            $coordinates = $projectDevices
                ->filter(fn (Device $device) => $device->latitude && $device->longitude && (float) $device->latitude !== 0.0 && (float) $device->longitude !== 0.0);
            $location = $project->name.' '.$project->location.' '.$projectDevices->pluck('region')->filter()->implode(' ').' '.$projectDevices->pluck('city')->filter()->implode(' ');
            [$mapLeft, $mapTop] = app(IndonesiaMapService::class)->percentage($location);
            $online = $projectDevices->where('status', 'online')->count();

            return [
                'project' => $project,
                'map_left' => $mapLeft,
                'map_top' => $mapTop,
                'device_count' => $projectDevices->count(),
                'online_count' => $online,
                'offline_count' => $projectDevices->where('status', 'offline')->count(),
                'health' => $projectDevices->isEmpty() ? 0 : (int) round(($online / $projectDevices->count()) * 100),
            ];
        });
    }

}
