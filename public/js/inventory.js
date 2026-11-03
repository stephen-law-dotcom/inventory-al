document.querySelectorAll('[data-request-form]').forEach((form) => {
    const list = form.querySelector('[data-item-list]');
    const template = form.querySelector('[data-item-template]');

    form.querySelector('[data-add-item]')?.addEventListener('click', () => {
        const index = list.querySelectorAll('[data-item-row]').length;
        const html = template.innerHTML.replaceAll('__INDEX__', index);
        list.insertAdjacentHTML('beforeend', html);
    });

    form.addEventListener('click', (event) => {
        if (! event.target.matches('[data-remove-item]')) {
            return;
        }

        const rows = list.querySelectorAll('[data-item-row]');
        if (rows.length > 1) {
            event.target.closest('[data-item-row]').remove();
        }
    });
});

document.querySelectorAll('[data-vendor-form]').forEach((form) => {
    const list = form.querySelector('[data-vendor-list]');
    const template = form.querySelector('[data-vendor-template]');

    form.querySelector('[data-add-vendor]')?.addEventListener('click', () => {
        const index = list.querySelectorAll('[data-vendor-row]').length;
        const html = template.innerHTML.replaceAll('__INDEX__', index);
        list.insertAdjacentHTML('beforeend', html);
    });

    form.addEventListener('click', (event) => {
        if (! event.target.matches('[data-remove-vendor]')) {
            return;
        }

        event.target.closest('[data-vendor-row]').remove();
    });
});

document.querySelectorAll('[data-edit-target]').forEach((button) => {
    button.addEventListener('click', () => {
        const target = document.getElementById(button.dataset.editTarget);
        if (! target) {
            return;
        }

        target.hidden = ! target.hidden;
        button.closest('details')?.removeAttribute('open');
    });
});

const actionMenus = Array.from(document.querySelectorAll('details.action-menu'));

const closeActionMenus = (except = null) => {
    actionMenus.forEach((menu) => {
        if (menu !== except) {
            menu.removeAttribute('open');
            menu.classList.remove('opens-upward');
        }
    });
};

const positionActionMenu = (menu) => {
    menu.classList.remove('opens-upward');

    const panel = menu.querySelector('.menu');
    const summary = menu.querySelector('summary');
    if (! panel || ! summary) {
        return;
    }

    const panelRect = panel.getBoundingClientRect();
    const summaryRect = summary.getBoundingClientRect();
    const viewportPadding = 12;
    const hasRoomAbove = summaryRect.top - viewportPadding >= panelRect.height;

    if (panelRect.bottom > window.innerHeight - viewportPadding && hasRoomAbove) {
        menu.classList.add('opens-upward');
    }
};

actionMenus.forEach((menu) => {
    menu.addEventListener('toggle', () => {
        if (! menu.open) {
            menu.classList.remove('opens-upward');
            return;
        }

        closeActionMenus(menu);
        window.requestAnimationFrame(() => positionActionMenu(menu));
    });
});

document.addEventListener('click', (event) => {
    if (! event.target.closest('details.action-menu')) {
        closeActionMenus();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    const openMenu = document.querySelector('details.action-menu[open]');
    closeActionMenus();
    openMenu?.querySelector('summary')?.focus();
});

window.addEventListener('resize', () => {
    const openMenu = document.querySelector('details.action-menu[open]');
    if (openMenu) {
        positionActionMenu(openMenu);
    }
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (! window.confirm(form.dataset.confirm)) {
            event.preventDefault();
            const fileInput = form.querySelector('input[type="file"]');
            if (fileInput) fileInput.value = '';
        }
    });
});

document.querySelectorAll('[data-href]').forEach((row) => {
    row.addEventListener('click', (event) => {
        if (event.target.closest('a, button, form, input, select, textarea, details, summary')) {
            return;
        }

        window.location.href = row.dataset.href;
    });

    row.addEventListener('keydown', (event) => {
        if ((event.key === 'Enter' || event.key === ' ') && ! event.target.closest('a, button, form, input, select, textarea, details, summary')) {
            event.preventDefault();
            window.location.href = row.dataset.href;
        }
    });
});

document.querySelectorAll('table').forEach((table, index) => {
    const bodyRows = Array.from(table.querySelectorAll('tbody tr'));
    if (bodyRows.length < 5 || table.classList.contains('report-table') || table.closest('.mini-table') || table.closest('section')?.querySelector('.transaction-toolbar')) {
        return;
    }

    const toolbar = document.createElement('div');
    toolbar.className = 'table-toolbar';
    const search = document.createElement('input');
    search.className = 'table-search';
    search.type = 'search';
    search.placeholder = 'Cari data tabel...';
    search.setAttribute('aria-label', 'Cari data tabel');
    toolbar.appendChild(search);
    table.parentNode.insertBefore(toolbar, table);

    search.addEventListener('input', () => {
        const keyword = search.value.trim().toLowerCase();
        bodyRows.forEach((row) => {
            row.hidden = keyword !== '' && ! row.textContent.toLowerCase().includes(keyword);
        });
    });
});

document.querySelector('[data-export-report]')?.addEventListener('click', () => {
    const tables = Array.from(document.querySelectorAll('main section table'));
    const visibleTables = tables.filter((table) => table.offsetParent !== null);
    const rows = [];

    visibleTables.forEach((table) => {
        const title = table.closest('section')?.querySelector('h2')?.textContent?.trim();
        if (title) {
            rows.push([title]);
        }

        table.querySelectorAll('tr').forEach((tr) => {
            const cells = Array.from(tr.querySelectorAll('th, td')).map((cell) =>
                cell.textContent.replace(/\s+/g, ' ').trim()
            );
            if (cells.length) {
                rows.push(cells);
            }
        });
        rows.push([]);
    });

    const csv = rows.map((row) => row.map((value) => `"${String(value).replaceAll('"', '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `report-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
});

document.querySelectorAll('[data-usage-form]').forEach((form) => {
    const unitSelect = form.querySelector('[data-usage-unit]');
    const itemSelect = form.querySelector('[name="inventory_item_id"]');
    const dependents = form.querySelectorAll('[data-usage-dependent]');
    const message = form.querySelector('[data-usage-unit-message]');

    const syncUsageUnit = () => {
        const hasUnit = Boolean(unitSelect.value);
        const campId = unitSelect.selectedOptions[0]?.dataset.campId;
        dependents.forEach((field) => {
            field.disabled = ! hasUnit;
        });
        if (itemSelect) {
            Array.from(itemSelect.options).forEach((option) => {
                option.hidden = Boolean(option.dataset.campId) && option.dataset.campId !== campId;
            });
            if (itemSelect.selectedOptions[0]?.hidden) itemSelect.value = '';
        }
        if (message) {
            message.hidden = hasUnit;
        }
    };

    unitSelect.addEventListener('change', syncUsageUnit);
    syncUsageUnit();
});

document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => {
    document.body.classList.toggle('nav-open');
});

document.querySelector('[data-sidebar-close]')?.addEventListener('click', () => {
    document.body.classList.remove('nav-open');
});

document.querySelectorAll('.sidebar a').forEach((link) => {
    link.addEventListener('click', () => document.body.classList.remove('nav-open'));
});
