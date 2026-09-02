<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceCheck extends Model
{
    public $timestamps = false;

    protected $fillable = ['device_id', 'status', 'latency_ms', 'message', 'checked_at'];

    protected $casts = ['checked_at' => 'datetime'];
}
