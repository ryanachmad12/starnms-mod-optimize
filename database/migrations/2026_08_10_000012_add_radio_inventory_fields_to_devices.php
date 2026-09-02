<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('lid', 100)->nullable()->index();
            $table->string('sector', 100)->nullable();
            $table->decimal('height_m', 8, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devices', fn (Blueprint $table) => $table->dropColumn(['lid', 'sector', 'height_m']));
    }
};
