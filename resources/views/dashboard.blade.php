<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v=2">
    <link rel="shortcut icon" type="image/png" href="{{ asset('favicon.png') }}?v=2">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}?v=2">
    <title>NMS Starcom · Network Operations</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="operations-app">
<aside class="sidebar" aria-label="Primary navigation">
    <div class="brand starcom-brand"><img src="{{ asset('images/starcom-logo.png') }}" alt="Starcom"><div><strong>NMS STARCOM</strong><small>NETWORK MONITORING</small></div></div>
    <nav>
        <button class="nav-link active" data-view="overview"><span>⌂</span> Overview</button>
        <div class="nav-group open">
            <button class="nav-link nav-parent" type="button" aria-expanded="true"><span>⌘</span> Topology <i>⌄</i></button>
            <div class="nav-submenu"><button class="nav-link nav-child" data-view="topology"><span>⌖</span> Map Monitoring</button></div>
        </div>
        <div class="nav-group open">
            <button class="nav-link nav-parent" type="button" aria-expanded="true"><span>▦</span> Device Management <i>⌄</i></button>
            <div class="nav-submenu"><button class="nav-link nav-child" data-view="devices"><span>▤</span> Devices <b>{{ $stats['total'] }}</b></button></div>
        </div>
        @if(auth()->user()->isAdministrator())<div class="nav-group open"><button class="nav-link nav-parent" type="button" aria-expanded="true"><span>▣</span> Project Management <i>⌄</i></button><div class="nav-submenu"><a class="nav-link nav-child nav-anchor" href="{{ route('projects.index') }}"><span>▤</span> Projects <b>{{ $topologyProjects->count() }}</b></a></div></div>@endif
        @if(auth()->user()->isAdministrator())<a class="nav-link nav-anchor" href="{{ route('users.index') }}"><span>♙</span> User Management</a>@endif
        <a class="nav-link nav-anchor" href="{{ route('about') }}"><span>ⓘ</span> About</a>
    </nav>
    <div class="side-status"><i></i><div><small>MONITOR ENGINE</small><strong>Scheduler active</strong><span id="monitorHeartbeat">Awaiting status</span></div></div>
</aside>

