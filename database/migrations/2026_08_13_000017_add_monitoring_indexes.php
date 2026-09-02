<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('devices', fn (Blueprint $t) => $t->index(['is_active','id'], 'devices_active_id_index')); Schema::table('device_checks', fn (Blueprint $t) => $t->index(['device_id','checked_at'], 'checks_device_checked_index')); } public function down(): void {} };
