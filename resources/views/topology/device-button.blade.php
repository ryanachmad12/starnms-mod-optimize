@php
    $key = strtolower($device->type);
    $icon = match (true) {
        str_contains($key, 'router') => '◈', str_contains($key, 'switch') => '⇄', str_contains($key, 'firewall') => '▦',
        str_contains($key, 'server'), str_contains($key, 'storage') => '▤', str_contains($key, 'access point'), str_contains($key, 'wireless') => '◉',
        str_contains($key, 'radio'), str_contains($key, 'intrakom'), str_contains($key, 'telrad'), str_contains($key, 'himax') => '⌁',
        str_contains($key, 'olt'), str_contains($key, 'onu'), str_contains($key, 'ont') => '◆', str_contains($key, 'camera'), str_contains($key, 'cctv') => '●',
        str_contains($key, 'iot') => '◇', default => '■',
    };
@endphp
<button class="detail-device {{ $device->status }}" data-device-id="{{ $device->id }}" data-check-url="{{ route('devices.check',$device) }}" data-name="{{ $device->name }}" data-ip="{{ $device->ip_address }}" data-type="{{ $device->type }}" data-web="{{ $device->web_protocol }}://{{ $device->ip_address }}:{{ $device->web_port }}" data-telnet="telnet://{{ $device->ip_address }}:{{ $device->telnet_port }}" data-ssh="ssh://{{ $device->ip_address }}:{{ $device->ssh_port }}" data-snmp-enabled="{{ $device->snmp_enabled ? 1 : 0 }}" data-snmp-version="{{ $device->snmp_version }}" data-snmp-port="{{ $device->snmp_port }}" data-snmp-status="{{ $device->snmp_status }}" data-snmp-metrics-url="{{ route('devices.snmp.metrics',$device) }}" data-snmp-poll-url="{{ route('devices.snmp.poll',$device) }}"><b>{{ $icon }}</b><span>{{ $device->name }}</span><small>{{ $device->ip_address }} · {{ strtoupper($device->status) }}</small><em class="device-sla">{{ $device->sla_enabled ? 'SLA '.number_format($device->sla_percentage, 2).'%' : 'SLA OFF' }}</em></button>
