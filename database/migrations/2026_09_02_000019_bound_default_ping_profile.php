<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('alter table devices alter column ping_timeout_ms set default 1200');
        DB::statement('alter table devices alter column ping_count set default 1');
        DB::table('devices')->where('ping_timeout_ms', '>', 3000)->update(['ping_timeout_ms' => 1200]);
        DB::table('devices')->where('ping_count', '>', 3)->update(['ping_count' => 1]);
    }

    public function down(): void
    {
        DB::statement('alter table devices alter column ping_timeout_ms set default 5000');
        DB::statement('alter table devices alter column ping_count set default 5');
    }
};
