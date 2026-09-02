<?php

namespace App\Services;

use App\Models\Device;

class SlaService
{
    public function calculate(Device $device, int $days = 30): ?float
    {
        if (! $device->sla_enabled || ! $device->sla_activated_at) return null;

        $end = now();
        $periodStart = $end->copy()->subDays($days);
        $start = $device->sla_activated_at->greaterThan($periodStart)
            ? $device->sla_activated_at->copy() : $periodStart;
        if ($start->greaterThanOrEqualTo($end)) return 100.0;

        $checks = $device->checks()->whereBetween('checked_at', [$start, $end])->orderBy('checked_at')->get();
        $previous = $device->checks()->where('checked_at', '<', $start)->latest('checked_at')->first();
        $status = $previous?->status ?? $checks->first()?->status ?? $device->status;
        $cursor = $start;
        $uptime = 0;

        foreach ($checks as $check) {
            if ($check->checked_at->greaterThan($cursor) && $status === 'online') {
                $uptime += $cursor->diffInSeconds($check->checked_at);
            }
            $cursor = $check->checked_at;
            $status = $check->status;
        }
        if ($end->greaterThan($cursor) && $status === 'online') $uptime += $cursor->diffInSeconds($end);

        return round(min(100, max(0, ($uptime / max(1, $start->diffInSeconds($end))) * 100)), 2);
    }

    public function applyToDevices($devices, int $days = 30): void
    {
        foreach ($devices as $device) $device->setAttribute('sla_percentage', $this->calculate($device, $days));
    }

    public function projectAverage($devices): ?float
    {
        $enabled = $devices->where('sla_enabled', true)->filter(fn ($device) => $device->sla_percentage !== null);
        return $enabled->isEmpty() ? null : round((float) $enabled->avg('sla_percentage'), 2);
    }
}