<main data-monitor-batches-url="{{ url('/monitor/batches') }}">
    <header class="app-header">
        <div><p class="eyebrow">NETWORK OPERATIONS / <span id="crumb">OVERVIEW</span></p><h1 id="page-title">Network overview</h1><p class="header-context" id="headerContext">Fleet availability, current incidents, and regional operating state.</p></div>
        <div class="header-actions">
            <div class="device-search"><select id="searchBy" aria-label="Search by"><option value="all">All</option><option value="name">Device Name</option><option value="ip">IP Address</option></select><label class="search">⌕ <input id="globalSearch" placeholder="Search device name or IP..." aria-label="Search device name or IP address"><button type="button" id="clearSearch" title="Clear search">×</button></label></div>
            <details class="alert-center" data-alert-url="{{ route('monitor.alerts') }}"><summary title="Alert notifications">🔔 @if($alerts->count())<b>{{ $alerts->count() }}</b>@endif</summary><div class="alert-panel"><strong>Alert notifications</strong><div class="alert-items">@forelse($alerts as $alert)<article><b>{{ $alert['device'] }}</b><span>{{ $alert['sensor'] }}: {{ $alert['value'] }} {{ $alert['unit'] }}</span><small>{{ \Illuminate\Support\Carbon::parse($alert['time'])->diffForHumans() }}</small></article>@empty<p>Tidak ada threshold alert aktif.</p>@endforelse</div></div></details>
            <button class="theme-toggle" type="button" data-theme-toggle aria-pressed="false"><span data-theme-icon>☀</span><span data-theme-label>Light</span></button>
            <div class="user-menu"><span aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ strtoupper(auth()->user()->role) }}</small></div><form method="POST" action="{{ route('logout') }}">@csrf<button title="Logout" aria-label="Logout">↪</button></form></div>
        </div>
    </header>

    @if(session('success'))<div class="toast">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="toast error">{{ $errors->first() }}</div>@endif

    <section class="view active" id="overview-view">
        <div class="hero-grid">
            <article class="network-health">
                <div class="section-head"><div><p class="eyebrow">FLEET STATUS</p><h2>Network health</h2></div><span class="live"><i></i> LIVE <b id="lastStatusRefresh">Initial snapshot</b></span></div>
                <div class="health-body">
                    @php $availability = $stats['total'] ? round(($stats['online'] / $stats['total']) * 100, 1) : 0; @endphp
                    <div class="gauge" style="--value: {{ $availability * 3.6 }}deg"><div><strong>{{ $availability }}%</strong><small>availability</small></div></div>
                    <div class="health-copy"><strong>{{ $stats['online'] }} of {{ $stats['total'] }}</strong><span>IP devices operational</span><p>Router, switch, firewall, server, radio, access point, dan perangkat IP lainnya dipantau otomatis setiap 60 detik.</p></div>
                </div>
            </article>
            <div class="metric-grid">
                <article class="metric online"><span>●</span><div><small>ONLINE</small><strong data-count="online">{{ $stats['online'] }}</strong><p>Operating normally</p></div></article>
                <article class="metric offline"><span>●</span><div><small>OFFLINE</small><strong data-count="offline">{{ $stats['offline'] }}</strong><p>Needs investigation</p></div></article>
                <article class="metric unknown"><span>●</span><div><small>UNKNOWN</small><strong data-count="unknown">{{ $stats['unknown'] }}</strong><p>Awaiting a result</p></div></article>
                <article class="metric regions"><span>⌖</span><div><small>REGIONS</small><strong>{{ $stats['regions'] }}</strong><p>Active operating areas</p></div></article>
            </div>
        </div>

        <div class="content-grid">
            <article class="panel region-panel">
                <div class="section-head"><div><p class="eyebrow">REGIONAL DISTRIBUTION</p><h2>Perangkat per region</h2></div><button class="text-button" data-switch="topology">Open topology →</button></div>
                <div class="regions">
                    @foreach($devices->groupBy('region')->sortByDesc->count() as $region => $group)
                    <button class="region-row" data-region="{{ $region }}" data-switch="devices">
                        <span class="region-icon">{{ substr($region, 0, 2) }}</span>
                        <span><strong>{{ $region }}</strong><small>{{ $group->first()->city ?: 'Region' }}</small></span>
                        <span class="bar"><i style="width: {{ ($group->count() / max(1, $devices->groupBy('region')->max->count())) * 100 }}%"></i></span>
                        <b>{{ $group->count() }}</b>
                    </button>
                    @endforeach
                </div>
            </article>
            <article class="panel activity-panel">
                <div class="section-head"><div><p class="eyebrow">RECENT SIGNALS</p><h2>Device activity</h2></div><span class="muted">Last checks</span></div>
                <div class="activity">
                    @foreach($devices->sortByDesc('last_checked_at')->take(6) as $device)
                    <div><span class="status-dot {{ $device->status }}"></span><p><strong>{{ $device->name }}</strong><small>{{ $device->ip_address }} · {{ $device->region }}</small></p><time>{{ $device->last_checked_at ? \Illuminate\Support\Carbon::parse($device->last_checked_at)->diffForHumans() : 'Not checked' }}</time></div>
                    @endforeach
                </div>
            </article>
        </div>
    </section>

    <section class="view" id="topology-view">
        <article class="panel indonesia-map-panel" id="indonesiaMapPanel">
            <div class="section-head"><div><p class="eyebrow">GEOGRAPHIC PROJECT VIEW</p><h2>Indonesia project coverage</h2></div><div class="legend"><span><i class="online"></i>Healthy</span><span><i class="offline"></i>Offline present</span><span><i class="unknown"></i>Click marker for project</span></div></div>
            <div class="indonesia-map-layout">
                <div class="indonesia-map" aria-label="Indonesia project map">
                    <img class="indonesia-map-art" src="{{ asset('assets/indonesia.svg') }}" alt="Indonesia">
                    <div class="map-markers" aria-live="polite">
                        @foreach($projectMap as $mapProject)
                            <a class="map-marker {{ $mapProject['offline_count'] ? 'has-offline' : ($mapProject['online_count'] ? 'healthy' : 'unknown') }}" href="{{ route('topology.project', $mapProject['project']) }}" style="left: {{ $mapProject['map_left'] }}%; top: {{ $mapProject['map_top'] }}%" data-project-marker="{{ $mapProject['project']->id }}" aria-label="{{ $mapProject['project']->name }}, {{ $mapProject['device_count'] }} devices">
                                <i></i><span><strong>{{ $mapProject['project']->name }}</strong><small>{{ $mapProject['project']->location ?: 'Indonesia' }} · {{ $mapProject['device_count'] }} devices</small></span>
                            </a>
                        @endforeach
                    </div>
                </div>
                <aside class="map-inspector" id="mapInspector">
                    <p class="eyebrow">PROJECT INSPECTOR</p><h3>Select a project</h3><p>Choose a marker to open its topology, device hierarchy, telemetry, and operational details.</p><div class="map-inspector-metrics"><span><b>{{ $topologyProjects->count() }}</b> Projects</span><span><b>{{ $stats['total'] }}</b> Devices</span><span><b>{{ $stats['offline'] }}</b> Offline</span></div>
                </aside>
            </div>
        </article>
        <article class="panel topology-panel">
            <div class="section-head"><div><p class="eyebrow">LOGICAL MAP · TOPOLOGY BY PROJECT</p><h2>Topology by project</h2></div><div class="legend"><span><i class="online"></i>Online</span><span><i class="offline"></i>Offline</span><span><i class="unknown"></i>Unknown</span></div></div>
            <div class="topology" id="topology">
                <svg class="topology-links" aria-hidden="true"></svg><div class="core-node"><span>◈</span><strong>NMS STARCOM CORE</strong><small>PostgreSQL · ICMP Ping · SNMP v1/v2c</small></div>
                <div class="core-project-trunk"></div>
                <div class="project-topology">
                    @foreach($topologyProjects as $project)
                        @php
                            $projectDevices = $devices->where('project_id', $project->id);
                            $projectOnline = $projectDevices->where('status', 'online')->count();
                            $networkHealth = $projectDevices->count() ? round(($projectOnline / $projectDevices->count()) * 100) : 0;
                            $slaDevices = $projectDevices->where('sla_enabled', true)->filter(fn($device) => $device->sla_percentage !== null);
                            $projectSla = $slaDevices->isEmpty() ? null : round($slaDevices->avg('sla_percentage'), 2);
                            $typeStats = $projectDevices->groupBy('type')->map(fn($items) => ['total'=>$items->count(),'online'=>$items->where('status','online')->count(),'offline'=>$items->where('status','offline')->count()]);
                        @endphp
                        <div class="project-branch">
                            <div class="project-connector"></div>
                            <a class="project-node" data-project-id="{{ $project->id }}" href="{{ route('topology.project', $project) }}">
                                <span>PROJECT</span><strong>{{ $project->name }}</strong><small>{{ $project->customer_name }} · {{ $projectDevices->count() }} devices</small>
                                <div class="project-health"><div><b>NETWORK HEALTH</b><strong>{{ $networkHealth }}%</strong></div><span><i style="width:{{ $networkHealth }}%"></i></span></div>
                                <div class="project-sla-summary"><b>SLA PROJECT · LAST 30 DAYS</b><strong>{{ $projectSla === null ? 'SLA OFF' : number_format($projectSla, 2).'%' }}</strong></div>
                                @if($typeStats->isNotEmpty())<div class="project-type-stats"><div class="stats-head"><b>DEVICE TYPE</b><span>TOTAL</span><i>ON</i><i>OFF</i></div>@foreach($typeStats as $type => $counts)<div><b>{{ $type }}</b><span title="Total">{{ $counts['total'] }}</span><i class="online" title="Online">{{ $counts['online'] }}</i><i class="offline" title="Offline">{{ $counts['offline'] }}</i></div>@endforeach</div>@endif
                            </a>
                        </div>
                    @endforeach
                    @if($devices->whereNull('project_id')->isNotEmpty())
                        <div class="project-branch unassigned"><div class="project-connector"></div><div class="project-node"><span>PROJECT</span><strong>UNASSIGNED</strong><small>{{ $devices->whereNull('project_id')->count() }} devices</small></div></div>
                    @endif
                </div>
            </div>
        </article>
    </section>

    <section class="view" id="devices-view">
        <article class="panel devices-panel">
            <div class="section-head"><div><p class="eyebrow">DEVICE INVENTORY</p><h2>Managed devices <small id="searchResultCount">{{ $devices->count() }} results</small></h2></div>
                <div class="inventory-tools"><select id="regionFilter"><option value="">All regions</option>@foreach($devices->pluck('region')->unique()->sort() as $region)<option>{{ $region }}</option>@endforeach</select>@if(auth()->user()->isAdministrator())<a class="button ghost button-link" href="{{ route('devices.draft') }}">Download Draft</a><button class="button ghost" type="button" id="openDeviceExport">Export Excel</button><button class="button ghost" type="button" id="openDeviceImport">Import Excel</button><button class="button primary" data-open-modal>＋ Add device</button>@endif</div>
            </div>
            <div class="table-wrap"><table><thead class="global-device-columns"><tr><th>Status</th><th>Device</th><th>Project</th><th>IP address</th><th>Type</th><th>Region / City</th><th>SNMP v2</th><th>Latency</th><th>Last check</th><th></th></tr></thead>
                <tbody id="deviceRows">
                @php
                    $deviceProjectGroups = $projects->mapWithKeys(fn($project) => [(string) $project->id => $devices->where('project_id', $project->id)->values()]);
                    if ($devices->whereNull('project_id')->isNotEmpty()) $deviceProjectGroups->put('unassigned', $devices->whereNull('project_id')->values());
                @endphp
                @foreach($deviceProjectGroups as $projectKey => $projectDevices)
                @php
                    $groupProject = $projectKey === 'unassigned' ? null : $projects->firstWhere('id', (int) $projectKey);
                    $groupOnline = $projectDevices->where('status', 'online')->count();
                    $groupOffline = $projectDevices->where('status', 'offline')->count();
                    $groupUnknown = $projectDevices->count() - $groupOnline - $groupOffline;
                @endphp
                <tr class="project-device-group" data-project-group="{{ $projectKey }}" data-expanded="false"><td colspan="10"><button type="button" class="project-group-toggle" aria-expanded="false"><div class="project-group-heading"><div><span>PROJECT</span><strong>{{ $groupProject?->name ?? 'UNASSIGNED' }}</strong><small>{{ $groupProject?->customer_name ?? 'Perangkat tanpa project' }}</small></div><div class="project-group-actions">@if(auth()->user()->isAdministrator() && $groupProject)<span role="button" tabindex="0" class="auto-discovery-trigger" data-project-name="{{ $groupProject->name }}" data-url="{{ route('projects.discover', $groupProject) }}">⌕ Auto Discovery</span>@endif<div class="project-group-summary"><span><b>{{ $projectDevices->count() }}</b> TOTAL</span><span class="online"><b>{{ $groupOnline }}</b> ONLINE</span><span class="offline"><b>{{ $groupOffline }}</b> OFFLINE</span>@if($groupUnknown)<span class="unknown"><b>{{ $groupUnknown }}</b> UNKNOWN</span>@endif</div><i class="project-toggle-icon">⌄</i></div></div></button></td></tr>
                <tr class="project-column-header" data-project-key="{{ $projectKey }}" hidden><th>Status</th><th>Device</th><th>Project</th><th>IP address</th><th>Type</th><th>Region / City</th><th>SNMP v2</th><th>Latency</th><th>Last check</th><th></th></tr>
                @foreach($projectDevices as $device)
                <tr class="device-row" hidden data-project-key="{{ $projectKey }}" data-search="{{ strtolower($device->name.' '.$device->ip_address.' '.$device->region.' '.$device->city.' '.($device->project?->name ?? '')) }}" data-name="{{ strtolower($device->name) }}" data-ip="{{ $device->ip_address }}" data-region="{{ $device->region }}" data-id="{{ $device->id }}">
                    <td><span class="status-pill {{ $device->status }}"><i></i>{{ strtoupper($device->status) }}</span></td>
                    <td><strong>{{ $device->name }}</strong><small>{{ $device->mac_address ?: 'No MAC recorded' }}</small></td>
                    <td><strong>{{ $device->project?->name ?? 'Unassigned' }}</strong><small>{{ $device->project?->customer_name ?? 'No project' }}</small></td>
                    <td><code>{{ $device->ip_address }}</code></td><td>{{ $device->type }}</td>
                    <td><strong>{{ $device->region }}</strong><small>{{ $device->city }}</small></td>
                    <td><span class="snmp-badge {{ $device->snmp_status }}">{{ $device->snmp_enabled ? strtoupper($device->snmp_status) : 'OFF' }}</span><small>{{ count($device->snmp_oids ?? []) }} OID</small></td>
                    <td class="latency">{{ $device->latency_ms ? $device->latency_ms.' ms' : '—' }}</td>
                    <td class="last-check">{{ $device->last_checked_at ? \Illuminate\Support\Carbon::parse($device->last_checked_at)->diffForHumans() : 'Never' }}</td>
                    <td><div class="row-actions">
                        <button class="icon-btn check-device" data-url="{{ route('devices.check', $device) }}" title="Check now">↻</button>
                        @if($device->snmp_enabled)<button class="icon-btn graph-device" data-name="{{ $device->name }}" data-url="{{ route('devices.snmp.metrics', $device) }}" data-poll="{{ route('devices.snmp.poll', $device) }}" title="SNMP graph">⌁</button>@endif
                        @if(auth()->user()->isAdministrator())
                            <button class="icon-btn edit-device" data-device='@json($device)' title="Edit">✎</button>
                            <form method="POST" action="{{ route('devices.destroy', $device) }}" class="delete-device-form" data-device-name="{{ $device->name }}">@csrf @method('DELETE')<button class="icon-btn danger" title="Delete">×</button></form>
                        @endif
                    </div></td>
                </tr>
                @endforeach
                @endforeach
                </tbody>
            </table></div>
        </article>
    </section>
