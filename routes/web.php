<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\SnmpController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TopologyController;
use App\Http\Controllers\ExcelController;
use App\Http\Controllers\TelemetryExportController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\AboutController;
use Illuminate\Support\Facades\Route;

Route::get('/health/ready', [HealthController::class, 'ready'])->middleware('throttle:30,1');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.submit');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/about', [AboutController::class, 'index'])->name('about');
    Route::get('/topology/projects/{project}', [TopologyController::class, 'project'])->name('topology.project');
    Route::get('/api/status', [MonitoringController::class, 'status'])->name('monitor.status');
    Route::get('/api/alerts', [DashboardController::class, 'alerts'])->name('monitor.alerts');
    Route::post('/devices/{device}/check', [MonitoringController::class, 'check'])->name('devices.check');
    Route::post('/monitor/check-all', [MonitoringController::class, 'checkAll'])->name('monitor.check-all');
    Route::get('/monitor/batches/{batch}', [MonitoringController::class, 'batch'])->name('monitor.batch');
    Route::post('/devices/{device}/snmp/poll', [SnmpController::class, 'poll'])->name('devices.snmp.poll');
    Route::get('/devices/{device}/snmp/metrics', [SnmpController::class, 'metrics'])->name('devices.snmp.metrics');
    Route::post('/projects/{project}/telemetry/export', [TelemetryExportController::class, 'export'])->name('projects.telemetry.export');

    Route::middleware('administrator')->group(function () {
        Route::get('/devices/export/excel', [ExcelController::class, 'exportDevices'])->name('devices.export');
        Route::get('/devices/template/excel', [ExcelController::class, 'draftDevices'])->name('devices.draft');
        Route::post('/devices/import/excel', [ExcelController::class, 'importDevices'])->name('devices.import');
        Route::get('/projects/export/excel', [ExcelController::class, 'exportProjects'])->name('projects.export');
        Route::get('/projects/template/excel', [ExcelController::class, 'draftProjects'])->name('projects.draft');
        Route::post('/projects/import/excel', [ExcelController::class, 'importProjects'])->name('projects.import');
        Route::post('/projects/{project}/discover', [DiscoveryController::class, 'scan'])->name('projects.discover');
        Route::resource('devices', DeviceController::class)->except(['show', 'create', 'edit', 'index']);
        Route::resource('users', UserController::class)->except(['show', 'create', 'edit']);
        Route::resource('projects', ProjectController::class)->except(['show', 'create', 'edit']);
    });
});
