<?php

namespace App\Jobs;

use App\Http\Controllers\MonitoringController;
use App\Models\Device;
use App\Models\MonitoringBatch;
use App\Services\PingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class PollDevice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 45;
    public bool $failOnTimeout = true;

    public function __construct(public int $deviceId, public ?int $monitoringBatchId = null, public bool $forcePing = false) {}

    public function handle(PingService $ping, MonitoringController $monitor): void
    {
        $lock = Cache::lock('device:icmp:'.$this->deviceId, 30);
        if (! $lock->get()) {
            $this->release(5);
            return;
        }
        $device = Device::find($this->deviceId);
        if (! $device || ! $device->is_active || (! $device->ping_enabled && ! $this->forcePing)) {
            $this->failBatch();
            $lock->release();
            return;
        }

        try {
            $sensor = collect($device->snmp_oids ?? [])->first(fn ($s) => ($s['sensor_type'] ?? null) === 'ping' || strtolower($s['label'] ?? '') === 'ping');
            $monitor->persist($device, $ping->check($device, $sensor));
            $this->completeBatch();
        } finally {
            $lock->release();
        }
    }

    public function backoff(): array { return [2, 10]; }

    public function failed(\Throwable $exception): void
    {
        $this->failBatch();
    }

    private function completeBatch(): void
    {
        if ($this->monitoringBatchId) MonitoringBatch::find($this->monitoringBatchId)?->markCompleted();
    }

    private function failBatch(): void
    {
        if ($this->monitoringBatchId) MonitoringBatch::find($this->monitoringBatchId)?->markFailed();
    }
}
