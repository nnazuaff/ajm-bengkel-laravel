import './theme';

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || event.defaultPrevented) return;
    const menu = document.querySelector<HTMLDetailsElement>('details[data-home-menu][open], details[data-account-menu][open]');
    if (!menu) return;
    menu.open = false;
    menu.querySelector('summary')?.focus();
});

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;
    const link = event.target.closest('details[data-home-menu] a, details[data-account-menu] a');
    const menu = link?.closest<HTMLDetailsElement>('details');
    if (menu) menu.open = false;
});
