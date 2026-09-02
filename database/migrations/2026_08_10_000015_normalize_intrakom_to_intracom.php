<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("UPDATE devices SET type = REPLACE(REPLACE(type, 'INTRAKOM', 'INTRACOM'), 'Intrakom', 'Intracom') WHERE LOWER(type) LIKE '%intrakom%'");
        DB::statement("UPDATE devices SET name = REPLACE(REPLACE(name, 'INTRAKOM', 'INTRACOM'), 'Intrakom', 'Intracom') WHERE LOWER(name) LIKE '%intrakom%'");
    }
    public function down(): void {}
};
