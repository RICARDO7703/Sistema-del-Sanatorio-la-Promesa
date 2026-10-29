(() => {
    'use strict';
    const config = document.currentScript.dataset;
    let navegacionInterna = false;
    let enviado = false;
    document.addEventListener('click', event => {
        const enlace = event.target.closest('a[href]');
        if (!enlace || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || enlace.hasAttribute('download') || (enlace.target && enlace.target !== '_self')) return;
        const destino = new URL(enlace.href, location.href);
        if (destino.origin === location.origin && (destino.pathname !== location.pathname || destino.search !== location.search || !destino.hash)) {
            navegacionInterna = true;
            setTimeout(() => { navegacionInterna = false; }, 1500);
        }
    });
    document.addEventListener('submit', event => {
        if (event.defaultPrevented) return;
        navegacionInterna = true;
        setTimeout(() => { navegacionInterna = false; }, 1500);
    });
    window.addEventListener('pageshow', () => { enviado = false; navegacionInterna = false; });
    window.addEventListener('pagehide', event => {
        if (event.persisted || navegacionInterna || enviado || !navigator.sendBeacon) return;
        const datos = new FormData();
        datos.append('_token', config.csrf);
        datos.append('pagina', config.pagina);
        enviado = navigator.sendBeacon(config.salida, datos);
    });
})();
