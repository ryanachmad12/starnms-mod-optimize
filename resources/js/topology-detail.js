import './bootstrap';

const token = document.querySelector('meta[name="csrf-token"]').content;
const remote = document.querySelector('#detailRemote');
const graph = document.querySelector('#detailGraph');
const canvas = document.querySelector('#detailSnmpChart');
const ctx = canvas.getContext('2d');
let selected = null;
let graphData = [];

document.querySelectorAll('.refresh-project-button').forEach(button => button.addEventListener('click', async () => {
    if (button.dataset.running === '1') return;
    button.dataset.running = '1';
    const originalText = button.textContent;
    const uniqueDevices = [...document.querySelectorAll('.detail-device[data-device-id]')]
        .filter((node, index, nodes) => nodes.findIndex(candidate => candidate.dataset.deviceId === node.dataset.deviceId) === index);
    try {
        for (let index = 0; index < uniqueDevices.length; index++) {
            const source = uniqueDevices[index];
            button.textContent = `Checking ${index + 1}/${uniqueDevices.length}`;
            const response = await fetch(source.dataset.checkUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            });
            if (!response.ok) continue;
            const device = await response.json();
            document.querySelectorAll(`.detail-device[data-device-id="${device.id}"]`).forEach(node => {
                node.classList.remove('online', 'offline', 'unknown');
                node.classList.add(device.status);
                const meta = node.querySelector('small');
                if (meta) meta.textContent = `${node.dataset.ip} · ${String(device.status).toUpperCase()}`;
            });
            await new Promise(resolve => setTimeout(resolve, 60));
        }
    } finally {
        button.textContent = originalText;
        button.dataset.running = '0';
        window.location.reload();
    }
}));

document.querySelectorAll('.detail-device').forEach(button => button.addEventListener('click', () => {
    selected = button;
    document.querySelector('#detailRemoteName').textContent = button.dataset.name;
    document.querySelector('#detailIp').textContent = button.dataset.ip;
    document.querySelector('#detailType').textContent = button.dataset.type;
    document.querySelector('#detailIcon').textContent = button.querySelector('b').textContent;
    document.querySelector('#detailWeb').href = button.dataset.web;
    document.querySelector('#detailTelnet').href = button.dataset.telnet;
    document.querySelector('#detailSsh').href = button.dataset.ssh;
    const enabled = button.dataset.snmpEnabled === '1';
    document.querySelector('#detailSnmp').classList.toggle('disabled-service', !enabled);
    document.querySelector('#detailSnmpPort').textContent = `V${String(button.dataset.snmpVersion || '2c').toUpperCase()} · UDP/${button.dataset.snmpPort || 161}`;
    document.querySelector('#detailSnmpHelp').textContent = enabled ? `Grafik OID · status ${button.dataset.snmpStatus || 'unknown'}` : 'SNMP belum diaktifkan pada perangkat ini';
    remote.showModal();
}));

document.querySelector('.close-detail').addEventListener('click', () => remote.close());
document.querySelector('#detailCopy').addEventListener('click', () => selected && navigator.clipboard.writeText(selected.dataset.ip));
document.querySelector('#detailSnmp').addEventListener('click', async () => {
    if (!selected || selected.dataset.snmpEnabled !== '1') return;
    remote.close();
    document.querySelector('#detailGraphTitle').textContent = selected.dataset.name;
    graph.showModal();
    await loadGraph(false);
});
document.querySelector('.close-detail-graph').addEventListener('click', () => graph.close());
document.querySelector('#detailPeriodSelect').addEventListener('change', showPrompt);
document.querySelector('#detailOidSelect').addEventListener('change', showPrompt);
document.querySelector('#detailPollSnmp').addEventListener('click', async () => {
    if (!selected) return;
    const button = document.querySelector('#detailPollSnmp');
    const oid = document.querySelector('#detailOidSelect').value;
    button.classList.add('spinning');
    try {
        const response = await fetch(selected.dataset.snmpPollUrl, {method:'POST', headers:{'X-CSRF-TOKEN':token, Accept:'application/json'}});
        const result = await response.json();
        document.querySelector('#detailGraphStatus').textContent = result.message || `SNMP ${result.status}`;
        await loadGraph(true, oid);
    } finally { button.classList.remove('spinning'); }
});

function showPrompt() {
    const custom = document.querySelector('#detailPeriodSelect').value === 'custom';
    document.querySelector('#detailCustomRange').hidden = !custom;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const empty = document.querySelector('#detailChartEmpty');
    empty.textContent = 'Klik Poll now untuk menampilkan grafik sensor dan periode yang dipilih.';
    empty.style.display = 'grid';
    document.querySelector('#detailGraphLatest').textContent = '';
}

