document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-link]');
    if (!button) return;

    const input = document.getElementById(button.dataset.copyLink);
    const status = document.getElementById(button.dataset.copyLink + '-status');
    if (!input || !status) return;

    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(input.value);
        } else {
            input.focus();
            input.select();
            if (!document.execCommand('copy')) {
                throw new Error('copy unavailable');
            }
        }
        status.textContent = 'Lien copié.';
        button.textContent = 'Copié';
    } catch {
        input.focus();
        input.select();
        status.textContent = 'Copie automatique indisponible. Sélectionnez et copiez le lien.';
        button.textContent = 'Sélectionné';
    }
});
