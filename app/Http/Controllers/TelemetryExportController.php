<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SnmpMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;

class TelemetryExportController extends Controller
{
    public function export(Request $request, Project $project)
    {
        $data = $request->validate([
            'device_ids' => ['required', 'array', 'min:1'],
            'device_ids.*' => ['integer'],
            'oids' => ['required', 'array', 'min:1'],
            'oids.*' => ['string', 'max:160'],
            'period' => ['required', 'in:1,6,24,168,720,8760,custom'],
            'start_date' => ['nullable', 'date', 'required_if:period,custom'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'required_if:period,custom'],
        ]);

        $devices = $project->devices()->whereIn('id', $data['device_ids'])->orderBy('name')->get();
        abort_if($devices->isEmpty(), 422, 'Site tidak termasuk dalam project ini.');
        $end = $data['period'] === 'custom' ? Carbon::parse($data['end_date']) : now();
        $start = $data['period'] === 'custom' ? Carbon::parse($data['start_date']) : $end->copy()->subHours((int) $data['period']);
        $metrics = SnmpMetric::query()
            ->whereIn('device_id', $devices->pluck('id'))
            ->whereIn('oid', $data['oids'])
            ->whereBetween('polled_at', [$start, $end])
            ->orderBy('device_id')->orderBy('oid')->orderBy('polled_at')->get()
            ->groupBy(fn ($metric) => $metric->device_id.'|'.$metric->oid);
        $exportNumber = DB::table('telemetry_exports')->insertGetId([
            'project_id' => $project->id,
            'user_id' => $request->user()?->id,
            'exported_at' => now(),
        ]);

        $word = new PhpWord();
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(9);
        $chartFiles = [];
        $entries = collect();
        foreach ($devices as $device) {
            foreach ($data['oids'] as $oid) {
                $rows = $metrics->get($device->id.'|'.$oid, collect());
                if ($rows->isEmpty()) continue;
                $sensor = collect($device->snmp_oids ?? [])->firstWhere('oid', $oid) ?? [];
                $unit = $sensor['unit'] ?? ($oid === 'plugin:ping' ? 'ms' : 'Value');
                $entries->push(compact('device', 'oid', 'rows', 'unit'));
            }
        }

        $totalPages = max(1, $entries->count());
        if ($entries->isEmpty()) {
            $section = $this->addReportSection($word);
            $this->addHeader($section, $project, $exportNumber, $start, $end, 1, 1);
            $section->addText('Tidak ada data telemetry untuk pilihan dan periode ini.', ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER, 'spaceBefore' => 300]);
        } else {
            foreach ($entries as $index => $entry) {
                $section = $this->addReportSection($word);
                $this->addHeader($section, $project, $exportNumber, $start, $end, $index + 1, $totalPages);
                $device = $entry['device']; $rows = $entry['rows']; $oid = $entry['oid']; $unit = $entry['unit'];
                $section->addText($device->name.' ('.$device->ip_address.')', ['bold' => true, 'size' => 12, 'color' => '151568'], ['alignment' => Jc::CENTER, 'spaceBefore' => 150, 'spaceAfter' => 55, 'keepNext' => true]);
                $section->addText(($rows->first()->label ?: $oid).' ('.$unit.') - '.$oid, ['bold' => true, 'size' => 9], ['alignment' => Jc::CENTER, 'spaceAfter' => 60, 'keepNext' => true]);
                $chart = $this->createChart($rows, $rows->first()->label ?: $oid, $unit, $start, $end);
                $chartFiles[] = $chart;
                $section->addImage($chart, ['width' => 680, 'height' => 295, 'alignment' => Jc::CENTER]);
                $section->addText('Jumlah sampel: '.$rows->count().' | Nilai NULL/timeout akan memutus garis grafik.', ['size' => 8, 'color' => '667985'], ['alignment' => Jc::CENTER, 'spaceBefore' => 50]);
            }
        }

        $filename = 'SNMP-'.$project->name.'-'.$end->format('Ymd-His').'.docx';
        $path = tempnam(sys_get_temp_dir(), 'starnms-report-').'.docx';
        IOFactory::createWriter($word, 'Word2007')->save($path);
        foreach ($chartFiles as $chartFile) @unlink($chartFile);
        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function addReportSection(PhpWord $word)
    {
        return $word->addSection([
            'orientation' => 'landscape',
            'pageSizeW' => 15840,
            'pageSizeH' => 12240,
            'marginTop' => 350,
            'marginBottom' => 350,
            'marginLeft' => 600,
            'marginRight' => 600,
            'breakType' => 'nextPage',
        ]);
    }

    private function createChart($rows, string $label, string $unit, Carbon $start, Carbon $end): string
    {
        $width = 1200; $height = 520; $left = 85; $right = 35; $top = 55; $bottom = 75;
        $image = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($image, 248, 251, 253);
        $grid = imagecolorallocate($image, 202, 214, 222);
        $ink = imagecolorallocate($image, 43, 59, 70);
        $line = imagecolorallocate($image, 17, 181, 166);
        $muted = imagecolorallocate($image, 105, 126, 138);
        imagefill($image, 0, 0, $background);

        $step = max(1, (int) ceil($rows->count() / 900));
        $points = $rows->values()->filter(fn ($row, $index) => $index % $step === 0 || $index === $rows->count() - 1)->values();
        $numeric = $points->pluck('numeric_value')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value);
        $min = $numeric->isEmpty() ? 0.0 : (float) $numeric->min();
        $max = $numeric->isEmpty() ? 1.0 : (float) $numeric->max();
        if ($max === $min) { $margin = max(abs($max) * .05, 1); $min -= $margin; $max += $margin; }
        $plotWidth = $width - $left - $right; $plotHeight = $height - $top - $bottom;

