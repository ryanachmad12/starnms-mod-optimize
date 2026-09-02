const themeKey = 'starnms-theme';

function preferredTheme() {
    const saved = localStorage.getItem(themeKey);
    if (saved === 'light' || saved === 'dark') return saved;
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme) {
    document.documentElement.dataset.theme = theme;
    document.querySelectorAll('[data-theme-toggle]').forEach(button => {
        const dark = theme === 'dark';
        button.setAttribute('aria-pressed', String(dark));
        button.setAttribute('title', dark ? 'Switch to light mode' : 'Switch to dark mode');
        button.querySelector('[data-theme-label]').textContent = dark ? 'Dark' : 'Light';
        button.querySelector('[data-theme-icon]').textContent = dark ? '☾' : '☀';
    });
}

applyTheme(preferredTheme());

document.addEventListener('click', event => {
    const button = event.target.closest('[data-theme-toggle]');
    if (!button) return;

    const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem(themeKey, nextTheme);
    applyTheme(nextTheme);
});
