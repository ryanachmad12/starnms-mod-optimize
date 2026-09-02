<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('projects', function (Blueprint $table) { $table->id(); $table->string('name',180); $table->string('customer_name',180); $table->date('contract_start_date'); $table->date('contract_end_date'); $table->string('location',255); $table->unsignedSmallInteger('maintenance_interval_months'); $table->date('maintenance_anchor_date'); $table->text('notes')->nullable(); $table->boolean('is_active')->default(true); $table->timestampsTz(); $table->index(['contract_end_date','is_active']); }); }
    public function down(): void { Schema::dropIfExists('projects'); }
};
