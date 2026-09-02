import './bootstrap';

const token = document.querySelector('meta[name="csrf-token"]')?.content;
const views = document.querySelectorAll('.view');
const navLinks = document.querySelectorAll('.nav-link[data-view]');
const titles = { overview: 'Network overview', topology: 'Project topology', devices: 'Device inventory' };
const contexts = {
    overview: 'Fleet availability, current incidents, and regional operating state.',
    topology: 'Trace project health and identify fault concentration by region.',
    devices: 'Search, inspect, and operate the managed device inventory.',
};

function switchView(name) {
    views.forEach(v => v.classList.toggle('active', v.id === `${name}-view`));
    navLinks.forEach(n => n.classList.toggle('active', n.dataset.view === name));
    document.querySelector('#page-title').textContent = titles[name];
    document.querySelector('#crumb').textContent = name.toUpperCase();
    document.querySelector('#headerContext').textContent = contexts[name];
    document.querySelector('#headerAddDevice')?.toggleAttribute('hidden', name !== 'devices');
    document.querySelector('.device-search')?.toggleAttribute('hidden', name === 'topology');
}

navLinks.forEach(n => n.addEventListener('click', () => switchView(n.dataset.view)));
const initialView = location.hash.slice(1);
if (titles[initialView]) switchView(initialView);
window.addEventListener('hashchange', () => {
    const view = location.hash.slice(1);
    if (titles[view]) switchView(view);
});
document.querySelectorAll('[data-switch]').forEach(n => n.addEventListener('click', () => {
    switchView(n.dataset.switch);
    if (n.dataset.region) document.querySelector('#regionFilter').value = n.dataset.region;
    filterRows();
}));

const modal = document.querySelector('#deviceModal');
const form = document.querySelector('#deviceForm');
const snmpToggle = form.elements.snmp_enabled;
const slaToggle = form.elements.sla_enabled;
const slaThreshold = form.elements.sla_threshold_percentage;
const oidRows = document.querySelector('#oidRows');
let oidIndex = 0;
function syncSlaThreshold() { slaThreshold.required = slaToggle.checked; slaThreshold.disabled = !slaToggle.checked; if (!slaToggle.checked) slaThreshold.value = ''; }
slaToggle.addEventListener('change', syncSlaThreshold);

