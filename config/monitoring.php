<?php

return [
    'device_check_retention_days' => (int) env('DEVICE_CHECK_RETENTION_DAYS', 90),
    'snmp_metric_retention_days' => (int) env('SNMP_METRIC_RETENTION_DAYS', 90),
    'prune_batch_size' => (int) env('MONITORING_PRUNE_BATCH_SIZE', 10000),
];
