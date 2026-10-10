import './public-home';

document.addEventListener('keydown', (event) => {
    if (
        event.defaultPrevented || event.isComposing || event.repeat ||
        event.altKey || event.shiftKey || !(event.ctrlKey || event.metaKey) ||
        event.key.toLowerCase() !== 'k'
    ) {
        return;
    }

    const target = event.target;

    if (target instanceof Element && (
        target.closest('input, textarea, select') ||
        (target instanceof HTMLElement && target.isContentEditable)
    )) {
        return;
    }

    const search = Array.from(document.querySelectorAll('input[data-workshop-search]')).find(
        (input) => input instanceof HTMLInputElement &&
            !input.matches(':disabled') && !input.readOnly && input.type !== 'hidden' &&
            input.getClientRects().length > 0 &&
            window.getComputedStyle(input).visibility === 'visible',
    );

    if (search instanceof HTMLInputElement) {
        event.preventDefault();
        search.focus();
    }
});
