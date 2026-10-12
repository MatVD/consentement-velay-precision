/*
 * Mobile-only progressive enhancement for the EasyAdmin client datagrid.
 * Without JavaScript, all client fields remain available.
 */
(() => {
    const table = document.querySelector('.ea.ea-index-Client .table.datagrid:not(.datagrid-empty)');
    if (!table) {
        return;
    }

    const mobile = window.matchMedia('(max-width: 767.98px)');
    const rows = table.querySelectorAll('tbody > tr:not(.empty-row)');

    rows.forEach((row, rowIndex) => {
        const nameCell = row.querySelector(':scope > td[data-column="name"]');
        const statusCell = row.querySelector(':scope > td[data-column="status"]');
        if (!nameCell || !statusCell) {
            return;
        }

        const name = nameCell.textContent.trim();
        const details = Array.from(row.querySelectorAll(':scope > td')).filter(
            (cell) => cell !== nameCell && cell !== statusCell
        );
        if (!details.length) {
            return;
        }

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'client-card-toggle';
        const chevron = document.createElement('span');
        chevron.className = 'client-card-chevron';
        chevron.setAttribute('aria-hidden', 'true');
        toggle.appendChild(chevron);

        const detailsIds = details.map((cell, index) => {
            const id = `client-card-detail-${rowIndex}-${index}`;
            cell.id = id;
            return id;
        });
        toggle.setAttribute('aria-controls', detailsIds.join(' '));

        const setExpanded = (expanded) => {
            row.classList.toggle('client-card-collapsed', !expanded);
            toggle.setAttribute('aria-expanded', String(expanded));
            toggle.setAttribute('aria-label', `${expanded ? 'Replier' : 'Déplier'} les détails de ${name}`);
            toggle.title = expanded ? 'Replier la fiche' : 'Déplier la fiche';
        };

        nameCell.appendChild(toggle);
        setExpanded(false);

        // EasyAdmin also makes the whole table row an edit link on desktop.
        // On mobile, its non-interactive area should instead expand the card.
        const initialRole = row.getAttribute('role');
        const initialTabindex = row.getAttribute('tabindex');
        const updateRowAccessibility = () => {
            if (mobile.matches) {
                // Keep table-row semantics; the dedicated button controls expansion.
                row.setAttribute('role', 'row');
                row.removeAttribute('tabindex');
            } else {
                if (initialRole === null) {
                    row.removeAttribute('role');
                } else {
                    row.setAttribute('role', initialRole);
                }
                if (initialTabindex === null) {
                    row.removeAttribute('tabindex');
                } else {
                    row.setAttribute('tabindex', initialTabindex);
                }
            }
        };
        updateRowAccessibility();
        mobile.addEventListener('change', updateRowAccessibility);

        const switchExpanded = () => setExpanded(toggle.getAttribute('aria-expanded') !== 'true');

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            switchExpanded();
        });
        toggle.addEventListener('keydown', (event) => event.stopPropagation());

        row.addEventListener('click', (event) => {
            if (!mobile.matches || event.target.closest('a, button, input, label, select, textarea, form, [role="button"]')) {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            switchExpanded();
        }, true);
    });
})();