</main>

<dialog id="projectCheckModal" class="selection-modal"><form id="projectCheckForm"><div class="modal-head"><div><p class="eyebrow">ICMP MONITORING</p><h2>Check device of project</h2></div><button type="button" class="close-project-check">×</button></div><p class="selection-help">Perangkat diperiksa satu per satu agar halaman tetap responsif dan polling SNMP tetap berjalan terpisah.</p><label class="selection-field">Project<select id="checkProjectSelect" required><option value="">Pilih project</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }} · {{ $devices->where('project_id',$project->id)->count() }} devices</option>@endforeach</select></label><div id="projectCheckProgress" class="selection-progress" hidden></div><div class="modal-actions"><button type="button" class="button ghost close-project-check">Cancel</button><button type="submit" class="button primary" id="projectCheckSubmit">Start checking</button></div></form></dialog>

@if(auth()->user()->isAdministrator())
<dialog id="deviceExportModal" class="selection-modal wide-selection-modal"><form method="GET" action="{{ route('devices.export') }}" id="deviceExportForm"><div class="modal-head"><div><p class="eyebrow">DEVICE MANAGEMENT</p><h2>Export devices to Excel</h2></div><button type="button" class="close-device-export">×</button></div><label class="selection-field">Pilih project<select name="project_id" id="exportProjectSelect" required><option value="">Pilih project</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select></label><label class="select-all-devices"><input type="checkbox" id="exportSelectAll"> Pilih semua device pada project</label><div class="device-export-list" id="deviceExportList"><p>Pilih project untuk menampilkan perangkat.</p>@foreach($devices as $device)<label data-project-id="{{ $device->project_id }}" hidden><input type="checkbox" name="device_ids[]" value="{{ $device->id }}"><span><b>{{ $device->name }}</b><small>{{ $device->ip_address }} · {{ $device->region }}</small></span></label>@endforeach</div><div class="modal-actions"><button type="button" class="button ghost close-device-export">Cancel</button><button type="submit" class="button primary">Download Excel</button></div></form></dialog>
<dialog id="deviceImportModal" class="selection-modal"><form method="POST" action="{{ route('devices.import') }}" enctype="multipart/form-data">@csrf<div class="modal-head"><div><p class="eyebrow">DEVICE MANAGEMENT</p><h2>Import devices from Excel</h2></div><button type="button" class="close-device-import">×</button></div><p class="selection-help">Gunakan file dari Download Draft. Project pada setiap baris Excel harus sudah tersedia.</p><label class="selection-field">File Excel<input type="file" name="excel_file" accept=".xlsx,.xls" required></label><div class="modal-actions"><button type="button" class="button ghost close-device-import">Cancel</button><button class="button primary">Import Excel</button></div></form></dialog>
@endif

