<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnmpSensor extends Model
{
    protected $fillable = ['device_id', 'sensor_type', 'label', 'oid', 'unit', 'value_type', 'configuration', 'is_active'];
    protected $casts = ['configuration' => 'array', 'is_active' => 'boolean'];

    public function device() { return $this->belongsTo(Device::class); }
    public function metrics() { return $this->hasMany(SnmpMetric::class, 'sensor_id'); }
}
