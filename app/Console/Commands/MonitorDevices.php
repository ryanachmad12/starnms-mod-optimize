<?php

namespace App\Console\Commands;

use App\Jobs\PollDevice;
use App\Jobs\PollSnmp;
use App\Models\Device;
use Illuminate\Console\Command;

class MonitorDevices extends Command
{
    protected $signature = 'monitor:devices';
    protected $description = 'Masukkan satu job polling untuk setiap perangkat aktif';

    public function handle(): int
    {
        Device::where('is_active', true)->select('id', 'ping_enabled', 'snmp_enabled')->chunkById(250, function ($devices) {
            foreach ($devices as $device) {
                if ($device->ping_enabled) PollDevice::dispatch($device->id)->onQueue('monitoring');
                if ($device->snmp_enabled) PollSnmp::dispatch($device->id)->onQueue('snmp');
            }
        });
        return self::SUCCESS;
    }
}
