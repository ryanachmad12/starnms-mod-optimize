<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('username', 60)->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->string('role', 20)->default('user')->index();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
