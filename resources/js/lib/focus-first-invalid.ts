const FOCUSABLE_SELECTOR = [
    'input:not([type="hidden"]):not([type="file"])',
    'textarea',
    'select',
    '[role="radio"][aria-checked="true"]',
    'button',
    'label',
].join(', ');

export function focusFirstInvalidField(root: ParentNode = document): void {
    const field = root.querySelector<HTMLElement>('[data-invalid="true"]');

    if (!field) {
        return;
    }

    field.scrollIntoView({ block: 'center', behavior: 'smooth' });
    field.querySelector<HTMLElement>(FOCUSABLE_SELECTOR)?.focus();
}