function escapeHtml(value = '') {
    return String(value).replace(/[&<>"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[character]));
}

function addOidRow(item = {}) {
    const index = oidIndex++;
    const isPing = item.sensor_type === 'ping' || String(item.label || '').trim().toLowerCase() === 'ping';
    const valueType = item.value_type || 'gauge';
    const changeMode = item.change_mode || 'ignore';
    const thresholdOperator = item.threshold_operator || 'gt';
    oidRows.insertAdjacentHTML('beforeend', `<section class="oid-row">
        <div class="oid-row-title"><strong>SENSOR <span></span><em></em></strong><button type="button" class="remove-oid" title="Hapus sensor">×</button></div>
        <div class="oid-row-fields">
            <label class="sensor-name-field">Sensor name<input class="sensor-name" data-snmp-required name="snmp_oids[${index}][label]" value="${escapeHtml(isPing ? 'Ping' : (item.label || ''))}" placeholder="Ping atau Temperature"></label>
            <label class="oid-wide oid-only">OID value<input data-oid-required name="snmp_oids[${index}][oid]" value="${escapeHtml(item.oid || '')}" placeholder="1.3.6.1.4.1..."></label>
            <label class="oid-only">Unit<input data-oid-required name="snmp_oids[${index}][unit]" value="${escapeHtml(item.unit || 'Value')}" placeholder="C, dBm, Mb/s"></label>
            <label class="oid-only">Value type<select data-oid-required name="snmp_oids[${index}][value_type]"><option value="gauge" ${valueType === 'gauge' ? 'selected' : ''}>Gauge</option><option value="counter" ${valueType === 'counter' ? 'selected' : ''}>Counter</option><option value="delta" ${valueType === 'delta' ? 'selected' : ''}>Delta (Counter/sec)</option></select></label>
            <label class="oid-only">Multiplication<input data-oid-required type="number" step="any" name="snmp_oids[${index}][multiplier]" value="${escapeHtml(item.multiplier ?? 1)}"></label>
            <label class="oid-only">Division<input data-oid-required type="number" step="any" name="snmp_oids[${index}][divisor]" value="${escapeHtml(item.divisor ?? 1)}"></label>
            <label class="oid-full oid-only">Alert notification<span class="oid-change-options"><span><input type="radio" name="snmp_oids[${index}][change_mode]" value="ignore" ${changeMode !== 'notify' ? 'checked' : ''}> Ignore changes</span><span><input type="radio" name="snmp_oids[${index}][change_mode]" value="notify" ${changeMode === 'notify' ? 'checked' : ''}> Trigger notification</span></span><small class="sensor-help">Nilai tetap disimpan dan digrafikkan pada kedua pilihan.</small></label>
            <label class="oid-only threshold-field">Threshold operator<select name="snmp_oids[${index}][threshold_operator]"><option value="gt" ${thresholdOperator === 'gt' ? 'selected' : ''}>Greater than (&gt;)</option><option value="gte" ${thresholdOperator === 'gte' ? 'selected' : ''}>Greater/equal (&gt;=)</option><option value="lt" ${thresholdOperator === 'lt' ? 'selected' : ''}>Less than (&lt;)</option><option value="lte" ${thresholdOperator === 'lte' ? 'selected' : ''}>Less/equal (&lt;=)</option><option value="eq" ${thresholdOperator === 'eq' ? 'selected' : ''}>Equal (=)</option><option value="neq" ${thresholdOperator === 'neq' ? 'selected' : ''}>Not equal (!=)</option></select></label>
            <label class="oid-only threshold-field">Threshold value<input type="number" step="any" name="snmp_oids[${index}][threshold_value]" value="${escapeHtml(item.threshold_value ?? '')}" placeholder="Contoh: 70"></label>
            <div class="ping-only ping-message"><p>Built-in ICMP Ping — Plugin OID dibuat otomatis dan tidak dapat diubah.</p></div>
            <label class="ping-only oid-wide">Plugin OID (Automatic)<input value="plugin:ping" readonly aria-readonly="true"></label>
            <label class="ping-only">Timeout (ms)<input data-ping-plugin-required type="number" name="snmp_oids[${index}][ping_timeout_ms]" value="${escapeHtml(Math.min(3000, Math.max(100, item.ping_timeout_ms ?? 1200)))}" min="100" max="3000"></label>
            <label class="ping-only">Packet size (bytes)<input data-ping-plugin-required type="number" name="snmp_oids[${index}][ping_packet_size]" value="${escapeHtml(item.ping_packet_size ?? 32)}" min="1" max="65500"></label>
            <label class="ping-only ping-method">Ping method<select data-ping-plugin-required name="snmp_oids[${index}][ping_method]"><option value="single" ${(item.ping_method || 'multiple') === 'single' ? 'selected' : ''}>Send one single Ping</option><option value="multiple" ${(item.ping_method || 'multiple') !== 'single' ? 'selected' : ''}>Send multiple Ping requests</option></select></label>
            <label class="ping-only">Ping count<input data-ping-plugin-required type="number" name="snmp_oids[${index}][ping_count]" value="${escapeHtml(Math.min(3, Math.max(1, item.ping_count ?? 1)))}" min="1" max="3"></label>
            <label class="ping-only">Ping delay (ms)<input data-ping-plugin-required type="number" name="snmp_oids[${index}][ping_delay_ms]" value="${escapeHtml(item.ping_delay_ms ?? 5)}" min="0" max="60000"></label>
            <label class="ping-only ping-full ping-auto-ack"><span class="ping-check"><input type="checkbox" name="snmp_oids[${index}][ping_auto_ack]" value="1" ${item.ping_auto_ack ? 'checked' : ''}> Auto acknowledge status error</span><small class="sensor-help">Status Down tetap dicatat; alert langsung ditandai sudah diketahui.</small></label>
            <label class="ping-only ping-full">Ping alert notification<span class="oid-change-options"><span><input type="radio" name="snmp_oids[${index}][change_mode]" value="ignore" ${changeMode !== 'notify' ? 'checked' : ''}> Ignore changes</span><span><input type="radio" name="snmp_oids[${index}][change_mode]" value="notify" ${changeMode === 'notify' ? 'checked' : ''}> Trigger notification</span></span><small class="sensor-help">Latency Ping tetap disimpan dan digrafikkan pada kedua pilihan.</small></label>
            <label class="ping-only">Threshold operator<select name="snmp_oids[${index}][threshold_operator]"><option value="gt" ${thresholdOperator === 'gt' ? 'selected' : ''}>Greater than (&gt;)</option><option value="gte" ${thresholdOperator === 'gte' ? 'selected' : ''}>Greater/equal (&gt;=)</option><option value="lt" ${thresholdOperator === 'lt' ? 'selected' : ''}>Less than (&lt;)</option><option value="lte" ${thresholdOperator === 'lte' ? 'selected' : ''}>Less/equal (&lt;=)</option><option value="eq" ${thresholdOperator === 'eq' ? 'selected' : ''}>Equal (=)</option><option value="neq" ${thresholdOperator === 'neq' ? 'selected' : ''}>Not equal (!=)</option></select></label>
            <label class="ping-only">Threshold value<input type="number" step="any" min="0" name="snmp_oids[${index}][threshold_value]" value="${escapeHtml(item.threshold_value ?? '')}" placeholder="Contoh: 100 ms"></label>
        </div>
    </section>`);
    const row = oidRows.lastElementChild;
    row.querySelector('.remove-oid').addEventListener('click', event => { event.currentTarget.closest('.oid-row').remove(); renumberOidTitles(); syncSnmpRequired(); });
    row.querySelector('.sensor-name').addEventListener('input', () => syncSensorRow(row));
    renumberOidTitles();
    syncSnmpRequired();
}

function renumberOidTitles() {
    oidRows.querySelectorAll('.oid-row-title span').forEach((label, index) => label.textContent = `#${index + 1}`);
}

function renderOidRows(items = []) {
    oidRows.innerHTML = '';
    oidIndex = 0;
    items.forEach(addOidRow);
}

function syncSnmpRequired() {
    const enabled = snmpToggle.checked;
    ['snmp_version', 'snmp_community', 'snmp_port', 'snmp_timeout_ms'].forEach(name => form.elements[name].required = enabled);
    if (enabled && !oidRows.children.length) addOidRow();
    oidRows.querySelectorAll('[data-snmp-required]').forEach(field => field.required = enabled);
    oidRows.querySelectorAll('.oid-row').forEach(syncSensorRow);
    form.querySelector('.snmp-config').classList.toggle('snmp-is-required', enabled);
}
function syncSensorRow(row) {
    const isPing = row.querySelector('.sensor-name').value.trim().toLowerCase() === 'ping';
    row.classList.toggle('ping-plugin', isPing);
    row.querySelector('.oid-row-title em').textContent = isPing ? 'BUILT-IN PING' : 'SNMP OID';
    row.querySelectorAll('[data-oid-required]').forEach(field => field.required = snmpToggle.checked && !isPing);
    row.querySelectorAll('[data-ping-plugin-required]').forEach(field => field.required = snmpToggle.checked && isPing);
    row.querySelectorAll('.oid-only input, .oid-only select').forEach(field => field.disabled = isPing);
    row.querySelectorAll('.ping-only input, .ping-only select').forEach(field => field.disabled = !isPing);
}
snmpToggle.addEventListener('change', syncSnmpRequired);
document.querySelector('#addOidRow').addEventListener('click', () => addOidRow());
document.querySelectorAll('[data-open-modal]').forEach(b => b.addEventListener('click', () => {
    form.reset(); form.action = '/devices'; document.querySelector('#method').value = 'POST';
    renderOidRows([{unit:'Value', value_type:'gauge', multiplier:1, divisor:1, change_mode:'ignore'}]);
    document.querySelector('#modalTitle').textContent = 'Add device'; syncRadioDeviceFields(); syncSnmpRequired(); syncSlaThreshold(); modal.showModal();
}));
document.querySelectorAll('.close-modal').forEach(b => b.addEventListener('click', () => modal.close()));

document.querySelectorAll('.edit-device').forEach(b => b.addEventListener('click', () => {
    const d = JSON.parse(b.dataset.device); form.reset(); form.action = `/devices/${d.id}`;
    document.querySelector('#method').value = 'PUT'; document.querySelector('#modalTitle').textContent = 'Edit device';
    ['project_id','name','lid','sector','station_role','parent_id','height_m','ip_address','mac_address','type','region','city','monitor_port','latitude','longitude','web_protocol','web_port','telnet_port','ssh_port','snmp_version','snmp_community','snmp_port','snmp_timeout_ms'].forEach(k => {
        if (form.elements[k]) form.elements[k].value = d[k] ?? '';
    });
    form.elements.is_active.checked = !!d.is_active;
    form.elements.ping_enabled.checked = !!d.ping_enabled;
    form.elements.sla_enabled.checked = !!d.sla_enabled;
    form.elements.sla_threshold_percentage.value = d.sla_threshold_percentage ?? '';
    form.elements.snmp_enabled.checked = !!d.snmp_enabled;
    renderOidRows(d.snmp_oids || []);
    syncRadioDeviceFields(); syncSnmpRequired(); syncSlaThreshold(); modal.showModal();
}));

const radioDeviceTypes = ['wireless access point','radio link','intracom bs','intracom','intrakom bs','intrakom','telrad bs','telrad','himax 331 v.2','himax 331 v.3','himax 331 v2','himax 331 v3'];
function syncRadioDeviceFields() {
    const isRadio = radioDeviceTypes.includes(String(form.elements.type.value || '').trim().toLowerCase());
    const container = document.querySelector('#radioDeviceFields');
    container.hidden = !isRadio;
    form.elements.sector.required = isRadio;
    form.elements.station_role.required = isRadio;
    if (!isRadio) { form.elements.sector.value = ''; form.elements.station_role.value = ''; form.elements.parent_id.value = ''; form.elements.height_m.value = ''; }
    syncStationRole();
}
form.elements.type.addEventListener('change', syncRadioDeviceFields);
function syncStationRole() {
    const isSubscriber = form.elements.station_role.value === 'SS';
    const parentField = document.querySelector('.base-station-parent');
    parentField.hidden = !isSubscriber;
    form.elements.parent_id.required = isSubscriber;
    if (!isSubscriber) form.elements.parent_id.value = '';
    const project = String(form.elements.project_id.value || '');
    const region = String(form.elements.region.value || '').trim().toLowerCase();
    [...form.elements.parent_id.options].forEach(option => { if (!option.value) return; option.hidden = (project && option.dataset.project !== project) || (region && String(option.dataset.region).toLowerCase() !== region); });
}
form.elements.station_role.addEventListener('change', syncStationRole);
form.elements.project_id.addEventListener('change', syncStationRole);
form.elements.region.addEventListener('input', syncStationRole);

function filterRows() {
    const query = document.querySelector('#globalSearch').value.trim().toLowerCase();
    const searchBy = document.querySelector('#searchBy').value;
    const region = document.querySelector('#regionFilter').value;
    let visible = 0;
    document.querySelectorAll('#deviceRows tr.device-row').forEach(row => {
        const haystack = searchBy === 'name' ? row.dataset.name : searchBy === 'ip' ? row.dataset.ip : row.dataset.search;
        const matches = haystack.includes(query) && (!region || row.dataset.region === region);
        row.dataset.matches = matches ? '1' : '0';
        const group = document.querySelector(`#deviceRows .project-device-group[data-project-group="${row.dataset.projectKey}"]`);
        row.hidden = !matches || group.dataset.expanded !== 'true';
        if (matches) visible++;
    });
    document.querySelectorAll('#deviceRows .project-device-group').forEach(group => {
        const hasMatches = !!document.querySelector(`#deviceRows tr.device-row[data-project-key="${group.dataset.projectGroup}"][data-matches="1"]`);
        const hasDevices = !!document.querySelector(`#deviceRows tr.device-row[data-project-key="${group.dataset.projectGroup}"]`);
        const showEmptyProject = !hasDevices && !query && !region;
        group.hidden = !hasMatches && !showEmptyProject;
        const columns = document.querySelector(`#deviceRows .project-column-header[data-project-key="${group.dataset.projectGroup}"]`);
        columns.hidden = !hasDevices || !hasMatches || group.dataset.expanded !== 'true';
    });
    document.querySelector('#searchResultCount').textContent = `${visible} result${visible === 1 ? '' : 's'}`;
}
document.querySelectorAll('.project-group-toggle').forEach(button => button.addEventListener('click', () => {
    const group = button.closest('.project-device-group');
    const expanded = group.dataset.expanded !== 'true';
    group.dataset.expanded = expanded ? 'true' : 'false';
    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    filterRows();
}));

const discoveryModal = document.querySelector('#discoveryModal');
const discoveryForm = document.querySelector('#discoveryForm');
if (discoveryModal && discoveryForm) {
    const result = document.querySelector('#discoveryResult');
    const submit = document.querySelector('#discoverySubmit');
    const openDiscovery = trigger => {
        discoveryForm.reset();
        discoveryForm.action = trigger.dataset.url;
        document.querySelector('#discoveryProjectName').textContent = trigger.dataset.projectName;
        result.hidden = true;
        result.className = 'discovery-result';
        result.textContent = '';
        discoveryModal.showModal();
    };
    document.querySelectorAll('.auto-discovery-trigger').forEach(trigger => {
        trigger.addEventListener('click', event => { event.preventDefault(); event.stopPropagation(); openDiscovery(trigger); });
        trigger.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); event.stopPropagation(); openDiscovery(trigger); }
        });
    });
    document.querySelectorAll('.close-discovery').forEach(button => button.addEventListener('click', () => discoveryModal.close()));
    discoveryForm.addEventListener('submit', async event => {
        event.preventDefault();
        submit.disabled = true;
        submit.textContent = 'Scanning IP...';
        result.hidden = false;
        result.className = 'discovery-result';
        result.textContent = 'Pemindaian ICMP sedang berjalan di backend. Halaman tetap dapat digunakan.';
        try {
            const response = await fetch(discoveryForm.action, {
                method: 'POST', body: new FormData(discoveryForm),
                headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            });
            const data = await response.json();
            if (!response.ok) {
                const validation = data.errors ? Object.values(data.errors).flat().join(' ') : '';
                throw new Error(validation || data.message || 'Auto Discovery gagal dijalankan.');
            }
            result.innerHTML = `<strong>${escapeHtml(data.message)}</strong><br>${data.scanned} IP dipindai · ${data.skipped_existing} IP sudah terdaftar.`;
            submit.textContent = 'Selesai';
            setTimeout(() => window.location.reload(), 1400);
        } catch (error) {
            result.classList.add('error');
            result.textContent = error.message;
            submit.disabled = false;
            submit.textContent = '⌕ Scan & Add';
        }
    });
}

