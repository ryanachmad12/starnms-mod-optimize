<?php

namespace App\Services;

use App\Models\Device;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class PingService
{
    public function discover(array $ipAddresses, int $timeoutMs = 700, int $parallel = 16): array
    {
        $found = [];
        $isWindows = PHP_OS_FAMILY === 'Windows';
        foreach (array_chunk($ipAddresses, max(1, min(32, $parallel))) as $chunk) {
            $processes = [];
            foreach ($chunk as $ip) {
                $command = $isWindows
                    ? ['C:\\Windows\\System32\\PING.EXE', '-4', '-n', '1', '-w', (string) $timeoutMs, $ip]
                    : ['ping', '-c', '1', '-W', (string) max(1, (int) ceil($timeoutMs / 1000)), $ip];
                $process = new Process($command);
                $process->setTimeout(max(3, ($timeoutMs / 1000) + 2));
                $process->start();
                $processes[$ip] = $process;
            }
            foreach ($processes as $ip => $process) {
                $process->wait();
                $output = $process->getOutput().' '.$process->getErrorOutput();
                $received = $this->receivedPackets($output, $isWindows);
                if ($received > 0) $found[$ip] = $this->averageLatency($output, $isWindows) ?? 0;
            }
        }
        return $found;
    }

    public function check(Device $device, ?array $settings = null): array
    {
        $settings ??= [];
        $method = $settings['ping_method'] ?? $device->ping_method ?? 'multiple';
        $count = $method === 'single' ? 1 : min(3, max(1, (int) ($settings['ping_count'] ?? $device->ping_count ?? 1)));
        $timeoutMs = min(3000, max(100, (int) ($settings['ping_timeout_ms'] ?? $device->ping_timeout_ms ?? config('nms.ping_timeout_ms'))));
        $size = max(1, (int) ($settings['ping_packet_size'] ?? $device->ping_packet_size ?? 32));
        $delayMs = max(0, (int) ($settings['ping_delay_ms'] ?? $device->ping_delay_ms ?? 5));
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $command = $isWindows
            ? ['C:\\Windows\\System32\\PING.EXE', '-4', '-n', (string) $count, '-w', (string) $timeoutMs, '-l', (string) $size, $device->ip_address]
            : ['ping', '-c', (string) $count, '-W', (string) max(1, (int) ceil($timeoutMs / 1000)), '-s', (string) $size, $device->ip_address];
        $process = new Process($command);
        $process->setTimeout(max(5, ($count * (($timeoutMs / 1000) + ($delayMs / 1000))) + 3));
        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            // An unreachable Windows host may keep ping.exe alive until the
            // process timeout. That is a valid ICMP offline result.
        }
        $output = $process->getOutput().' '.$process->getErrorOutput();
        $received = $this->receivedPackets($output, $isWindows, $process->getExitCode());
        $latency = $this->averageLatency($output, $isWindows);
        $retried = false;
        if ($received === 0) {
            $retried = true;
            $retryCommand = $isWindows
                ? ['C:\\Windows\\System32\\PING.EXE', '-4', '-n', '1', '-w', (string) $timeoutMs, '-l', (string) $size, $device->ip_address]
                : ['ping', '-c', '1', '-W', (string) max(1, (int) ceil($timeoutMs / 1000)), '-s', (string) $size, $device->ip_address];
            $retry = new Process($retryCommand);
            $retry->setTimeout(max(3, ($timeoutMs / 1000) + 2));
            try {
                $retry->run();
            } catch (ProcessTimedOutException) {
                // Keep received=0 and persist the device as offline.
            }
            $retryOutput = $retry->getOutput().' '.$retry->getErrorOutput();
            $received = $this->receivedPackets($retryOutput, $isWindows, $retry->getExitCode());
            $latency = $this->averageLatency($retryOutput, $isWindows);
            $output .= "\nRETRY:\n".$retryOutput;
        }
        $online = $received > 0;

        return [
            'status' => $online ? 'online' : 'offline',
            'latency_ms' => $online ? ($latency ?? 0) : null,
            'message' => $online
                ? 'ICMP Ping '.($retried ? 'retry ' : '')."{$received} reply, average ".($latency ?? 0).' ms'
                : "ICMP Ping timeout ({$count} request)",
        ];
    }

    private function averageLatency(string $output, bool $isWindows): ?int
    {
        if ($isWindows && preg_match('/Average = (\d+)ms/i', $output, $match)) return (int) $match[1];
        if (! $isWindows && preg_match('/= [\d.]+\/([\d.]+)\/[\d.]+\/[\d.]+ ms/', $output, $match)) return (int) round((float) $match[1]);
        if (preg_match_all('/time[=<]([\d.]+)\s*ms/i', $output, $matches) && count($matches[1])) {
            return (int) round(array_sum(array_map('floatval', $matches[1])) / count($matches[1]));
        }
        return null;
    }

    private function receivedPackets(string $output, bool $isWindows, ?int $exitCode = null): int
    {
        if ($isWindows && preg_match('/Received = (\d+)/i', $output, $match)) return (int) $match[1];
        if ($isWindows && preg_match('/(?:Received|Diterima|Menerima)\s*=\s*(\d+)/i', $output, $match)) return (int) $match[1];
        if ($isWindows && preg_match_all('/(?:Reply from|Balasan dari|Jawaban dari) .*?(?:time[=<]|waktu[=<]|TTL=|TTL\s*=)/i', $output, $matches)) return count($matches[0]);
        // ping.exe exit code 0 is authoritative even when the OS localizes output.
        if ($isWindows && $exitCode === 0) return 1;
        if (! $isWindows && preg_match('/(\d+) received/', $output, $match)) return (int) $match[1];
        return 0;
    }
}