<dialog id="deviceModal">
    <form method="POST" id="deviceForm" action="{{ route('devices.store') }}">@csrf <input type="hidden" name="_method" id="method" value="POST">
        <div class="modal-head"><div><p class="eyebrow">DEVICE MANAGEMENT</p><h2 id="modalTitle">Add device</h2></div><button type="button" class="close-modal">×</button></div>
        <div class="form-grid"><p class="form-required-note"><b>*</b> Semua field bertanda bintang wajib diisi.</p>
            <label class="span-2 required-field">Project<select required name="project_id"><option value="" disabled selected>Pilih project</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }} · {{ $project->customer_name }}</option>@endforeach</select></label>
            <label class="span-2 required-field">Device name<input required name="name" placeholder="ODU INTRACOM SITE NAME"></label>
            <label class="span-2 required-field">LID<input required name="lid" placeholder="Local / Link ID perangkat"></label>
            <label class="required-field">IP address<input required name="ip_address" placeholder="10.162.x.x"></label>
            <label class="required-field">Service port <small>(tidak digunakan untuk status ICMP)</small><input required type="number" min="1" max="65535" name="monitor_port" value="80"></label>
            @php
                $standardDeviceTypes = collect(['Router','Managed Switch','Firewall','Server','Wireless Access Point','Radio Link','OLT','ONU / ONT','IP Camera / CCTV','Network Storage','IoT Gateway','INTRACOM BS','INTRACOM','TELRAD BS','TELRAD','Himax 331 V.2','Himax 331 V.3','Other Network Device']);
                $deviceTypeOptions = $standardDeviceTypes->merge($devices->pluck('type'))->filter()->unique()->values();
            @endphp
            <label class="required-field">Device type<select required name="type"><option value="" disabled selected>Pilih jenis perangkat</option>@foreach($deviceTypeOptions as $deviceType)<option value="{{ $deviceType }}">{{ $deviceType }}</option>@endforeach</select></label>
            <label class="required-field">MAC address<input required name="mac_address" placeholder="Contoh: 00:11:22:33:44:55"></label>
            <div class="conditional-radio-fields span-2" id="radioDeviceFields" hidden>
                <label class="required-field">Peran perangkat<select name="station_role"><option value="">Pilih BS / SS</option><option value="BS">BS · Base Station</option><option value="SS">SS · Subscriber Station</option></select></label>
                <label class="required-field">Sektor<select name="sector"><option value="">Pilih sektor</option><option value="Sektor 1">Sektor 1</option><option value="Sektor 2">Sektor 2</option><option value="Sektor 3">Sektor 3</option><option value="Sektor 4">Sektor 4</option></select></label>
                <label class="base-station-parent" hidden>Base Station induk<select name="parent_id"><option value="">Pilih Base Station</option>@foreach($devices as $baseDevice)<option value="{{ $baseDevice->id }}" data-project="{{ $baseDevice->project_id }}" data-region="{{ $baseDevice->region }}">{{ $baseDevice->name }} · {{ $baseDevice->region }}</option>@endforeach</select></label>
                <label>Ketinggian (meter) <small>Opsional untuk BS maupun SS</small><input type="number" step="0.01" min="0" name="height_m" placeholder="Contoh: 45"></label>
            </div>
            <label class="required-field">Region code<input required name="region" placeholder="JKT"></label>
            <label class="required-field">City<input required name="city" placeholder="Jakarta"></label>
            <label class="required-field">Latitude<input required type="number" step="0.0000001" min="-90" max="90" name="latitude"></label>
            <label class="required-field">Longitude<input required type="number" step="0.0000001" min="-180" max="180" name="longitude"></label>
            <div class="remote-config span-2">
                <div class="snmp-title"><div><strong>Remote Access Ports</strong><small>Custom service ports for this device</small></div></div>
                <div class="remote-port-fields">
                    <label class="required-field">Web protocol<select required name="web_protocol"><option value="http">HTTP</option><option value="https">HTTPS</option></select></label>
                    <label class="required-field">Web port<input required type="number" name="web_port" value="80" min="1" max="65535"></label>
                    <label class="required-field">Telnet port<input required type="number" name="telnet_port" value="23" min="1" max="65535"></label>
                    <label class="required-field">SSH port<input required type="number" name="ssh_port" value="22" min="1" max="65535"></label>
                </div>
            </div>
            <label class="toggle"><input type="checkbox" name="is_active" value="1" checked><span></span> Enable realtime monitoring</label>
            <label class="toggle sla-toggle"><input type="checkbox" name="sla_enabled" value="1"><span></span> Active SLA <small>Mulai dihitung sejak diaktifkan</small></label><label class="sla-threshold-field">SLA threshold (%)<input type="number" name="sla_threshold_percentage" min="0" max="100" step="0.01" placeholder="Contoh: 99.50"><small>Alert aktif ketika SLA turun di bawah nilai ini.</small></label>
            <div class="snmp-config span-2">
                <div class="snmp-title"><div><strong>SNMP Monitoring</strong><small>Simple Network Management Protocol v1 / v2c</small></div><div class="monitoring-toggles"><label class="toggle"><input type="checkbox" name="ping_enabled" value="1"><span></span> Enable ICMP</label><label class="toggle"><input type="checkbox" name="snmp_enabled" value="1"><span></span> Enable SNMP</label></div></div>
                <div class="snmp-fields">
                    <label class="snmp-required-field">Version<select name="snmp_version"><option value="2c">SNMP v2c</option><option value="1">SNMP v1</option></select></label>
                    <label class="snmp-required-field">Community<input name="snmp_community" value="public" autocomplete="off"></label>
                    <label class="snmp-required-field">UDP port<input type="number" name="snmp_port" value="161" min="1" max="65535"></label>
                    <label class="snmp-required-field">Timeout (ms)<input type="number" name="snmp_timeout_ms" value="1200" min="100" max="10000"></label>
                    <div class="custom-oids span-2 snmp-required-field">
                        <div class="custom-oids-head"><div><strong>Custom OID Values</strong><small>Isi Sensor Name <b>Ping</b> untuk memakai OID otomatis bawaan plugin ICMP.</small></div><button type="button" class="button ghost" id="addOidRow">+ Add Sensor</button></div>
                        <div id="oidRows"></div>
                        <p id="oidEmpty">Belum ada sensor. Klik <b>+ Add Sensor</b> untuk menambahkan sensor.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-actions"><button type="button" class="button ghost close-modal">Cancel</button><button class="button primary">Save device</button></div>
    </form>