        $chartTitle = $label.' ('.$unit.')';
        imagestring($image, 5, (int) (($width - imagefontwidth(5) * strlen($chartTitle)) / 2), 18, $chartTitle, $ink);
        $axisLabel = 'Nilai ('.$unit.')';
        imagestringup($image, 3, 14, (int) ($top + ($plotHeight + imagefontwidth(3) * strlen($axisLabel)) / 2), $axisLabel, $ink);
        for ($i = 0; $i <= 4; $i++) {
            $y = (int) ($top + ($plotHeight * $i / 4));
            imageline($image, $left, $y, $width - $right, $y, $grid);
            $value = number_format($max - (($max - $min) * $i / 4), 2);
            imagestring($image, 3, 8, $y - 7, $value, $muted);
        }

        $previous = null;
        foreach ($points as $index => $row) {
            if ($row->numeric_value === null) { $previous = null; continue; }
            $x = (int) ($left + $plotWidth * ($index / max(1, $points->count() - 1)));
            $y = (int) ($top + $plotHeight - (((float) $row->numeric_value - $min) / ($max - $min) * $plotHeight));
            if ($previous) imageline($image, $previous[0], $previous[1], $x, $y, $line);
            imagefilledellipse($image, $x, $y, 5, 5, $line);
            $previous = [$x, $y];
        }

        foreach ([0, .25, .5, .75, 1] as $ratio) {
            $x = (int) ($left + $plotWidth * $ratio);
            $timestamp = $start->copy()->addSeconds((int) ($start->diffInSeconds($end) * $ratio));
            $text = $timestamp->format($start->diffInHours($end) <= 24 ? 'H:i:s' : 'd-m-Y');
            $labelX = max(5, min($width - (imagefontwidth(3) * strlen($text)) - 5, $x - 30));
            imagestring($image, 3, $labelX, $height - 48, $text, $muted);
        }
        imagestring($image, 3, (int) ($width / 2 - 55), $height - 22, 'Waktu pengukuran', $ink);
        imagerectangle($image, $left, $top, $width - $right, $height - $bottom, $grid);

        $path = tempnam(sys_get_temp_dir(), 'starnms-chart-').'.png';
        imagepng($image, $path);
        imagedestroy($image);
        return $path;
    }

    private function addHeader($section, Project $project, int $exportNumber, Carbon $start, Carbon $end, int $pageNumber, int $totalPages): void
    {
        $table = $section->addTable(['borderSize' => 8, 'borderColor' => '000000', 'cellMargin' => 60, 'layout' => 'fixed', 'alignment' => JcTable::CENTER, 'width' => 14640, 'unit' => 'dxa']);
        $table->addRow(Converter::cmToTwip(0.65));
        $logoCell = $table->addCell(3000, ['vMerge' => 'restart', 'valign' => 'center']);
        $logo = public_path('images/starcom-logo.png');
        if (is_file($logo)) $logoCell->addImage($logo, ['width' => 150, 'height' => 122, 'alignment' => Jc::CENTER]);
        $table->addCell(7140, ['valign' => 'center'])->addText('PT STARCOM SOLUSINDO', ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER]);
        $table->addCell(1500, ['valign' => 'center'])->addText('No. dok', ['size' => 8], ['alignment' => Jc::CENTER]);
        $table->addCell(3000, ['valign' => 'center'])->addText('P-Z-NOC-GRAPH-'.$exportNumber, ['size' => 8], ['alignment' => Jc::CENTER]);
        $table->addRow(Converter::cmToTwip(0.55));
        $table->addCell(null, ['vMerge' => 'continue']);
        $table->addCell(7140, ['valign' => 'center'])->addText(strtoupper($project->name), ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER]);
        $table->addCell(1500, ['valign' => 'center'])->addText('Revisi', ['size' => 8], ['alignment' => Jc::CENTER]);
        $table->addCell(3000, ['valign' => 'center'])->addText('0', ['size' => 8], ['alignment' => Jc::CENTER]);
        $table->addRow(Converter::cmToTwip(0.95));
        $table->addCell(null, ['vMerge' => 'continue']);
        $periodCell = $table->addCell(7140, ['valign' => 'center']);
        $periodCell->addText('PERIODE GENERATE GRAPH SNMP', ['bold' => true, 'size' => 10], ['alignment' => Jc::CENTER, 'spaceAfter' => 20]);
        $periodCell->addText($start->format('d M Y H:i:s').' - '.$end->format('d M Y H:i:s'), ['size' => 8], ['alignment' => Jc::CENTER]);
        $table->addCell(1500, ['valign' => 'center'])->addText('Halaman', ['size' => 8], ['alignment' => Jc::CENTER]);
        $pageCell = $table->addCell(3000, ['valign' => 'center']);
        $pageCell->addText($pageNumber.' dari '.$totalPages, ['size' => 8, 'bold' => true], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(1);
    }
}
