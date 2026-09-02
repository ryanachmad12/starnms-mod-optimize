<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Jobs\PollSnmp;

class SnmpController extends Controller
{
    public function poll(Device $device)
    {
        PollSnmp::dispatch($device->id)->onQueue('snmp');
        return response()->json(['status' => 'queued', 'message' => 'Polling perangkat dimasukkan ke antrean.'], 202);
    }

    public function metrics(Request $request, Device $device)
    {
        $request->validate([
            'hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        $customRange = $request->filled(['start_date', 'end_date']);
        $start = $customRange ? Carbon::parse($request->input('start_date')) : now()->subHours(min(8760, max(1, (int) $request->input('hours', 24))));
        $end = $customRange ? Carbon::parse($request->input('end_date')) : now();
        $hours = max(1, (int) ceil($start->diffInMinutes($end) / 60));
        $bucket = match (true) {
            $hours <= 1 => 'second',
            $hours <= 24 => 'hour',
            $hours <= 720 => 'day',
            default => 'month',
        };
        $query = $device->snmpMetrics()
            ->whereBetween('polled_at', [$start, $end])
            ->selectRaw("oid, date_trunc('$bucket', polled_at) AS bucket_time, AVG(numeric_value) AS numeric_value, MAX(raw_value) AS raw_value")
            ->groupBy('oid')
            ->groupByRaw("date_trunc('$bucket', polled_at)");
        if ($request->filled('oid')) $query->where('oid', $request->string('oid'));

        $rowsByOid = $query->orderBy('bucket_time')->get()->groupBy('oid');
        $series = collect($device->snmp_oids ?? [])->map(function ($configuration) use ($rowsByOid) {
            $oid = $configuration['oid'] ?? '';
            $rows = $rowsByOid->get($oid, collect());
            return [
                'label' => $configuration['label'] ?? $oid,
                'oid' => $oid,
                'unit' => $configuration['unit'] ?? 'Value',
                'value_type' => $configuration['value_type'] ?? (($configuration['sensor_type'] ?? '') === 'ping' ? 'gauge' : 'gauge'),
                'multiplier' => $configuration['multiplier'] ?? 1,
                'divisor' => $configuration['divisor'] ?? 1,
                'points' => $rows->map(fn ($row) => [
                    'time' => \Carbon\Carbon::parse($row->bucket_time)->toIso8601String(),
                    'value' => $row->numeric_value === null ? null : round((float) $row->numeric_value, 4),
                    'raw' => $row->raw_value,
                ])->values(),
            ];
        })->filter(fn ($item) => $item['oid'] !== '')->values();

        return response()->json([
            'device' => $device->only('id', 'name', 'ip_address', 'snmp_version', 'snmp_status', 'snmp_oids'),
            'aggregation' => $bucket,
            'range' => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String(), 'custom' => $customRange],
            'series' => $series,
        ]);
    }
}
