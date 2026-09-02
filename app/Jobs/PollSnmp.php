<?php

namespace App\Jobs;

use App\Models\Device;
use App\Services\SnmpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class PollSnmp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 45;
    public bool $failOnTimeout = true;

    public function __construct(public int $deviceId) {}

    public function handle(SnmpService $snmp): void
    {
        $lock = Cache::lock('device:snmp:'.$this->deviceId, 50);
        if (! $lock->get()) return;

        $device = null;

        try {
            $device = Device::find($this->deviceId);
            if (! $device || ! $device->is_active || ! $device->snmp_enabled) return;

            $snmp->poll($device);
        } catch (\Throwable $exception) {
            $device?->update(['snmp_status' => 'error', 'snmp_last_polled_at' => now()]);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function backoff(): array { return [5, 20]; }
}
