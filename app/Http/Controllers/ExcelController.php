<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelController extends Controller
{
    private const RADIO_TYPES = ['wireless access point','radio link','intracom bs','intracom','intrakom bs','intrakom','telrad bs','telrad','himax 331 v.2','himax 331 v.3','himax 331 v2','himax 331 v3'];
    private const DEVICE_HEADERS = ['Project','LID','Device Name','IP Address','MAC Address','Device Type','Station Role','Sector','Parent Base Station','Height (m)','Region','City','Latitude','Longitude','Service Port','Web Protocol','Web Port','Telnet Port','SSH Port','Realtime Monitoring','Active SLA','SLA Threshold (%)','SNMP Enabled','SNMP Version','SNMP Community','SNMP Port','SNMP Timeout (ms)'];
    private const SENSOR_HEADERS = ['Device IP','Sensor Name','Sensor Type','OID','Unit','Value Type','Multiplication','Division','Alert Mode','Threshold Operator','Threshold Value','Ping Timeout (ms)','Ping Packet Size','Ping Count','Ping Delay (ms)','Ping Method','Ping Auto Acknowledge'];
    private const PROJECT_HEADERS = ['Project Name','Customer Name','Contract Start','Contract End','Location','PM Interval Months','Maintenance Anchor Date','Notes','Active'];

    public function exportDevices(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'device_ids' => ['required', 'array', 'min:1'],
            'device_ids.*' => ['integer', 'exists:devices,id'],
        ]);
        $headers = self::DEVICE_HEADERS;
        $devices = Device::with(['project','baseStation'])
            ->where('project_id', $data['project_id'])
            ->whereIn('id', $data['device_ids'])
            ->orderBy('name')->get();
        if ($devices->isEmpty()) throw ValidationException::withMessages(['device_ids' => 'Pilih minimal satu perangkat untuk diekspor.']);
        $rows = $devices->map(fn ($d) => [
            $d->project?->name,$d->lid,$d->name,$d->ip_address,$d->mac_address,$d->type,$d->station_role,$d->sector,$d->baseStation?->name,$d->height_m,$d->region,$d->city,$d->latitude,$d->longitude,$d->monitor_port,$d->web_protocol,$d->web_port,$d->telnet_port,$d->ssh_port,$d->is_active ? 'YES' : 'NO',$d->sla_enabled ? 'YES' : 'NO',$d->sla_threshold_percentage,$d->snmp_enabled ? 'YES' : 'NO',$d->snmp_version,$d->snmp_community,$d->snmp_port,$d->snmp_timeout_ms,
        ])->all();
        $sensorHeaders = self::SENSOR_HEADERS;
        $sensorRows = $devices->flatMap(fn ($device) => collect($device->snmp_oids ?? [])->map(fn ($sensor) => [
            $device->ip_address,$sensor['label'] ?? '',$sensor['sensor_type'] ?? 'snmp',$sensor['oid'] ?? '',$sensor['unit'] ?? '',$sensor['value_type'] ?? 'gauge',$sensor['multiplier'] ?? 1,$sensor['divisor'] ?? 1,$sensor['change_mode'] ?? 'ignore',$sensor['threshold_operator'] ?? 'gt',$sensor['threshold_value'] ?? null,$sensor['ping_timeout_ms'] ?? null,$sensor['ping_packet_size'] ?? null,$sensor['ping_count'] ?? null,$sensor['ping_delay_ms'] ?? null,$sensor['ping_method'] ?? null,!empty($sensor['ping_auto_ack']) ? 'YES' : 'NO',
        ]))->all();
        $projectName = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $devices->first()->project?->name);
        return $this->download('STARNMS-Devices-'.$projectName.'-'.now()->format('Ymd-His').'.xlsx', 'Devices', $headers, $rows, [['SNMP Sensors',$sensorHeaders,$sensorRows]]);
    }

    public function draftDevices()
    {
        $instructions = [
            ['Petunjuk','Nilai yang diperbolehkan'],
            ['Isi sheet Devices terlebih dahulu. Project harus sudah tersedia di STARNMS.','Station Role: BS atau SS; SNMP Version: 1 atau 2c; Realtime Monitoring, Active SLA, dan SNMP Enabled menggunakan YES atau NO.'],
            ['Untuk perangkat SS, isi Parent Base Station menggunakan nama BS pada project yang sama.','Web Protocol: http atau https; Device Type harus sama dengan pilihan pada form web.'],
            ['Isi sheet SNMP Sensors setelah Devices. Device IP harus sama dengan IP pada sheet Devices.','Sensor Type: ping atau snmp; Ping otomatis memakai OID plugin:ping.'],
        ];
        return $this->download('STARNMS-Draft-Devices.xlsx', 'Devices', self::DEVICE_HEADERS, [], [
            ['SNMP Sensors', self::SENSOR_HEADERS, []],
            ['Instructions', array_shift($instructions), $instructions],
        ]);
    }

    public function importDevices(Request $request)
    {
        $request->validate(['excel_file' => ['required','file','mimes:xlsx,xls','max:10240']]);
        $path = $request->file('excel_file')->getRealPath();
        $rows = $this->rows($path);
        $sensorRows = $this->rows($path, 'SNMP Sensors');
        $count = 0;
        DB::transaction(function () use ($rows, $sensorRows, &$count) {
            foreach ($rows as $line => $row) {
                if (! array_filter($row, fn ($v) => $v !== null && $v !== '')) continue;
                $project = Project::whereRaw('LOWER(name) = ?', [strtolower(trim((string) ($row['project'] ?? '')))])->first();
                if (! $project) throw ValidationException::withMessages(['excel_file' => "Baris $line: Project tidak ditemukan."]);
                foreach (['lid','device_name','ip_address','mac_address','device_type','region','city','latitude','longitude'] as $field) {
                    if (! isset($row[$field]) || trim((string) $row[$field]) === '') throw ValidationException::withMessages(['excel_file' => "Baris $line: kolom $field wajib diisi."]);
                }
                if (in_array(strtolower(trim((string) $row['device_type'])), self::RADIO_TYPES, true) && blank($row['sector'] ?? null)) {
                    throw ValidationException::withMessages(['excel_file' => "Baris $line: Sector wajib untuk tipe radio/wireless."]);
                }
                $role = strtoupper(trim((string) ($row['station_role'] ?? '')));
                if (in_array(strtolower(trim((string) $row['device_type'])), self::RADIO_TYPES, true) && ! in_array($role, ['BS','SS'], true)) throw ValidationException::withMessages(['excel_file' => "Baris $line: Station Role harus BS atau SS."]);
                $parentId = null;
                if ($role === 'SS') {
                    $parentId = Device::where('project_id',$project->id)->whereRaw('LOWER(name) = ?', [strtolower(trim((string) ($row['parent_base_station'] ?? '')))])->value('id');
                    if (! $parentId) throw ValidationException::withMessages(['excel_file' => "Baris $line: Parent Base Station tidak ditemukan. Pastikan baris BS diimpor lebih dahulu."]);
                }
                $deviceIp = trim((string) $row['ip_address']);
                $existingDevice = Device::where('ip_address', $deviceIp)->first();
                $slaEnabled = $this->yes($row['active_sla'] ?? 'NO');
                Device::updateOrCreate(['ip_address' => $deviceIp], [
                    'project_id'=>$project->id,'lid'=>trim((string) $row['lid']),'name'=>trim((string) $row['device_name']),
                    'mac_address'=>trim((string) $row['mac_address']),'type'=>trim((string) $row['device_type']),
                    'station_role'=>$role ?: null,'sector'=>blank($row['sector'] ?? null) ? null : trim((string) $row['sector']),'parent_id'=>$parentId,'height_m'=>$this->number($row['height_m'] ?? null),
                    'region'=>trim((string) $row['region']),'city'=>trim((string) $row['city']),'latitude'=>(float) $row['latitude'],'longitude'=>(float) $row['longitude'],
                    'monitor_port'=>(int) ($row['service_port'] ?: 80),'web_protocol'=>strtolower((string) ($row['web_protocol'] ?: 'http')),
                    'web_port'=>(int) ($row['web_port'] ?: 80),'telnet_port'=>(int) ($row['telnet_port'] ?: 23),'ssh_port'=>(int) ($row['ssh_port'] ?: 22),
                    'is_active'=>$this->yes($row['realtime_monitoring'] ?? 'YES'),
                    'sla_enabled'=>$slaEnabled,
                    'sla_activated_at'=>$slaEnabled ? ($existingDevice?->sla_enabled ? $existingDevice->sla_activated_at : now()) : null,
                    'sla_threshold_percentage'=>$slaEnabled && is_numeric($row['sla_threshold'] ?? null) ? (float) $row['sla_threshold'] : null,
                    'snmp_enabled'=>$this->yes($row['snmp_enabled'] ?? 'NO'),
                    'snmp_version'=>in_array((string) ($row['snmp_version'] ?? '2c'), ['1','2c'], true) ? (string) ($row['snmp_version'] ?? '2c') : '2c','snmp_community'=>(string) ($row['snmp_community'] ?: 'public'),'snmp_port'=>(int) ($row['snmp_port'] ?: 161),
                    'snmp_timeout_ms'=>(int) ($row['snmp_timeout_ms'] ?: 1200),'snmp_status'=>$this->yes($row['snmp_enabled'] ?? 'NO') ? 'unknown' : 'disabled',
                ]);
                $count++;
            }
            collect($sensorRows)->groupBy('device_ip')->each(function ($items, $ip) {
                $device = Device::where('ip_address', trim((string) $ip))->first();
                if (! $device) return;
                $sensors = $items->map(function ($row) {
                    $isPing = strtolower(trim((string) ($row['sensor_type'] ?? ''))) === 'ping' || strtolower(trim((string) ($row['sensor_name'] ?? ''))) === 'ping';
                    return [
                        'sensor_type'=>$isPing ? 'ping' : 'snmp','label'=>$isPing ? 'Ping' : trim((string) $row['sensor_name']),
                        'oid'=>$isPing ? 'plugin:ping' : ltrim(trim((string) $row['oid']),'.'),'unit'=>$isPing ? 'ms' : trim((string) ($row['unit'] ?? 'Value')),
                        'value_type'=>$row['value_type'] ?: 'gauge','multiplier'=>(float) ($row['multiplication'] ?: 1),'divisor'=>(float) ($row['division'] ?: 1),
                        'change_mode'=>$row['alert_mode'] ?: 'ignore','threshold_operator'=>$row['threshold_operator'] ?: 'gt','threshold_value'=>$this->number($row['threshold_value'] ?? null),
                        'ping_timeout_ms'=>min(3000, max(100, (int) ($row['ping_timeout_ms'] ?: 1200))),'ping_packet_size'=>(int) ($row['ping_packet_size'] ?: 32),'ping_count'=>min(3, max(1, (int) ($row['ping_count'] ?: 1))),
                        'ping_delay_ms'=>(int) ($row['ping_delay_ms'] ?: 5),'ping_method'=>$row['ping_method'] ?: 'multiple','ping_auto_ack'=>$this->yes($row['ping_auto_acknowledge'] ?? 'NO'),
                    ];
                })->values()->all();
                $device->update(['snmp_oids'=>$sensors]); $device->syncSnmpSensors();
            });
        });
        return back()->with('success', "$count perangkat berhasil diimpor dari Excel.");
    }

    public function exportProjects()
    {
        $headers = self::PROJECT_HEADERS;
        $rows = Project::orderBy('name')->get()->map(fn ($p) => [$p->name,$p->customer_name,$p->contract_start_date->format('Y-m-d'),$p->contract_end_date->format('Y-m-d'),$p->location,$p->maintenance_interval_months,$p->maintenance_anchor_date->format('Y-m-d'),$p->notes,$p->is_active ? 'YES' : 'NO'])->all();
        return $this->download('STARNMS-Projects-'.now()->format('Ymd-His').'.xlsx', 'Projects', $headers, $rows);
    }

    public function draftProjects()
    {
        $instructions = [
            ['Petunjuk','Nilai yang diperbolehkan'],
            ['Tanggal diisi dengan format YYYY-MM-DD.','PM Interval Months: 3, 6, atau 12.'],
            ['Semua kolom wajib diisi kecuali Notes.','Active: YES atau NO.'],
        ];
        return $this->download('STARNMS-Draft-Projects.xlsx', 'Projects', self::PROJECT_HEADERS, [], [
            ['Instructions', array_shift($instructions), $instructions],
        ]);
    }

    public function importProjects(Request $request)
    {
        $request->validate(['excel_file' => ['required','file','mimes:xlsx,xls','max:10240']]);
        $rows = $this->rows($request->file('excel_file')->getRealPath());
        $count = 0;
        DB::transaction(function () use ($rows, &$count) {
            foreach ($rows as $line => $row) {
                if (! array_filter($row, fn ($v) => $v !== null && $v !== '')) continue;
                foreach (['project_name','customer_name','contract_start','contract_end','location','pm_interval_months','maintenance_anchor_date'] as $field) {
                    if (blank($row[$field] ?? null)) throw ValidationException::withMessages(['excel_file' => "Baris $line: kolom $field wajib diisi."]);
                }
                $interval = (int) $row['pm_interval_months'];
                if (! in_array($interval, [3,6,12], true)) throw ValidationException::withMessages(['excel_file' => "Baris $line: PM Interval harus 3, 6, atau 12."]);
                Project::updateOrCreate(['name'=>trim((string) $row['project_name'])], [
                    'customer_name'=>trim((string) $row['customer_name']),'contract_start_date'=>$this->date($row['contract_start']),
                    'contract_end_date'=>$this->date($row['contract_end']),'location'=>trim((string) $row['location']),
                    'maintenance_interval_months'=>$interval,'maintenance_anchor_date'=>$this->date($row['maintenance_anchor_date']),
                    'notes'=>$row['notes'] ?? null,'is_active'=>$this->yes($row['active'] ?? 'YES'),
                ]);
                $count++;
            }
        });
        return back()->with('success', "$count project berhasil diimpor dari Excel.");
    }

    private function download(string $filename, string $sheetName, array $headers, array $rows, array $extraSheets = [])
    {
        $book = new Spreadsheet(); $sheet = $book->getActiveSheet(); $sheet->setTitle($sheetName);
        $sheet->fromArray($headers, null, 'A1'); if ($rows) $sheet->fromArray($rows, null, 'A2');
        $this->styleSheet($sheet);
        foreach ($extraSheets as [$name,$extraHeaders,$extraRows]) { $extra=$book->createSheet();$extra->setTitle($name);$extra->fromArray($extraHeaders,null,'A1');if($extraRows)$extra->fromArray($extraRows,null,'A2');$this->styleSheet($extra); }
        $path = storage_path('app/'.$filename); (new Xlsx($book))->save($path); $book->disconnectWorksheets();
        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function rows(string $path, ?string $sheetName = null): array
    {
        $book = IOFactory::load($path); $sheet = $sheetName ? $book->getSheetByName($sheetName) : $book->getActiveSheet(); if (! $sheet) return [];
        $data = $sheet->toArray(null, true, true, false); if (! $data) return [];
        $headers = array_map(fn ($h) => strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', (string) $h), '_')), array_shift($data));
        $rows = []; foreach ($data as $index => $values) $rows[$index + 2] = array_combine($headers, array_pad(array_slice($values, 0, count($headers)), count($headers), null));
        return $rows;
    }

    private function styleSheet($sheet): void
    {
        $last = $sheet->getHighestColumn(); $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A1:{$last}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F766E');
        $sheet->freezePane('A2'); $sheet->setAutoFilter("A1:{$last}1"); foreach (range('A',$last) as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    private function yes(mixed $value): bool { return in_array(strtolower(trim((string) $value)), ['1','yes','y','true','active','aktif'], true); }
    private function number(mixed $value): ?float { return blank($value) ? null : (float) $value; }
    private function date(mixed $value): string { return is_numeric($value) ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d') : date('Y-m-d', strtotime((string) $value)); }
}