async function loadGraph(draw = true, requestedOid = null) {
    const period = document.querySelector('#detailPeriodSelect');
    const params = new URLSearchParams();
    if (period.value === 'custom') {
        const start = document.querySelector('#detailStartDate').value;
        const end = document.querySelector('#detailEndDate').value;
        if (!start || !end) { showPrompt(); document.querySelector('#detailChartEmpty').textContent='Isi Start date dan End date terlebih dahulu.'; return; }
        params.set('start_date', start); params.set('end_date', end);
    } else params.set('hours', period.value);
    const response = await fetch(`${selected.dataset.snmpMetricsUrl}?${params}`, {headers:{Accept:'application/json'}, cache:'no-store'});
    const data = await response.json();
    graphData = data.series || [];
    const select = document.querySelector('#detailOidSelect');
    const previous = requestedOid || select.value;
    select.innerHTML = graphData.map(series => `<option value="${series.oid}">${series.label} (${series.unit || 'Value'}) · ${series.oid}</option>`).join('');
    if ([...select.options].some(option => option.value === previous)) select.value = previous;
    document.querySelector('#detailGraphProtocol').textContent = `SNMP V${String(data.device.snmp_version || '2c').toUpperCase()} TELEMETRY`;
    document.querySelector('#detailGraphStatus').textContent = `SNMP ${(data.device.snmp_status || 'unknown').toUpperCase()} · ${data.device.ip_address}`;
    document.querySelector('#detailGraphRange').textContent = period.options[period.selectedIndex].text;
    draw ? drawGraph() : showPrompt();
}

function formatAxisTime(value, hours) {
    const date = new Date(value);
    if(hours<=1)return date.toLocaleTimeString('id-ID',{second:'2-digit'}).replace(/\D/g,'').slice(-2);
    if(hours<=24)return date.toLocaleTimeString('id-ID',{hour:'2-digit',hour12:false}).replace(/\D/g,'').slice(0,2);
    if(hours<=720)return date.toLocaleDateString('id-ID',{day:'2-digit'}).replace(/\D/g,'').slice(0,2);
    return date.toLocaleDateString('id-ID',{month:'short'});
}

function drawGraph() {
    const oid = document.querySelector('#detailOidSelect').value;
    const series = graphData.find(item => item.oid === oid);
    const samples = series?.points || [];
    const valid = samples.map((point,index) => ({...point,index})).filter(point => point.value !== null);
    ctx.clearRect(0,0,canvas.width,canvas.height);
    const empty = document.querySelector('#detailChartEmpty');
    if (!valid.length) { empty.textContent='Belum ada nilai numerik untuk sensor dan periode ini.';empty.style.display='grid';return; }
    empty.style.display='none';
    const values=valid.map(point=>Number(point.value)),min=Math.min(...values),max=Math.max(...values),margin=max===min?Math.max(Math.abs(max)*.05,1):0;
    const chartMin=min-margin,chartMax=max+margin,padX=62,padTop=30,padBottom=58,width=canvas.width-padX-25,height=canvas.height-padTop-padBottom;
    ctx.strokeStyle='#1e303c';ctx.fillStyle='#738996';ctx.font='11px monospace';ctx.lineWidth=1;
    for(let i=0;i<=4;i++){const y=padTop+height*i/4;ctx.beginPath();ctx.moveTo(padX,y);ctx.lineTo(canvas.width-25,y);ctx.stroke();ctx.fillText((chartMax-(chartMax-chartMin)*i/4).toFixed(2),5,y+4)}
    ctx.strokeStyle='#20d3c2';ctx.lineWidth=3;ctx.shadowColor='#20d3c2';ctx.shadowBlur=9;ctx.beginPath();let started=false;
    samples.forEach((point,index)=>{if(point.value===null){ctx.stroke();ctx.beginPath();started=false;return}const x=padX+width*index/Math.max(1,samples.length-1),y=padTop+height-((Number(point.value)-chartMin)/(chartMax-chartMin))*height;started?ctx.lineTo(x,y):ctx.moveTo(x,y);started=true});ctx.stroke();ctx.shadowBlur=0;
    const selectedPeriod=document.querySelector('#detailPeriodSelect').value,hours=selectedPeriod==='custom'?Math.max(1,(new Date(document.querySelector('#detailEndDate').value)-new Date(document.querySelector('#detailStartDate').value))/3600000):Number(selectedPeriod||24),indexes=[0,.25,.5,.75,1].map(ratio=>Math.round((samples.length-1)*ratio)).filter((value,index,array)=>array.indexOf(value)===index);
    ctx.textAlign='center';ctx.fillStyle='#8ca1ab';ctx.font='10px monospace';indexes.forEach(index=>{const x=padX+width*index/Math.max(1,samples.length-1);ctx.fillText(formatAxisTime(samples[index].time,hours),x,canvas.height-24)});const axisUnit=hours<=1?'Detik':hours<=24?'Jam':hours<=720?'Hari':'Bulan';ctx.fillText(`Waktu (${axisUnit})`,padX+width/2,canvas.height-7);ctx.textAlign='start';
    const latest=valid.at(-1);document.querySelector('#detailGraphLatest').textContent=`Latest: ${latest.value} ${series.unit||''} · ${new Date(latest.time).toLocaleString('id-ID')}`;
}

const exportDialog=document.querySelector('#telemetryExportDialog');
document.querySelector('#openTelemetryExport')?.addEventListener('click',()=>exportDialog.showModal());
document.querySelector('#closeTelemetryExport')?.addEventListener('click',()=>exportDialog.close());
document.querySelector('#exportPeriod')?.addEventListener('change',event=>{document.querySelector('#exportCustomRange').hidden=event.target.value!=='custom'});
document.querySelectorAll('[data-check-all]').forEach(master=>master.addEventListener('change',()=>{
    const selector=master.dataset.checkAll==='site'?'.export-site':'.export-sensor';
    document.querySelectorAll(selector).forEach(item=>item.checked=master.checked);
}));
document.querySelectorAll('.date-picker-priority,#detailStartDate,#detailEndDate').forEach(input=>input.addEventListener('focus',()=>{
    if(typeof input.showPicker==='function') input.showPicker();
}));
import './global-alerts';