const projectCheckModal = document.querySelector('#projectCheckModal');
const projectCheckForm = document.querySelector('#projectCheckForm');
const monitoringBatchUrl = document.querySelector('main')?.dataset.monitorBatchesUrl;

async function queueProjectCheck(projectId, progress, submit = null) {
    const response = await fetch('/monitor/check-all', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ project_id: projectId || null }),
    });
    const batch = await response.json();
    if (!response.ok) throw new Error(batch.message || 'Pemeriksaan tidak dapat dimasukkan ke antrean.');

    if (progress) {
        progress.hidden = false;
        progress.textContent = `Queued ${batch.total_devices} devices. Waiting for monitoring workers...`;
    }
    if (submit) submit.textContent = 'Queued';

    const poll = async () => {
        const stateResponse = await fetch(`${monitoringBatchUrl}/${batch.id}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (!stateResponse.ok) throw new Error('Batch monitoring tidak dapat dibaca.');
        const state = await stateResponse.json();
        if (progress) progress.textContent = `Checking ${state.completed_devices + state.failed_devices} of ${state.total_devices} devices${state.failed_devices ? ` · ${state.failed_devices} failed` : ''}.`;
        if (['completed', 'completed_with_errors'].includes(state.status)) {
            if (progress) progress.textContent = state.failed_devices
                ? `Completed with ${state.failed_devices} failed device checks.`
                : `Completed ${state.completed_devices} device checks.`;
            if (submit) { submit.disabled = false; submit.textContent = 'Check again'; }
            await refreshStatuses();
            return;
        }
        window.setTimeout(poll, 1200);
    };
    window.setTimeout(poll, 800);
}

function refreshDeviceGroupSummary(projectKey) {
    const group = document.querySelector(`.project-device-group[data-project-group="${projectKey}"]`);
    if (!group) return;
    const rows = [...document.querySelectorAll(`tr.device-row[data-project-key="${projectKey}"]`)];
    const online = rows.filter(row => row.querySelector('.status-pill')?.classList.contains('online')).length;
    const offline = rows.filter(row => row.querySelector('.status-pill')?.classList.contains('offline')).length;
    const unknown = rows.length - online - offline;
    group.querySelector('.project-group-summary span:nth-child(1) b')?.replaceChildren(String(rows.length));
    group.querySelector('.project-group-summary span.online b')?.replaceChildren(String(online));
    group.querySelector('.project-group-summary span.offline b')?.replaceChildren(String(offline));
    const unknownBadge = group.querySelector('.project-group-summary span.unknown');
    if (unknownBadge) { unknownBadge.hidden = unknown === 0; unknownBadge.querySelector('b').textContent = String(unknown); }
}
if (projectCheckModal && projectCheckForm) {
    const projectSelect = document.querySelector('#checkProjectSelect');
    const progress = document.querySelector('#projectCheckProgress');
    const submit = document.querySelector('#projectCheckSubmit');
    document.querySelector('#openProjectCheck')?.addEventListener('click', () => { progress.hidden = true; projectCheckModal.showModal(); });
    document.querySelectorAll('.project-device-group[data-project-group]').forEach(group => {
        const projectId = group.dataset.projectGroup;
        if (projectId === 'unassigned') return;
        const actions = group.querySelector('.project-group-actions');
        if (!actions || actions.querySelector('.project-check-trigger')) return;
        const trigger = document.createElement('span');
        trigger.className = 'project-check-trigger';
        trigger.tabIndex = 0;
        trigger.textContent = '↻ Check devices';
        trigger.dataset.projectId = projectId;
        trigger.dataset.checking = '0';
        trigger.addEventListener('click', async event => {
            event.preventDefault(); event.stopPropagation();
            if (trigger.dataset.checking === '1') return;
            trigger.dataset.checking = '1';
            trigger.textContent = '… Queueing checks';
            try {
                await queueProjectCheck(projectId, null);
                trigger.textContent = '✓ Checks queued';
                window.setTimeout(() => { trigger.textContent = '↻ Check devices'; }, 1800);
            } catch (error) {
                trigger.textContent = '↻ Check devices';
                window.alert(error.message);
            } finally {
                trigger.dataset.checking = '0';
            }
        });
        trigger.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') trigger.click(); });
        actions.prepend(trigger);
    });
    document.querySelectorAll('.close-project-check').forEach(button => button.addEventListener('click', () => projectCheckModal.close()));
    projectCheckForm.addEventListener('submit', async event => {
        event.preventDefault();
        submit.disabled = true;
        try {
            await queueProjectCheck(projectSelect.value, progress, submit);
        } catch (error) {
            progress.hidden = false;
            progress.textContent = error.message;
            submit.disabled = false;
            submit.textContent = 'Start checking';
        }
    });
}

const deviceExportModal = document.querySelector('#deviceExportModal');
if (deviceExportModal) {
    const projectSelect = document.querySelector('#exportProjectSelect');
    const selectAll = document.querySelector('#exportSelectAll');
    const labels = [...document.querySelectorAll('#deviceExportList label[data-project-id]')];
    const syncExportDevices = () => {
        let visible = 0;
        labels.forEach(label => {
            const show = !!projectSelect.value && label.dataset.projectId === projectSelect.value;
            label.hidden = !show;
            label.querySelector('input').disabled = !show;
            if (!show) label.querySelector('input').checked = false;
            if (show) visible++;
        });
        document.querySelector('#deviceExportList > p').hidden = visible > 0;
        selectAll.checked = false;
    };
    document.querySelector('#openDeviceExport')?.addEventListener('click', () => { projectSelect.value = ''; syncExportDevices(); deviceExportModal.showModal(); });
    document.querySelectorAll('.close-device-export').forEach(button => button.addEventListener('click', () => deviceExportModal.close()));
    projectSelect.addEventListener('change', syncExportDevices);
    selectAll.addEventListener('change', () => labels.filter(label => !label.hidden).forEach(label => label.querySelector('input').checked = selectAll.checked));
    document.querySelector('#deviceExportForm').addEventListener('submit', event => {
        if (!labels.some(label => !label.hidden && label.querySelector('input').checked)) {
            event.preventDefault();
            window.alert('Pilih minimal satu device untuk diekspor.');
        }
    });
}

const deviceImportModal = document.querySelector('#deviceImportModal');
if (deviceImportModal) {
    document.querySelector('#openDeviceImport')?.addEventListener('click', () => deviceImportModal.showModal());
    document.querySelectorAll('.close-device-import').forEach(button => button.addEventListener('click', () => deviceImportModal.close()));
}
document.querySelector('#globalSearch').addEventListener('input', () => { switchView('devices'); filterRows(); });
document.querySelector('#searchBy').addEventListener('change', () => { switchView('devices'); filterRows(); document.querySelector('#globalSearch').focus(); });
document.querySelector('#clearSearch').addEventListener('click', () => { document.querySelector('#globalSearch').value = ''; filterRows(); document.querySelector('#globalSearch').focus(); });
document.querySelector('#regionFilter').addEventListener('change', filterRows);

function refreshTopologyCards(statuses) {
    const total = statuses.length;
    const onlineTotal = statuses.filter(device => device.status === 'online').length;
    const offlineTotal = statuses.filter(device => device.status === 'offline').length;
    const unknownTotal = total - onlineTotal - offlineTotal;
    document.querySelector('[data-count="total"]')?.replaceChildren(String(total));
    document.querySelector('.nav-submenu [data-view="devices"] b')?.replaceChildren(String(total));
    document.querySelector('[data-count="online"]')?.replaceChildren(String(onlineTotal));
    document.querySelector('[data-count="offline"]')?.replaceChildren(String(offlineTotal));
    document.querySelector('[data-count="unknown"]')?.replaceChildren(String(unknownTotal));
    document.querySelector('[data-fleet-health]')?.replaceChildren(`${onlineTotal} of ${total}`);
    document.querySelector('.health-copy strong')?.replaceChildren(`${onlineTotal} of ${total}`);
    const gauge = document.querySelector('[data-fleet-gauge], .gauge');
    if (gauge) {
        const availability = total ? Math.round((onlineTotal / total) * 1000) / 10 : 0;
        gauge.style.setProperty('--value', `${availability * 3.6}deg`);
        gauge.querySelector('strong').textContent = `${availability}%`;
    }
    document.querySelectorAll('.project-node[data-project-id]').forEach(node => {
        const projectId = String(node.dataset.projectId);
        const rows = statuses.filter(device => String(device.project_id) === projectId);
        if (!rows.length) return;
        const online = rows.filter(device => device.status === 'online').length;
        const offline = rows.filter(device => device.status === 'offline').length;
        const health = Math.round((online / rows.length) * 100);
        const healthValue = node.querySelector('.project-health strong');
        const healthBar = node.querySelector('.project-health i');
        if (healthValue) healthValue.textContent = `${health}%`;
        if (healthBar) healthBar.style.width = `${health}%`;
    });
}

function formatRefreshTime(date = new Date()) {
    return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
}

function updateRefreshState(state, detail = '') {
    const refresh = document.querySelector('#lastStatusRefresh');
    const heartbeat = document.querySelector('#monitorHeartbeat');
    if (refresh) refresh.textContent = detail || state;
    if (heartbeat) heartbeat.textContent = state;
}

document.querySelectorAll('.refresh-all-button').forEach(button => button.addEventListener('click', async () => {
    if (button.dataset.running === '1') return;
    button.dataset.running = '1';
    try {
        const response = await fetch(button.dataset.checkAllUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        });
        if (!response.ok) throw new Error('Tidak dapat mengambil data monitoring terbaru.');
        const snapshot = await response.json();
        for (const device of (snapshot.devices || [])) {
            const row = document.querySelector(`tr.device-row[data-id="${device.id}"]`);
            if (row) {
                const pill = row.querySelector('.status-pill');
                pill.className = `status-pill ${device.status}`;
                pill.innerHTML = `<i></i>${String(device.status).toUpperCase()}`;
                row.querySelector('.latency').textContent = device.latency_ms === null ? '—' : `${device.latency_ms} ms`;
                row.querySelector('.last-check').textContent = device.last_check_human;
                row.classList.add('row-refreshed');
                setTimeout(() => row.classList.remove('row-refreshed'), 450);
            }
            const node = document.querySelector(`.device-node[data-device-id="${device.id}"]`);
            if (node) node.className = `device-node ${device.status}`;
            await new Promise(resolve => setTimeout(resolve, 70));
        }
        refreshTopologyCards(snapshot.devices || []);
        await new Promise(resolve => setTimeout(resolve, 180));
    } finally {
        window.location.reload();
    }
}));

document.querySelectorAll('.delete-device-form').forEach(deleteForm => deleteForm.addEventListener('submit', event => {
    const deviceName = deleteForm.dataset.deviceName || 'perangkat ini';
    const confirmed = window.confirm(`Yakin akan menghapus device "${deviceName}"?\n\nDevice akan dihapus dari Device Management dan seluruh Map Monitoring.`);
    if (!confirmed) event.preventDefault();
}));

document.querySelectorAll('.check-device').forEach(button => button.addEventListener('click', async () => {
    button.classList.add('spinning');
    try {
        const response = await fetch(button.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } });
        const data = await response.json();
        const row = button.closest('tr'); const pill = row.querySelector('.status-pill');
        pill.className = `status-pill ${data.status}`; pill.innerHTML = `<i></i>${data.status.toUpperCase()}`;
        row.querySelector('.latency').textContent = data.latency_ms ? `${data.latency_ms} ms` : '—';
        row.querySelector('.last-check').textContent = 'just now';
        refreshDeviceGroupSummary(row.dataset.projectKey);
    } finally { button.classList.remove('spinning'); }
}));

document.querySelectorAll('.map-marker').forEach(marker => marker.addEventListener('mouseenter', () => {
    document.querySelectorAll('.map-marker').forEach(item => item.classList.toggle('is-focused', item === marker));
}));

let statusRefreshInFlight = false;
let statusRefreshTimer = null;
let statusSince = null;
const knownDeviceStatuses = new Map();

function relativeCheckTime(value) {
    if (!value) return 'Never';
    const checkedAt = new Date(value);
    if (Number.isNaN(checkedAt.getTime())) return 'Unknown';
    const seconds = Math.max(0, Math.round((Date.now() - checkedAt.getTime()) / 1000));
    if (seconds < 10) return 'just now';
    if (seconds < 60) return `${seconds} seconds ago`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)} minutes ago`;
    return `${Math.floor(seconds / 3600)} hours ago`;
}

async function refreshStatuses() {
    if (document.hidden || statusRefreshInFlight) return;
    statusRefreshInFlight = true;
    try {
        const url = new URL('/api/status', window.location.origin);
        if (statusSince) url.searchParams.set('since', statusSince);
        const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const payload = await response.json();
        const statuses = payload.devices || [];
        statuses.forEach(device => knownDeviceStatuses.set(device.id, device));
        statuses.forEach(d => {
            const row = document.querySelector(`tr[data-id="${d.id}"]`); if (!row) return;
            const pill = row.querySelector('.status-pill');
            pill.className = `status-pill ${d.status}`; pill.innerHTML = `<i></i>${d.status.toUpperCase()}`;
            row.querySelector('.latency').textContent = d.latency_ms ? `${d.latency_ms} ms` : '—';
            const lastCheck = row.querySelector('.last-check');
            if (lastCheck) lastCheck.textContent = relativeCheckTime(d.last_checked_at);
            const node = document.querySelector(`.device-node[data-device-id="${d.id}"]`);
            if (node) node.className = `device-node ${d.status}`;
        });
        refreshTopologyCards([...knownDeviceStatuses.values()]);
        statusSince = payload.server_time || new Date().toISOString();
        updateRefreshState('Monitoring connected', `Updated ${formatRefreshTime()}`);
    } catch (_) {
        updateRefreshState('Monitoring reconnecting', 'Last refresh unavailable');
    } finally {
        statusRefreshInFlight = false;
    }
}

function scheduleStatusRefresh(delay = 5000) {
    window.clearTimeout(statusRefreshTimer);
    statusRefreshTimer = window.setTimeout(async () => {
        await refreshStatuses();
        scheduleStatusRefresh(document.hidden ? 30000 : 5000);
    }, delay);
}

document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        refreshStatuses();
        scheduleStatusRefresh();
    }
});

