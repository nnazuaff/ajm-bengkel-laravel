type Theme = 'light' | 'dark';
const flux = () => (window as Window & {
    Flux?: { appearance?: string; applyAppearance?: (theme: Theme) => void };
}).Flux;

const syncThemeButtons = () => {
    const label = document.documentElement.classList.contains('dark')
        ? 'Ganti ke tema siang' : 'Ganti ke tema malam';
    document.querySelectorAll<HTMLButtonElement>('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-label', label);
        button.title = label;
    });
};

const applyTheme = (theme: Theme) => {
    const manager = flux();
    if (manager) {
        manager.appearance = theme;
        manager.applyAppearance?.(theme);
    }
    document.documentElement.classList.toggle('dark', theme === 'dark');
    document.documentElement.style.colorScheme = theme;
    try { localStorage.setItem('flux.appearance', theme); } catch {}
    syncThemeButtons();
};

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element) || !event.target.closest('[data-theme-toggle]')) return;
    applyTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
});
document.addEventListener('livewire:navigated', syncThemeButtons);
window.addEventListener('storage', (event) => {
    if (event.key === 'flux.appearance') applyTheme(event.newValue === 'light' ? 'light' : 'dark');
});
new MutationObserver(syncThemeButtons).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
syncThemeButtons();
