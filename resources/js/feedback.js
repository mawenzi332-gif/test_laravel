export function notify(message, variant = 'danger') {
    window.dispatchEvent(new CustomEvent('app:feedback', { detail: { message, variant } }));
}