refreshStatuses();
scheduleStatusRefresh();

const alertCenter = document.querySelector('.alert-center');
const initialAlertAudio = document.querySelector('#initialAlertAudio');
if (alertCenter && initialAlertAudio && 'speechSynthesis' in window) {
    const storageKey = 'starnms-spoken-alerts-v1';
    let spoken = [];
    try { spoken = JSON.parse(localStorage.getItem(storageKey) || '[]'); } catch (_) {}
    let audioUnlocked = false;
    let latestAlerts = [];
    try { latestAlerts = JSON.parse(initialAlertAudio.textContent || '[]'); } catch (_) {}

    const remember = key => {
        spoken = [...new Set([...spoken, key])].slice(-200);
        localStorage.setItem(storageKey, JSON.stringify(spoken));
    };
    const speakNewAlerts = alerts => {
        latestAlerts = alerts;
        if (!audioUnlocked) return;
        alerts.filter(alert => !spoken.includes(alert.key)).forEach(alert => {
            const utterance = new SpeechSynthesisUtterance(alert.speech);
            utterance.lang = 'id-ID'; utterance.rate = 0.95; utterance.pitch = 1;
            window.speechSynthesis.speak(utterance);
            remember(alert.key);
        });
    };
    const unlockAudio = () => { audioUnlocked = true; speakNewAlerts(latestAlerts); };
    document.addEventListener('pointerdown', unlockAudio, { once: true });
    document.addEventListener('keydown', unlockAudio, { once: true });

    let alertRefreshInFlight = false;
    const refreshAlerts = async () => {
        if (document.hidden || alertRefreshInFlight) return;
        alertRefreshInFlight = true;
        try {
            const response = await fetch(alertCenter.dataset.alertUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const payload = await response.json();
            const alerts = payload.alerts || [];
            const summary = alertCenter.querySelector('summary');
            summary.innerHTML = `🔔${alerts.length ? ` <b>${alerts.length}</b>` : ''}`;
            const items = alertCenter.querySelector('.alert-items');
            items.innerHTML = alerts.length ? alerts.map(alert => `<article><b>${escapeHtml(alert.device)}</b><span>${escapeHtml(alert.sensor)}: ${escapeHtml(alert.value)} ${escapeHtml(alert.unit)}</span><small>${escapeHtml(alert.time_human)}</small></article>`).join('') : '<p>Tidak ada threshold alert aktif.</p>';
            speakNewAlerts(alerts);
        } catch (_) {
        } finally {
            alertRefreshInFlight = false;
        }
    };
    speakNewAlerts(latestAlerts);
    window.setInterval(refreshAlerts, 10000);
}

const remoteModal = document.querySelector('#remoteModal');
let selectedRemoteIp = '';
let selectedRemoteNode = null;
document.querySelectorAll('.device-node').forEach(node => node.addEventListener('click', () => {
    selectedRemoteNode = node;
    selectedRemoteIp = node.dataset.deviceIp;
    document.querySelector('#remoteDeviceName').textContent = node.dataset.deviceName;
    document.querySelector('#remoteDeviceIp').textContent = selectedRemoteIp;
    document.querySelector('#remoteDeviceMeta').textContent = `${node.dataset.deviceType} · ${node.dataset.deviceRegion}`;
    document.querySelector('#remoteDeviceIcon').textContent = node.textContent.trim();
    const state = ['online', 'offline', 'unknown'].find(s => node.classList.contains(s)) || 'unknown';
    const stateEl = document.querySelector('#remoteDeviceState');
    stateEl.textContent = state.toUpperCase(); stateEl.className = `remote-state ${state}`;
    const webProtocol = node.dataset.webProtocol || 'http';
    const webPort = node.dataset.webPort || (webProtocol === 'https' ? '443' : '80');
    const telnetPort = node.dataset.telnetPort || '23';
    const sshPort = node.dataset.sshPort || '22';
    document.querySelector('#remoteWeb').href = `${webProtocol}://${selectedRemoteIp}:${webPort}`;
    document.querySelector('#remoteTelnet').href = `telnet://${selectedRemoteIp}:${telnetPort}`;
    document.querySelector('#remoteSsh').href = `ssh://${selectedRemoteIp}:${sshPort}`;
    document.querySelector('#remoteWebPort').textContent = `${webProtocol.toUpperCase()}/${webPort}`;
    document.querySelector('#remoteTelnetPort').textContent = `TCP/${telnetPort}`;
    document.querySelector('#remoteSshPort').textContent = `TCP/${sshPort}`;
    document.querySelector('#remoteTelnetHelp').textContent = `Hubungkan terminal ke port ${telnetPort}`;
    document.querySelector('#remoteSshHelp').textContent = `Secure Shell ke perangkat pada port ${sshPort}`;
    const snmpPort = node.dataset.snmpPort || '161';
    const snmpEnabled = node.dataset.snmpEnabled === '1';
    document.querySelector('#remoteSnmpPort').textContent = `UDP/${snmpPort}`;
    document.querySelector('#remoteSnmpHelp').textContent = snmpEnabled
        ? `Grafik OID · status ${node.dataset.snmpStatus || 'unknown'}`
        : 'SNMP belum diaktifkan pada perangkat ini';
    document.querySelector('#remoteSnmp').classList.toggle('disabled-service', !snmpEnabled);
    document.querySelector('#copyState').textContent = 'COPY';
    remoteModal.showModal();
}));
document.querySelector('.close-remote').addEventListener('click', () => remoteModal.close());
document.querySelector('#remoteSnmp').addEventListener('click', async () => {
    if (!selectedRemoteNode) return;
    remoteModal.close();
    await openGraph(selectedRemoteNode.dataset.deviceName, selectedRemoteNode.dataset.snmpMetricsUrl, selectedRemoteNode.dataset.snmpPollUrl);
});
document.querySelector('#copyDeviceIp').addEventListener('click', async () => {
    try {
        await navigator.clipboard.writeText(selectedRemoteIp);
        document.querySelector('#copyState').textContent = 'COPIED';
    } catch (_) {
        const input = document.createElement('input'); input.value = selectedRemoteIp;
        document.body.appendChild(input); input.select(); document.execCommand('copy'); input.remove();
        document.querySelector('#copyState').textContent = 'COPIED';
    }
});

const graphModal = document.querySelector('#graphModal');
const canvas = document.querySelector('#snmpChart');
const ctx = canvas.getContext('2d');
let graphUrl = '', pollUrl = '', graphData = [];

async function openGraph(name, metricsUrl, pollingUrl) {
    graphUrl = metricsUrl; pollUrl = pollingUrl;
    document.querySelector('#graphTitle').textContent = name;
    graphModal.showModal(); await loadGraph(false);
}
document.querySelectorAll('.graph-device').forEach(button => button.addEventListener('click', () => openGraph(button.dataset.name, button.dataset.url, button.dataset.poll)));
document.querySelector('.close-graph').addEventListener('click', () => graphModal.close());
document.querySelector('#periodSelect').addEventListener('change', showGraphPrompt);
document.querySelector('#oidSelect').addEventListener('change', showGraphPrompt);
document.querySelector('#pollSnmp').addEventListener('click', async () => {
    const button = document.querySelector('#pollSnmp'); button.classList.add('spinning');
    const requestedOid = document.querySelector('#oidSelect').value;
    try {
        const response = await fetch(pollUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } });
        const result = await response.json();
        document.querySelector('#graphStatus').textContent = result.message || `SNMP ${result.status}`;
        await loadGraph(true, requestedOid);
    } finally { button.classList.remove('spinning'); }
});

