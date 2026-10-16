(() => {
    const button = document.querySelector('.sidebar-toggle');
    const sidebar = document.getElementById('barra-lateral');
    if (!button || !sidebar) return;
    const narrow = window.matchMedia('(max-width: 767.98px)');
    const key = 'sanatorio-menu-oculto';
    function render(hidden) {
        sidebar.hidden = hidden;
        button.setAttribute('aria-expanded', String(!hidden));
        button.querySelector('span').textContent = hidden ? 'Mostrar menú' : 'Ocultar menú';
    }
    let saved = null;
    try { saved = localStorage.getItem(key); } catch (_) {}
    render(narrow.matches || saved === 'true');
    button.addEventListener('click', () => {
        render(!sidebar.hidden);
        try { localStorage.setItem(key, String(sidebar.hidden)); } catch (_) {}
    });
    narrow.addEventListener('change', () => render(narrow.matches));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && narrow.matches && !sidebar.hidden) {
            render(true);
            button.focus();
        }
    });
})();


// Conserva las tablas de escritorio y prepara etiquetas para las fichas móviles.
(() => {
    document.querySelectorAll('table.table').forEach(table => {
        const headers = Array.from(table.tHead?.rows[0]?.cells || []);
        if (!headers.length || headers.some(cell => cell.colSpan !== 1)) return;
        table.classList.add('tabla-adaptable');
        table.setAttribute('role', 'table');
        table.tHead.setAttribute('role', 'rowgroup');
        Array.from(table.tHead.rows).forEach(row => row.setAttribute('role', 'row'));
        headers.forEach(header => header.setAttribute('role', 'columnheader'));
        Array.from(table.tBodies).forEach(body => {
            body.setAttribute('role', 'rowgroup');
            Array.from(body.rows).forEach(row => {
                row.setAttribute('role', 'row');
                Array.from(row.cells).forEach((cell, index) => {
                    cell.setAttribute('role', 'cell');
                    if (cell.colSpan !== 1) return;
                    const label = document.createElement('span');
                    label.className = 'etiqueta-movil';
                    label.setAttribute('aria-hidden', 'true');
                    label.textContent = headers[index]?.textContent.trim() || '';
                    cell.prepend(label);
                });
            });
        });
    });
})();
