<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringBatch extends Model
{
    protected $fillable = ['requested_by', 'project_id', 'total_devices', 'completed_devices', 'failed_devices', 'status', 'completed_at'];

    protected $casts = ['completed_at' => 'datetime'];

    public function markCompleted(): void
    {
        $this->increment('completed_devices');
        $this->refreshStatus();
    }

    public function markFailed(): void
    {
        $this->increment('failed_devices');
        $this->refreshStatus();
    }

    private function refreshStatus(): void
    {
        $this->refresh();
        if (($this->completed_devices + $this->failed_devices) < $this->total_devices) return;

        $this->update([
            'status' => $this->failed_devices ? 'completed_with_errors' : 'completed',
            'completed_at' => now(),
        ]);
    }
}
