<?php
return ['default' => env('CACHE_STORE', 'redis'), 'stores' => [
    'file' => ['driver' => 'file', 'path' => storage_path('framework/cache/data')],
    'database' => ['driver' => 'database', 'connection' => null, 'table' => 'cache'],
    'redis' => ['driver' => 'redis', 'connection' => 'cache'],
], 'prefix' => 'starnms'];
