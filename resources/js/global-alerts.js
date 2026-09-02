let center = document.querySelector('.alert-center');
const projectLink = [...document.querySelectorAll('.sidebar nav > a')].find(link => /\/projects(?:$|\?)/.test(link.href));
if (projectLink && !projectLink.closest('.nav-group')) {
    const group = document.createElement('div'); group.className = `nav-group open`;
    const parent = document.createElement('button'); parent.type = 'button'; parent.className = `nav-link nav-parent${projectLink.classList.contains('active') ? ' active' : ''}`; parent.setAttribute('aria-expanded', 'true'); parent.innerHTML = '<span>▣</span> Project Management <i>⌄</i>';
    const submenu = document.createElement('div'); submenu.className = 'nav-submenu';
    projectLink.className = `nav-link nav-child nav-anchor${projectLink.classList.contains('active') ? ' active' : ''}`;
    projectLink.innerHTML = projectLink.innerHTML.replace('Project Management', 'Projects');
    projectLink.replaceWith(group); submenu.append(projectLink); group.append(parent, submenu);
    parent.addEventListener('click', () => { const open = !group.classList.toggle('open'); group.classList.toggle('open', !open); parent.setAttribute('aria-expanded', String(!open)); });
}
if (!center) {
    center = document.createElement('details');
    const headerActions = document.querySelector('header .header-actions');
    center.className = `alert-center${headerActions ? '' : ' global-alert-center'}`;
    center.dataset.alertUrl = '/api/alerts';
    center.innerHTML = '<summary title="Alert notifications">🔔</summary><div class="alert-panel"><strong>Alert notifications</strong><div class="alert-items"><p>Memuat alert aktif...</p></div></div>';
    if (headerActions) {
        const dashboardButton = [...headerActions.querySelectorAll('a')].find(link => link.textContent.includes('Dashboard'));
        dashboardButton ? headerActions.insertBefore(center, dashboardButton) : headerActions.append(center);
    } else document.body.append(center);
}
if (center) {
    const storageKey = 'starnms-spoken-alerts-v2';
    let spoken = JSON.parse(localStorage.getItem(storageKey) || '[]');
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
    let inFlight = false;
    const refreshAlerts = async () => {
        if (document.hidden || inFlight) return;
        inFlight = true;
        try {
            const response = await fetch(center.dataset.alertUrl, {headers:{Accept:'application/json'}});
            if (!response.ok) return;
            const alerts = (await response.json()).alerts || [];
            center.querySelector('summary').innerHTML = `🔔${alerts.length ? ` <b>${alerts.length}</b>` : ''}`;
            center.querySelector('.alert-items').innerHTML = alerts.length ? alerts.map(alert => `<article><b>${escapeHtml(alert.device)}</b><span>${escapeHtml(alert.sensor)}: ${escapeHtml(alert.value)} ${escapeHtml(alert.unit)}</span><small>${escapeHtml(alert.time_human)}</small></article>`).join('') : '<p>Tidak ada threshold alert aktif.</p>';
            if ('speechSynthesis' in window) alerts.filter(alert => !spoken.includes(alert.key)).forEach(alert => { speechSynthesis.speak(new SpeechSynthesisUtterance(alert.speech)); spoken.push(alert.key); });
            spoken = spoken.slice(-300); localStorage.setItem(storageKey, JSON.stringify(spoken));
        } catch (_) {
        } finally {
            inFlight = false;
        }
    };
    refreshAlerts(); setInterval(refreshAlerts, 10000);
}
