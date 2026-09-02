<?php

namespace Database\Seeders;

use App\Models\Device;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $devices = [
            ['ODU INTRACOM PULO BRAYAN', '10.162.60.6', 'INTRACOM BS', 'MDN', 'Medan'],
            ['ODU INTRACOM OTISTA MERDEKA', '10.162.75.104', 'INTRACOM BS', 'JKT', 'Jakarta'],
            ['ODU INTRACOM GENDUNGAN GILIGENTING', '10.161.25.58', 'INTRACOM BS', 'MDUR', 'Madura'],
            ['ODU INTRACOM AIRPORT PONTIANAK', '10.161.60.53', 'INTRACOM BS', 'PTK', 'Pontianak'],
            ['ODU INTRACOM TANJUNG RAYA', '10.161.60.103', 'INTRACOM BS', 'PTK', 'Pontianak'],
            ['ODU INTRACOM SIDOYOSO', '10.162.57.5', 'INTRACOM BS', 'SBY', 'Surabaya'],
            ['ODU INTRACOM KELANDIS', '10.162.59.8', 'INTRACOM BS', 'DPS', 'Denpasar'],
            ['ODU INTRACOM IMAM BONJOL', '10.162.60.154', 'INTRACOM BS', 'MDN', 'Medan'],
            ['ODU INTRACOM RUNGKUT', '10.162.68.57', 'INTRACOM BS', 'SBY', 'Surabaya'],
            ['ODU INTRACOM KAMPUNG JAWA', '10.162.82.153', 'INTRACOM BS', 'SMD', 'Samarinda'],
            ['MU 2 TELRAD JELAMBAR', '10.162.32.6', 'TELRAD BS', 'JKRT', 'Jakarta'],
            ['MU 3 TELRAD JELAMBAR', '10.162.32.7', 'TELRAD BS', 'JKRT', 'Jakarta'],
            ['MU 1 TELRAD CIBIRU', '10.162.48.101', 'TELRAD BS', 'BDG', 'Bandung'],
            ['MU 1 TELRAD CILEUNYI', '10.162.48.104', 'TELRAD BS', 'BDG', 'Bandung'],
            ['MU 1 TELRAD MENUR', '10.162.57.76', 'TELRAD BS', 'SBY', 'Surabaya'],
            ['MU 1 TELRAD KARANG AYU', '10.162.63.104', 'TELRAD BS', 'SMR', 'Semarang'],
            ['MU 1 TELRAD DATACOM', '10.161.37.10', 'TELRAD BS', 'PLM', 'Palembang'],
            ['MU 1 TELRAD PATHUK', '10.161.74.14', 'TELRAD BS', 'YGY', 'Yogyakarta'],
            ['MU 1 TELRAD JELAMBAR', '10.162.32.5', 'TELRAD BS', 'JKRT', 'Jakarta'],
            ['MU 2 TELRAD CIBIRU', '10.162.48.102', 'TELRAD BS', 'BDG', 'Bandung'],
            ['MU 1 TELRAD NGALIYAN', '10.162.63.6', 'TELRAD BS', 'SMR', 'Semarang'],
            ['MU 1 TELRAD PADALARANG', '10.162.71.6', 'TELRAD BS', 'BDG', 'Bandung'],
            ['MU 1 TELRAD GUNUNG BALAU', '10.162.78.62', 'TELRAD BS', 'LMP', 'Lampung'],
        ];

        foreach ($devices as [$name, $ip, $type, $region, $city]) {
            Device::updateOrCreate(['ip_address' => $ip], compact('name', 'type', 'region', 'city'));
        }
    }
}