function showGraphPrompt() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const empty = document.querySelector('#chartEmpty');
    empty.textContent = 'Klik Poll now untuk menampilkan grafik sensor dan periode yang dipilih.';
    empty.style.display = 'grid';
    document.querySelector('#graphLatest').textContent = '';
    const period = document.querySelector('#periodSelect');
    document.querySelector('#graphRange').textContent = period.options[period.selectedIndex].text;
}

async function loadGraph(drawGraph = true, requestedOid = null) {
    const hours = document.querySelector('#periodSelect').value;
    let response = await fetch(`${graphUrl}?hours=${hours}`, {headers:{Accept:'application/json'}, cache:'no-store'});
    if (!response.ok) throw new Error(`Metric request gagal (${response.status})`);
    let data = await response.json();
    graphData = data.series || [];
    const select = document.querySelector('#oidSelect');
    const previous = requestedOid || select.value;
    select.innerHTML = graphData.map(s => `<option value="${escapeHtml(s.oid)}">${s.label} (${s.unit || 'Value'}) · ${s.oid}</option>`).join('');
    if ([...select.options].some(o => o.value === previous)) select.value = previous;
    document.querySelector('#graphStatus').textContent = `SNMP ${data.device.snmp_status.toUpperCase()} · ${data.device.ip_address}`;
    document.querySelector('#graphProtocol').textContent = `SNMP V${String(data.device.snmp_version || '2c').toUpperCase()} TELEMETRY`;
    const period = document.querySelector('#periodSelect');
    document.querySelector('#graphRange').textContent = period.options[period.selectedIndex].text;
    drawGraph ? drawSelectedSeries() : showGraphPrompt();
}

