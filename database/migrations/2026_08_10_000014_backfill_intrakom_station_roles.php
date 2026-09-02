<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('devices')->whereIn(DB::raw('LOWER(type)'), ['intrakom','intrakom bs','intracom','intracom bs'])->whereNull('station_role')->update(['station_role' => 'BS']);
    }
    public function down(): void {}
};
