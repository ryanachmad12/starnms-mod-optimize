<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('monitoring_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->unsignedInteger('total_devices');
            $table->unsignedInteger('completed_devices')->default(0);
            $table->unsignedInteger('failed_devices')->default(0);
            $table->string('status', 32)->default('queued')->index();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->index(['requested_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_batches');
    }
};
