// The customer actions live in a native modal dialog, independently of
// Bootstrap's JavaScript bundle. showModal() manages focus, Esc and the backdrop.
document.addEventListener('DOMContentLoaded', () => {
    const trigger = document.querySelector('[data-client-actions-open]');
    const drawer = document.querySelector('[data-client-actions-drawer]');
    if (!trigger || !(drawer instanceof HTMLDialogElement)) return;

    trigger.addEventListener('click', () => {
        if (!drawer.open) drawer.showModal();
    });

    drawer.querySelector('[data-client-actions-close]')?.addEventListener('click', () => {
        drawer.close();
    });

    // Closing by clicking the backdrop. Keep clicks inside either form intact.
    drawer.addEventListener('click', (event) => {
        if (event.target !== drawer) return;
        const bounds = drawer.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right
            || event.clientY < bounds.top || event.clientY > bounds.bottom) {
            drawer.close();
        }
    });

    // A generated link is single-use flash content: show it immediately after
    // POST -> redirect so the user can copy it, rather than hide it in a drawer.
    if (drawer.hasAttribute('data-client-actions-auto-open') && !drawer.open) {
        drawer.showModal();
    }
});