function drawSelectedSeries() {
    const selectedOid = document.querySelector('#oidSelect').value;
    const series = graphData.find(item => item.oid === selectedOid);
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const empty = document.querySelector('#chartEmpty');
    if (!series || !series.points.length) { empty.textContent = 'Belum ada data untuk sensor ini.'; empty.style.display = 'grid'; return; }
    empty.style.display = 'none';
    const samples = series.points;
    const points = samples.map((point, sourceIndex) => ({...point, sourceIndex})).filter(p => p.value !== null);
    if (!points.length) {
        const latestRaw = series.points[series.points.length - 1]?.raw || '';
        empty.textContent = series.oid === 'plugin:ping' ? `Ping belum menghasilkan latency: ${latestRaw || 'perangkat tidak merespons ICMP.'}` : 'Sensor belum menghasilkan nilai numerik.';
        document.querySelector('#graphLatest').textContent = latestRaw;
        empty.style.display = 'grid'; return;
    }
    const values = points.map(p => Number(p.value)); const min = Math.min(...values); const max = Math.max(...values);
    const margin = max === min ? Math.max(Math.abs(max) * .05, 1) : 0;
    const chartMin = min - margin, chartMax = max + margin;
    const pad = 45, width = canvas.width - pad * 2, height = canvas.height - pad - 55;
    ctx.strokeStyle = '#1e303c'; ctx.fillStyle = '#738996'; ctx.font = '12px monospace'; ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
        const y = pad + height * i / 4; ctx.beginPath(); ctx.moveTo(pad, y); ctx.lineTo(canvas.width - pad, y); ctx.stroke();
        ctx.fillText((chartMax - (chartMax - chartMin) * i / 4).toFixed(2), 4, y + 4);
    }
    ctx.strokeStyle = '#20d3c2'; ctx.lineWidth = 3; ctx.shadowColor = '#20d3c2'; ctx.shadowBlur = 10;
    ctx.beginPath(); let segmentStarted = false;
    samples.forEach((p, i) => {
        if (p.value === null) { ctx.stroke(); ctx.beginPath(); segmentStarted = false; return; }
        const x = pad + width * (i / Math.max(1, samples.length - 1));
        const y = pad + height - ((Number(p.value) - chartMin) / (chartMax - chartMin)) * height;
        segmentStarted ? ctx.lineTo(x, y) : ctx.moveTo(x, y); segmentStarted = true;
    });
    ctx.stroke(); ctx.shadowBlur = 0;
    points.forEach((p, i) => {
        const x = pad + width * (p.sourceIndex / Math.max(1, samples.length - 1));
        const y = pad + height - ((Number(p.value) - chartMin) / (chartMax - chartMin)) * height;
        ctx.beginPath(); ctx.arc(x, y, points.length === 1 ? 7 : 4, 0, Math.PI * 2); ctx.fillStyle = '#20d3c2'; ctx.fill();
        const labelEvery = Math.max(1, Math.ceil(points.length / 12));
        if (i % labelEvery === 0 || i === points.length - 1) {
            ctx.fillStyle = '#d8f8f5'; ctx.font = '10px monospace'; ctx.textAlign = 'center';
            ctx.fillText(`${p.value} ${series.unit || ''}`, x, Math.max(13, y - 10));
        }
    });
    const periodHours = Number(document.querySelector('#periodSelect').value || 24);
    const formatAxisTime = value => {
        const date = new Date(value);
        if (periodHours <= 1) return date.toLocaleTimeString('id-ID', {second:'2-digit'}).replace(/\D/g, '').slice(-2);
        if (periodHours <= 24) return date.toLocaleTimeString('id-ID', {hour:'2-digit',hour12:false}).replace(/\D/g, '').slice(0,2);
        if (periodHours <= 720) return date.toLocaleDateString('id-ID', {day:'2-digit'}).replace(/\D/g, '').slice(0,2);
        return date.toLocaleDateString('id-ID', {month:'short'});
    };
    const labelIndexes = [...new Set([0, Math.round((samples.length-1)*.25), Math.round((samples.length-1)*.5), Math.round((samples.length-1)*.75), samples.length - 1])];
    ctx.fillStyle = '#738996'; ctx.font = '11px monospace'; ctx.textAlign = 'center';
    labelIndexes.forEach(i => {
        const x = pad + width * (i / Math.max(1, samples.length - 1));
        ctx.fillText(formatAxisTime(samples[i].time), x, canvas.height - 24);
    });
    ctx.fillStyle = '#8ca1ab'; ctx.font = '11px monospace';
    const axisUnit = periodHours <= 1 ? 'Detik' : periodHours <= 24 ? 'Jam' : periodHours <= 720 ? 'Hari' : 'Bulan';
    ctx.fillText(`Waktu (${axisUnit})`, canvas.width / 2, canvas.height - 7);
    ctx.save(); ctx.translate(11, canvas.height / 2); ctx.rotate(-Math.PI / 2);
    ctx.fillText(`Nilai (${series.unit || 'Value'})`, 0, 0); ctx.restore();
    ctx.textAlign = 'start';
    const latest = points[points.length - 1];
    document.querySelector('#graphLatest').textContent = `Latest: ${latest.value} ${series.unit || ''} · ${new Date(latest.time).toLocaleString('id-ID')}`;
}

