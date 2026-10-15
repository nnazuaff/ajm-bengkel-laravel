import './theme';

const faqTriggers = () => [...document.querySelectorAll<HTMLButtonElement>('#faq [data-slot="faq-question"]')];

function toggleFaq(trigger: HTMLButtonElement, expand: boolean): void {
    const answer = document.getElementById(trigger.getAttribute('aria-controls') ?? '');
    if (!answer) return;

    const startHeight = answer.hidden ? 0 : answer.getBoundingClientRect().height;
    const startOpacity = answer.hidden ? 0 : Number.parseFloat(getComputedStyle(answer).opacity);
    answer.getAnimations().forEach((animation) => animation.cancel());
    trigger.setAttribute('aria-expanded', String(expand));
    if (expand) answer.hidden = false;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        answer.hidden = !expand;
        return;
    }

    const animation = answer.animate(
        [
            { height: `${startHeight}px`, opacity: startOpacity },
            { height: expand ? `${answer.scrollHeight}px` : '0px', opacity: expand ? 1 : 0 },
        ],
        { duration: expand ? 360 : 320, easing: 'cubic-bezier(.4, 0, .2, 1)' },
    );
    animation.finished.catch(() => {}).finally(() => {
        if (!expand && trigger.getAttribute('aria-expanded') === 'false') answer.hidden = true;
    });
}

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;
    const trigger = event.target.closest<HTMLButtonElement>('#faq [data-slot="faq-question"]');
    if (!trigger) return;
    const expand = trigger.getAttribute('aria-expanded') !== 'true';
    if (expand) {
        faqTriggers().filter((other) => other !== trigger).forEach((other) => {
            if (other.getAttribute('aria-expanded') === 'true') {
                toggleFaq(other, false);
            } else {
                const answer = document.getElementById(other.getAttribute('aria-controls') ?? '');
                if (answer && !answer.hidden) {
                    answer.getAnimations().forEach((animation) => animation.cancel());
                    answer.hidden = true;
                }
            }
        });
    }
    toggleFaq(trigger, expand);
});

document.addEventListener('keydown', (event) => {
    if (!(event.target instanceof HTMLButtonElement) || !event.target.matches('#faq [data-slot="faq-question"]')) return;
    const triggers = faqTriggers();
    const index = triggers.indexOf(event.target);
    const next = event.key === 'ArrowDown' ? index + 1 : event.key === 'ArrowUp' ? index - 1
        : event.key === 'Home' ? 0 : event.key === 'End' ? triggers.length - 1 : -1;
    if (next < 0 && !['ArrowDown', 'ArrowUp'].includes(event.key)) return;
    event.preventDefault();
    triggers[(next + triggers.length) % triggers.length]?.focus();
});

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
