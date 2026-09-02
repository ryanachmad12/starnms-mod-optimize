<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnmpMetric extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'device_id', 'sensor_id', 'oid', 'label', 'numeric_value', 'raw_value', 'data_type', 'sample_status', 'polled_at',
    ];

    protected $casts = ['numeric_value' => 'float', 'polled_at' => 'datetime'];

    public function sensor() { return $this->belongsTo(SnmpSensor::class, 'sensor_id'); }
}
