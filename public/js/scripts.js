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
