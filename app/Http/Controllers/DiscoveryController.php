<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Project;
use App\Services\PingService;
use Illuminate\Http\Request;

class DiscoveryController extends Controller
{
    public function scan(Request $request, Project $project, PingService $ping)
    {
        $data = $request->validate([
            'ip_min' => ['required', 'ipv4'],
            'ip_max' => ['required', 'ipv4'],
        ]);
        $min = ip2long($data['ip_min']);
        $max = ip2long($data['ip_max']);
        abort_if($min === false || $max === false || $max < $min, 422, 'Range IP tidak valid.');
        abort_if(($max - $min + 1) > 256, 422, 'Maksimal 256 alamat IP dalam satu proses discovery.');

        $addresses = [];
        for ($current = $min; $current <= $max; $current++) $addresses[] = long2ip($current);
        $existing = Device::whereIn('ip_address', $addresses)->pluck('ip_address')->all();
        $targets = array_values(array_diff($addresses, $existing));
        $found = $ping->discover($targets);
        $created = [];
        foreach ($found as $ip => $latency) {
            $lastOctet = substr(strrchr($ip, '.'), 1);
            $device = Device::create([
                'project_id' => $project->id,
                'name' => 'DISCOVERED-'.$ip,
                'lid' => 'AUTO-'.$lastOctet,
                'ip_address' => $ip,
                'mac_address' => '00:00:00:00:00:00',
                'type' => 'Unknown Device',
                'region' => 'AUTO',
                'city' => $project->location ?: 'Auto Discovery',
                'latitude' => 0,
                'longitude' => 0,
                'monitor_port' => 80,
                'web_protocol' => 'http',
                'web_port' => 80,
                'telnet_port' => 23,
                'ssh_port' => 22,
                'is_active' => true,
                'sla_enabled' => false,
                'sla_activated_at' => null,
                'status' => 'online',
                'latency_ms' => $latency,
                'last_checked_at' => now(),
                'last_seen_at' => now(),
                'snmp_enabled' => false,
                'snmp_version' => '2c',
                'snmp_community' => 'public',
                'snmp_port' => 161,
                'snmp_timeout_ms' => 1200,
                'snmp_oids' => [],
                'snmp_status' => 'disabled',
            ]);
            $created[] = ['id' => $device->id, 'name' => $device->name, 'ip_address' => $ip, 'latency_ms' => $latency];
        }

        return response()->json([
            'message' => count($created).' device baru ditemukan dan ditambahkan ke project '.$project->name.'.',
            'scanned' => count($addresses),
            'skipped_existing' => count($existing),
            'created' => $created,
        ]);
    }
}
