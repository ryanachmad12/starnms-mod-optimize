document.querySelectorAll('.region-toggle').forEach(button => {
    button.addEventListener('click', () => {
        const region = button.closest('.detail-region');
        const collapsed = region.classList.toggle('collapsed');
        button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    });
});
document.querySelectorAll('.base-station-toggle').forEach(button => {
    button.addEventListener('click', () => {
        const station = button.closest('.station-tree');
        const collapsed = station.classList.toggle('collapsed');
        button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    });
});
