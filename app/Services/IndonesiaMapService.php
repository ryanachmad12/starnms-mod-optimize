<?php

namespace App\Services;

class IndonesiaMapService
{
    // Coordinates are copied exactly from public/assets/indonesia.svg #label_points.
    private const PROVINCES = [
        'aceh' => ['IDAC', 82.9, 45.8], 'sumatera utara' => ['IDSU', 126.7, 83.9], 'sumatra utara' => ['IDSU', 126.7, 83.9],
        'sumatera barat' => ['IDSB', 160.4, 151.3], 'sumatra barat' => ['IDSB', 160.4, 151.3], 'riau' => ['IDRI', 176.1, 125.2],
        'kepulauan riau' => ['IDKR', 306.5, 55.3], 'kepri' => ['IDKR', 306.5, 55.3], 'jambi' => ['IDJA', 199.1, 167.3],
        'sumatera selatan' => ['IDSS', 223, 204.1], 'sumatra selatan' => ['IDSS', 223, 204.1], 'bengkulu' => ['IDBE', 193.8, 204.9],
        'lampung' => ['IDLA', 243.9, 229.4], 'bangka belitung' => ['IDBB', 262.9, 178], 'babel' => ['IDBB', 262.9, 178],
        'banten' => ['IDBT', 265, 262], 'jakarta' => ['IDJK', 279.6, 257], 'dki' => ['IDJK', 279.6, 257],
        'jawa barat' => ['IDJB', 294.6, 270.4], 'jawa tengah' => ['IDJT', 338.4, 279.8], 'yogyakarta' => ['IDYO', 350.1, 290.6],
        'diy' => ['IDYO', 350.1, 290.6], 'jawa timur' => ['IDJI', 384.3, 284.9], 'bali' => ['IDBA', 445.2, 299.8],
        'nusa tenggara barat' => ['IDNB', 487.1, 307.5], 'ntb' => ['IDNB', 487.1, 307.5],
        'nusa tenggara timur' => ['IDNT', 560.4, 306], 'ntt' => ['IDNT', 560.4, 306],
        'kalimantan barat' => ['IDKB', 357, 139.9], 'kalimantan tengah' => ['IDKT', 410.5, 168.6],
        'kalimantan selatan' => ['IDKS', 448.7, 193.7], 'kalimantan timur' => ['IDKI', 469.2, 125.4],
        'kalimantan utara' => ['IDKU', 467.7, 70], 'sulawesi barat' => ['IDSR', 524.1, 190.8],
        'sulawesi selatan' => ['IDSN', 538.9, 205.5], 'sulawesi tenggara' => ['IDSG', 572.9, 203.6],
        'sulawesi tengah' => ['IDST', 571.3, 161], 'gorontalo' => ['IDGO', 586.7, 118.9], 'sulawesi utara' => ['IDSA', 624.1, 120],
        'maluku utara' => ['IDMU', 693.2, 106], 'maluku' => ['IDMA', 728.9, 194.2], 'papua barat' => ['IDPB', 792.6, 160.1],
        'papua' => ['IDPA', 919.8, 213.1],
    ];

    private const CITIES = [
        'jakarta' => 'jakarta', 'tangerang' => 'banten', 'serang' => 'banten', 'bekasi' => 'jawa barat',
        'bandung' => 'jawa barat', 'bogor' => 'jawa barat', 'semarang' => 'jawa tengah', 'solo' => 'jawa tengah',
        'surakarta' => 'jawa tengah', 'yogyakarta' => 'yogyakarta', 'jogja' => 'yogyakarta', 'surabaya' => 'jawa timur',
        'malang' => 'jawa timur', 'denpasar' => 'bali', 'mataram' => 'nusa tenggara barat',
        'kupang' => 'nusa tenggara timur', 'medan' => 'sumatera utara', 'padang' => 'sumatera barat',
        'pekanbaru' => 'riau', 'batam' => 'kepulauan riau', 'palembang' => 'sumatera selatan',
        'pontianak' => 'kalimantan barat', 'palangkaraya' => 'kalimantan tengah', 'banjarmasin' => 'kalimantan selatan',
        'samarinda' => 'kalimantan timur', 'balikpapan' => 'kalimantan timur', 'tarakan' => 'kalimantan utara',
        'makassar' => 'sulawesi selatan', 'manado' => 'sulawesi utara', 'kendari' => 'sulawesi tenggara',
        'palu' => 'sulawesi tengah', 'ambon' => 'maluku', 'ternate' => 'maluku utara',
        'sorong' => 'papua barat', 'jayapura' => 'papua',
    ];

    public function point(string $location): array
    {
        $location = mb_strtolower($location);

        foreach (self::PROVINCES as $name => $point) {
            if (str_contains($location, $name)) return $point;
        }
        foreach (self::CITIES as $city => $province) {
            if (str_contains($location, $city)) return self::PROVINCES[$province];
        }

        return ['ID', 500, 184];
    }

    public function percentage(string $location): array
    {
        [, $x, $y] = $this->point($location);

        // The SVG viewBox is 1000x368. The image occupies 92% x 82% of its map panel.
        return [4 + ($x / 1000 * 92), 9 + ($y / 368 * 82)];
    }
}
