<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('telemetry_exports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('exported_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_exports');
    }
};
