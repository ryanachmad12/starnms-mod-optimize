<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('devices', fn (Blueprint $table) => $table->decimal('sla_threshold_percentage', 5, 2)->nullable()->after('sla_activated_at')); }
    public function down(): void { Schema::table('devices', fn (Blueprint $table) => $table->dropColumn('sla_threshold_percentage')); }
};
