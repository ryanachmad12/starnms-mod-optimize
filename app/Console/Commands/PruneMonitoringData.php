<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneMonitoringData extends Command
{
    protected $signature = 'nms:prune-monitoring-data';

    protected $description = 'Remove raw monitoring samples that have passed the configured retention period';

    public function handle(): int
    {
        $batchSize = max(100, (int) config('monitoring.prune_batch_size'));
        $checks = $this->prune('device_checks', 'checked_at', now()->subDays((int) config('monitoring.device_check_retention_days'))->format('Y-m-d H:i:s'), $batchSize);
        $metrics = $this->prune('snmp_metrics', 'polled_at', now()->subDays((int) config('monitoring.snmp_metric_retention_days'))->format('Y-m-d H:i:s'), $batchSize);

        $this->info("Pruned {$checks} device checks and {$metrics} SNMP metrics.");

        return self::SUCCESS;
    }

    private function prune(string $table, string $timestampColumn, string $before, int $batchSize): int
    {
        $deleted = 0;

        do {
            // ctid keeps each batch short on PostgreSQL append-only monitoring tables.
            $count = DB::delete("delete from {$table} where ctid in (select ctid from {$table} where {$timestampColumn} < ? limit {$batchSize})", [$before]);
            $deleted += $count;
        } while ($count === $batchSize);

        return $deleted;
    }
}
