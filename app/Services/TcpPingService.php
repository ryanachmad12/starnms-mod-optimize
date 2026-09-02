<?php

namespace App\Services;

use App\Models\Device;

class TcpPingService
{
    public function check(Device $device): array
    {
        $started = microtime(true);
        $timeout = max(0.2, (int) config('nms.ping_timeout_ms', 1200) / 1000);
        $port = $device->monitor_port ?: (int) config('nms.ping_port', 80);
        $errno = 0;
        $error = '';

        $socket = @stream_socket_client(
            "tcp://{$device->ip_address}:{$port}",
            $errno,
            $error,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        $latency = (int) round((microtime(true) - $started) * 1000);
        if (is_resource($socket)) {
            fclose($socket);
            return ['status' => 'online', 'latency_ms' => $latency, 'message' => "TCP {$port} tersambung"];
        }

        return ['status' => 'offline', 'latency_ms' => null, 'message' => $error ?: "TCP {$port} tidak merespons"];
    }
}