</dialog>
@if(auth()->user()->isAdministrator())
<dialog id="discoveryModal" class="discovery-modal">
    <form id="discoveryForm" method="POST">@csrf
        <div class="modal-head"><div><p class="eyebrow">DEVICE MANAGEMENT</p><h2>Auto Discovery</h2></div><button type="button" class="close-discovery">×</button></div>
        <div class="discovery-project"><small>PROJECT TUJUAN</small><strong id="discoveryProjectName">Project</strong></div>
        <p class="discovery-help">Masukkan rentang IPv4. Sistem memindai ICMP di backend dan menambahkan IP yang merespons sebagai device draft. Maksimal 256 alamat per proses.</p>
        <div class="discovery-grid"><label>IP address min<input required name="ip_min" inputmode="decimal" placeholder="192.168.1.1"></label><label>IP address max<input required name="ip_max" inputmode="decimal" placeholder="192.168.1.254"></label></div>
        <div id="discoveryResult" class="discovery-result" hidden></div>
        <div class="modal-actions"><button type="button" class="button ghost close-discovery">Cancel</button><button type="submit" class="button primary" id="discoverySubmit">⌕ Scan & Add</button></div>
    </form>
</dialog>
@endif
<script type="application/json" id="initialAlertAudio">@json($alerts->map(fn($alert) => ['key'=>$alert['key'], 'speech'=>$alert['speech']])->values())</script>
<dialog id="remoteModal" class="remote-modal">
    <div class="modal-head"><div><p class="eyebrow">REMOTE ACCESS</p><h2 id="remoteDeviceName">Device</h2></div><button type="button" class="close-remote">×</button></div>
    <div class="remote-device-summary">
        <span class="remote-device-icon" id="remoteDeviceIcon">I</span>
        <div><strong id="remoteDeviceIp">0.0.0.0</strong><small id="remoteDeviceMeta">INTRACOM BS · REGION</small></div>
        <span class="remote-state" id="remoteDeviceState">UNKNOWN</span>
    </div>
    <div class="remote-options">
        <a id="remoteWeb" class="remote-option web" href="#" target="_blank" rel="noopener noreferrer">
            <span>↗</span><div><strong>Web Management</strong><small>Buka halaman perangkat di tab baru</small></div><b id="remoteWebPort">HTTP/80</b>
        </a>
        <a id="remoteTelnet" class="remote-option telnet" href="#">
            <span>⌁</span><div><strong>Telnet</strong><small id="remoteTelnetHelp">Hubungkan terminal ke port 23</small></div><b id="remoteTelnetPort">TCP/23</b>
        </a>
        <a id="remoteSsh" class="remote-option ssh" href="#">
            <span>&gt;_</span><div><strong>SSH</strong><small id="remoteSshHelp">Secure Shell ke perangkat pada port 22</small></div><b id="remoteSshPort">TCP/22</b>
        </a>
        <button id="remoteSnmp" class="remote-option snmp" type="button">
            <span>⌁</span><div><strong>SNMP Graph</strong><small id="remoteSnmpHelp">Tampilkan grafik garis berdasarkan OID</small></div><b id="remoteSnmpPort">UDP/161</b>
        </button>
        <button id="copyDeviceIp" class="remote-option copy" type="button">
            <span>⧉</span><div><strong>Copy IP address</strong><small>Salin alamat untuk aplikasi remote lain</small></div><b id="copyState">COPY</b>
        </button>
    </div>
    <p class="remote-note">Telnet dan SSH memerlukan aplikasi terminal seperti PuTTY atau Windows Terminal yang terdaftar sebagai handler protokol pada Windows.</p>
</dialog>
<dialog id="graphModal" class="graph-modal">
    <div class="modal-head"><div><p class="eyebrow" id="graphProtocol">SNMP TELEMETRY</p><h2 id="graphTitle">SNMP graph</h2></div><button type="button" class="close-graph">×</button></div>
    <div class="graph-toolbar"><select id="oidSelect"></select><select id="periodSelect"><option value="1">Last 1 hour</option><option value="6">Last 6 hours</option><option value="24" selected>Last 24 hours</option><option value="168">Last 7 days</option><option value="720">Last 30 days</option><option value="8760">Last 1 year</option></select><button class="button ghost" id="pollSnmp">↻ Poll now</button></div>
    <div class="chart-wrap"><canvas id="snmpChart" width="900" height="360"></canvas><div id="chartEmpty">Belum ada data SNMP untuk OID ini.</div></div>
    <div class="graph-footer"><span id="graphStatus"></span><span id="graphRange"></span><span id="graphLatest"></span></div>
</dialog>
</body>
</html>
