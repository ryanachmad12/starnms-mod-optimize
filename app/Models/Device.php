<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = [
        'project_id', 'name', 'lid', 'sector', 'station_role', 'height_m', 'ip_address', 'mac_address', 'type', 'region', 'city',
        'sla_enabled', 'sla_activated_at', 'sla_threshold_percentage',
        'latitude', 'longitude', 'parent_id', 'monitor_port', 'is_active',
        'status', 'latency_ms', 'last_checked_at', 'last_seen_at',
        'snmp_enabled', 'snmp_version', 'snmp_community', 'snmp_port',
        'snmp_timeout_ms', 'snmp_oids', 'snmp_last_polled_at', 'snmp_status',
        'ping_enabled', 'ping_timeout_ms', 'ping_packet_size', 'ping_count',
        'ping_delay_ms', 'ping_method', 'ping_auto_ack',
        'web_protocol', 'web_port', 'telnet_port', 'ssh_port',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'height_m' => 'decimal:2',
        'snmp_enabled' => 'boolean',
        'ping_enabled' => 'boolean',
        'ping_auto_ack' => 'boolean',
        'sla_enabled' => 'boolean',
        'sla_activated_at' => 'datetime',
        'sla_threshold_percentage' => 'decimal:2',
        'snmp_oids' => 'array',
        'snmp_last_polled_at' => 'datetime',
    ];

    public function checks(): HasMany
    {
        return $this->hasMany(DeviceCheck::class);
    }

    public function snmpMetrics(): HasMany
    {
        return $this->hasMany(SnmpMetric::class);
    }

    public function snmpSensors(): HasMany
    {
        return $this->hasMany(SnmpSensor::class);
    }

    public function syncSnmpSensors(): void
    {
        $activeIds = collect($this->snmp_oids ?? [])->map(function (array $configuration) {
            $oid = $configuration['oid'] ?? null;
            if (! $oid) return null;
            return $this->snmpSensors()->updateOrCreate(['oid' => $oid], [
                'sensor_type' => $configuration['sensor_type'] ?? 'snmp',
                'label' => $configuration['label'] ?? $oid,
                'unit' => $configuration['unit'] ?? 'Value',
                'value_type' => $configuration['value_type'] ?? 'gauge',
                'configuration' => $configuration,
                'is_active' => true,
            ])->id;
        })->filter();
        $this->snmpSensors()->whereNotIn('id', $activeIds)->update(['is_active' => false]);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function baseStation()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function subscriberStations(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
