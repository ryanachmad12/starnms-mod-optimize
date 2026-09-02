<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeviceController extends Controller
{
    public function index()
    {
        return redirect()->route('dashboard');
    }

    public function store(Request $request)
    {
        $device = Device::create($this->validated($request));
        $device->syncSnmpSensors();
        return back()->with('success', 'Perangkat berhasil ditambahkan.');
    }

    public function update(Request $request, Device $device)
    {
        $device->update($this->validated($request, $device));
        $device->syncSnmpSensors();
        return back()->with('success', 'Data perangkat berhasil diperbarui.');
    }

    public function destroy(Device $device)
    {
        $name = $device->name;
        $device->delete();
        return back()->with('success', "Device {$name} berhasil dihapus dari Device Management dan Map Monitoring.");
    }

    private function validated(Request $request, ?Device $device = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'lid' => ['required', 'string', 'max:100'],
            'sector' => ['nullable', 'string', 'max:100'],
            'station_role' => ['nullable', Rule::in(['BS', 'SS'])],
            'parent_id' => ['nullable', 'integer', 'exists:devices,id'],
            'height_m' => ['nullable', 'numeric', 'between:0,999999.99'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'ip_address' => ['required', 'ipv4', Rule::unique('devices')->ignore($device)],
            'mac_address' => ['required', 'string', 'max:32'],
            'type' => ['required', 'string', 'max:80'],
            'region' => ['required', 'string', 'max:40'],
            'city' => ['required', 'string', 'max:80'],
            'monitor_port' => ['required', 'integer', 'between:1,65535'],
            'web_protocol' => ['required', Rule::in(['http', 'https'])],
            'web_port' => ['required', 'integer', 'between:1,65535'],
            'telnet_port' => ['required', 'integer', 'between:1,65535'],
            'ssh_port' => ['required', 'integer', 'between:1,65535'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'is_active' => ['nullable', 'boolean'],
            'sla_enabled' => ['nullable', 'boolean'],
            'sla_threshold_percentage' => ['required_if:sla_enabled,1', 'nullable', 'numeric', 'between:0,100'],
            'ping_enabled' => ['nullable', 'boolean'],
            'ping_timeout_ms' => ['required_if:ping_enabled,1', 'nullable', 'integer', 'between:100,3000'],
            'ping_packet_size' => ['required_if:ping_enabled,1', 'nullable', 'integer', 'between:1,65500'],
            'ping_count' => ['required_if:ping_enabled,1', 'nullable', 'integer', 'between:1,3'],
            'ping_delay_ms' => ['required_if:ping_enabled,1', 'nullable', 'integer', 'between:0,60000'],
            'ping_method' => ['required_if:ping_enabled,1', 'nullable', Rule::in(['single', 'multiple'])],
            'ping_auto_ack' => ['nullable', 'boolean'],
            'snmp_enabled' => ['nullable', 'boolean'],
            'snmp_version' => ['required_if:snmp_enabled,1', 'nullable', Rule::in(['1', '2c'])],
            'snmp_community' => ['required_if:snmp_enabled,1', 'nullable', 'string', 'max:100'],
            'snmp_port' => ['required_if:snmp_enabled,1', 'nullable', 'integer', 'between:1,65535'],
            'snmp_timeout_ms' => ['required_if:snmp_enabled,1', 'nullable', 'integer', 'between:100,10000'],
            'snmp_oids' => ['required_if:snmp_enabled,1', 'nullable', 'array', 'min:1', 'max:100'],
            'snmp_oids.*.label' => ['required', 'string', 'max:120'],
            'snmp_oids.*.oid' => ['nullable', 'string', 'max:255', 'regex:/^\.?\d+(?:\.\d+)+$/'],
            'snmp_oids.*.unit' => ['nullable', 'string', 'max:30'],
            'snmp_oids.*.value_type' => ['nullable', Rule::in(['gauge', 'counter', 'delta'])],
            'snmp_oids.*.multiplier' => ['nullable', 'numeric', 'between:-1000000000,1000000000'],
            'snmp_oids.*.divisor' => ['nullable', 'numeric', 'not_in:0', 'between:-1000000000,1000000000'],
            'snmp_oids.*.change_mode' => ['nullable', Rule::in(['ignore', 'notify'])],
            'snmp_oids.*.threshold_operator' => ['nullable', Rule::in(['gt', 'gte', 'lt', 'lte', 'eq', 'neq'])],
            'snmp_oids.*.threshold_value' => ['nullable', 'numeric', 'between:-1000000000,1000000000'],
            'snmp_oids.*.ping_timeout_ms' => ['nullable', 'integer', 'between:100,3000'],
            'snmp_oids.*.ping_packet_size' => ['nullable', 'integer', 'between:1,65500'],
            'snmp_oids.*.ping_count' => ['nullable', 'integer', 'between:1,3'],
            'snmp_oids.*.ping_delay_ms' => ['nullable', 'integer', 'between:0,60000'],
            'snmp_oids.*.ping_method' => ['nullable', Rule::in(['single', 'multiple'])],
            'snmp_oids.*.ping_auto_ack' => ['nullable', 'boolean'],
        ]);

        $radioTypes = ['wireless access point', 'radio link', 'intracom bs', 'intracom', 'intrakom bs', 'intrakom', 'telrad bs', 'telrad', 'himax 331 v.2', 'himax 331 v.3', 'himax 331 v2', 'himax 331 v3'];
        if (in_array(strtolower(trim($data['type'])), $radioTypes, true) && blank($data['sector'] ?? null)) {
            throw ValidationException::withMessages(['sector' => 'Field sektor wajib diisi untuk perangkat radio/wireless.']);
        }
        if (in_array(strtolower(trim($data['type'])), $radioTypes, true) && blank($data['station_role'] ?? null)) {
            throw ValidationException::withMessages(['station_role' => 'Pilih peran perangkat sebagai Base Station atau Subscriber Station.']);
        }
        if (($data['station_role'] ?? null) === 'SS' && blank($data['parent_id'] ?? null)) {
            throw ValidationException::withMessages(['parent_id' => 'Subscriber Station wajib memilih Base Station induk.']);
        }
        if (($data['station_role'] ?? null) === 'SS') {
            $baseStation = Device::find($data['parent_id']);
            if (! $baseStation || $baseStation->station_role !== 'BS' || (int) $baseStation->project_id !== (int) $data['project_id'] || strcasecmp((string) $baseStation->region, (string) $data['region']) !== 0) {
                throw ValidationException::withMessages(['parent_id' => 'Base Station induk harus berstatus BS serta berada pada project dan region yang sama.']);
            }
            if ($device && $baseStation->id === $device->id) throw ValidationException::withMessages(['parent_id' => 'Perangkat tidak dapat menjadi induk untuk dirinya sendiri.']);
            if ($baseStation->sector) $data['sector'] = $baseStation->sector;
        }
        if (($data['station_role'] ?? null) !== 'SS') $data['parent_id'] = null;

        $errors = [];
        foreach ($data['snmp_oids'] ?? [] as $index => $item) {
            if (strtolower(trim($item['label'])) === 'ping') {
                if (($item['change_mode'] ?? 'ignore') === 'notify' && (! array_key_exists('threshold_value', $item) || $item['threshold_value'] === '' || $item['threshold_value'] === null)) {
                    $errors["snmp_oids.$index.threshold_value"] = 'Latency threshold wajib diisi ketika Trigger notification Ping dipilih.';
                }
                continue;
            }
            foreach (['oid', 'unit', 'value_type', 'multiplier', 'divisor'] as $field) {
                if (! array_key_exists($field, $item) || $item[$field] === '' || $item[$field] === null) {
                    $errors["snmp_oids.$index.$field"] = "Field $field wajib untuk sensor SNMP OID.";
                }
            }
            if (($item['change_mode'] ?? 'ignore') === 'notify' && (! array_key_exists('threshold_value', $item) || $item['threshold_value'] === '' || $item['threshold_value'] === null)) {
                $errors["snmp_oids.$index.threshold_value"] = 'Threshold value wajib diisi ketika Trigger notification dipilih.';
            }
        }
        if ($errors) throw ValidationException::withMessages($errors);

        $oids = collect($data['snmp_oids'] ?? [])->map(function ($item) {
            if (strtolower(trim($item['label'])) === 'ping') {
                return [
                    'sensor_type' => 'ping', 'label' => 'Ping', 'oid' => 'plugin:ping', 'unit' => 'ms',
                    'ping_timeout_ms' => (int) ($item['ping_timeout_ms'] ?? 1200),
                    'ping_packet_size' => (int) ($item['ping_packet_size'] ?? 32),
                    'ping_count' => (int) ($item['ping_count'] ?? 1),
                    'ping_delay_ms' => (int) ($item['ping_delay_ms'] ?? 5),
                    'ping_method' => $item['ping_method'] ?? 'multiple',
                    'ping_auto_ack' => ! empty($item['ping_auto_ack']),
                    'change_mode' => $item['change_mode'] ?? 'ignore',
                    'threshold_operator' => $item['threshold_operator'] ?? 'gt',
                    'threshold_value' => isset($item['threshold_value']) && $item['threshold_value'] !== '' ? (float) $item['threshold_value'] : null,
                ];
            }
            return [
                'sensor_type' => 'snmp', 'label' => trim($item['label']),
                'oid' => ltrim(trim($item['oid']), '.'), 'unit' => trim($item['unit']),
                'value_type' => $item['value_type'], 'multiplier' => (float) $item['multiplier'],
                'divisor' => (float) $item['divisor'], 'change_mode' => $item['change_mode'] ?? 'ignore',
                'threshold_operator' => $item['threshold_operator'] ?? 'gt',
                'threshold_value' => isset($item['threshold_value']) && $item['threshold_value'] !== '' ? (float) $item['threshold_value'] : null,
            ];
        })->values()->all();

        unset($data['snmp_oids']);
        $slaEnabled = $request->boolean('sla_enabled');
        $pingConfigured = collect($oids)->contains(fn ($sensor) => ($sensor['sensor_type'] ?? null) === 'ping');
        return $data + [
            'is_active' => $request->boolean('is_active'),
            'sla_enabled' => $slaEnabled,
            'sla_activated_at' => $slaEnabled ? ($device?->sla_enabled ? $device->sla_activated_at : now()) : null,
            'sla_threshold_percentage' => $slaEnabled ? (float) $data['sla_threshold_percentage'] : null,
            'ping_enabled' => $request->boolean('ping_enabled') || $pingConfigured,
            'ping_timeout_ms' => ($data['ping_timeout_ms'] ?? null) ?: 1200,
            'ping_packet_size' => ($data['ping_packet_size'] ?? null) ?: 32,
            'ping_count' => ($data['ping_count'] ?? null) ?: 1,
            'ping_delay_ms' => $data['ping_delay_ms'] ?? 5,
            'ping_method' => ($data['ping_method'] ?? null) ?: 'multiple',
            'ping_auto_ack' => $request->boolean('ping_auto_ack'),
            'snmp_enabled' => $request->boolean('snmp_enabled'),
            'snmp_version' => ($data['snmp_version'] ?? null) ?: '2c',
            'snmp_community' => ($data['snmp_community'] ?? null) ?: 'public',
            'snmp_port' => ($data['snmp_port'] ?? null) ?: 161,
            'snmp_timeout_ms' => ($data['snmp_timeout_ms'] ?? null) ?: 1200,
            'snmp_oids' => $oids,
            'snmp_status' => $request->boolean('snmp_enabled') ? 'unknown' : 'disabled',
        ];
    }
}
