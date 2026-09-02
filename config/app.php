<?php

return [
    'name' => env('APP_NAME', 'NMS Starcom'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'Asia/Jakarta',
    'locale' => 'id',
    'fallback_locale' => 'en',
    'faker_locale' => 'id_ID',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => array_filter(explode(',', env('APP_PREVIOUS_KEYS', ''))),
    'build' => [
        'version' => env('APP_VERSION', '1.0.0'),
        'id' => env('APP_BUILD_ID', 'development'),
        'release' => env('APP_RELEASE', 'development'),
        'channel' => env('APP_RELEASE_CHANNEL', 'Stable'),
        'built_at' => env('APP_BUILT_AT', 'Not recorded'),
    ],
    'maintenance' => ['driver' => 'file', 'store' => 'database'],
    'providers' => Illuminate\Support\ServiceProvider::defaultProviders()->merge([
        App\Providers\AppServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
    ])->toArray(),
    'aliases' => Illuminate\Support\Facades\Facade::defaultAliases()->toArray(),
];
