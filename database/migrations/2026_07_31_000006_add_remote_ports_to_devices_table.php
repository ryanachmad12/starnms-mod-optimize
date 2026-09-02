<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('web_protocol', 8)->default('http');
            $table->unsignedInteger('web_port')->default(80);
            $table->unsignedInteger('telnet_port')->default(23);
            $table->unsignedInteger('ssh_port')->default(22);
        });
    }

    public function down(): void
    {
        Schema::table('devices', fn (Blueprint $table) => $table->dropColumn(['web_protocol', 'web_port', 'telnet_port', 'ssh_port']));
    }
};
