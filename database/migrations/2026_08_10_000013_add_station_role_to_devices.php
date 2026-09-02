<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('station_role', 2)->nullable()->after('sector')->index();
        });

        $radioTypes = ['wireless access point','radio link','intracom bs','intracom','intrakom bs','intrakom','telrad bs','telrad','himax 331 v.2','himax 331 v.3','himax 331 v2','himax 331 v3'];
        DB::table('devices')->whereIn(DB::raw('LOWER(type)'), $radioTypes)->update(['station_role' => 'BS']);
        DB::table('devices')->where(function ($query) {
            $query->whereRaw("LOWER(name) LIKE 'ss-%'")->orWhereRaw("LOWER(name) LIKE 'ss -%'");
        })->update(['station_role' => 'SS']);

        $links = [
            'SS - 007C180L35 Indofood Sukses Makmur Tirta Perkasa Pontianak' => 'BS-ODU INTRAKOM AIRPORT PONTIANAK',
            'SS - 007C39281L1320 Kantor Dukcapil Sungai Raya' => 'BS-ODU INTRAKOM AIRPORT PONTIANAK',
            'SS - 007C39281L1297 Kantor Dukcapil Pontianak Barat Tanjung Raya Pontianak' => 'BS-ODU INTRAKOM TANJUNG RAYA',
        ];
        foreach ($links as $subscriber => $baseStation) {
            $parentId = DB::table('devices')->whereRaw('LOWER(name) = ?', [strtolower($baseStation)])->value('id');
            if ($parentId) DB::table('devices')->whereRaw('LOWER(name) = ?', [strtolower($subscriber)])->update(['parent_id' => $parentId, 'station_role' => 'SS']);
        }
    }

    public function down(): void
    {
        Schema::table('devices', fn (Blueprint $table) => $table->dropColumn('station_role'));
    }
};
