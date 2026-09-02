<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('snmp_metrics')->where('oid', 'plugin:ping')->where('sample_status', 'timeout')->update(['numeric_value' => null]);
    }

    public function down(): void
    {
        // Timeout bukan latency aktual, sehingga tidak dikembalikan menjadi angka buatan.
    }
};