function layoutProjectTopology() {
    const stage = document.querySelector('#topology');
    const core = stage?.querySelector('.core-node');
    const branches = [...(stage?.querySelectorAll('.project-branch') || [])];
    const svg = stage?.querySelector('.topology-links');
    if (!stage || !core || !svg || !branches.length) return;

    const width = stage.clientWidth;
    const height = stage.clientHeight;
    const centerX = width / 2;
    const centerY = height / 2;
    const cardWidth = Math.min(280, Math.max(210, width * .22));
    const cardHeight = 165;
    const radiusX = Math.max(cardWidth * .72, (width - cardWidth) / 2 - 28);
    const radiusY = Math.max(155, (height - cardHeight) / 2 - 28);

    core.style.left = `${centerX}px`;
    core.style.top = `${centerY}px`;
    branches.forEach((branch, index) => {
        const angle = (-Math.PI / 2) + (Math.PI * 2 * index / branches.length);
        const x = centerX + Math.cos(angle) * radiusX;
        const y = centerY + Math.sin(angle) * radiusY;
        branch.style.width = `${cardWidth}px`;
        branch.style.left = `${x}px`;
        branch.style.top = `${y}px`;
    });

    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.innerHTML = branches.map(branch => {
        const x = parseFloat(branch.style.left);
        const y = parseFloat(branch.style.top);
        return `<line x1="${centerX}" y1="${centerY}" x2="${x}" y2="${y}" />`;
    }).join('');
}

let topologyResizeTimer;
window.addEventListener('resize', () => { clearTimeout(topologyResizeTimer); topologyResizeTimer = setTimeout(layoutProjectTopology, 100); });
window.addEventListener('load', layoutProjectTopology);
window.addEventListener('hashchange', () => setTimeout(layoutProjectTopology));
document.querySelector('[data-view="topology"]')?.addEventListener('click', () => setTimeout(layoutProjectTopology));
