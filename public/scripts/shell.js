/* NovixFin — comportamento do shell (recolher sidebar).
   Alterna data-side="rail" no #nvx-shell (grid 264px <-> 72px), persistido em
   localStorage['sidebarCollapsed'] (mesma chave do shell antigo). No mobile a
   sidebar é substituída pela faixa #nvx-mnav via CSS — nada de JS aqui. */
(function () {
    'use strict';

    function boot() {
        var shell = document.getElementById('nvx-shell');
        var toggle = document.getElementById('nvx-side-toggle');
        if (!shell) return;

        function isDesktop() { return window.innerWidth >= 1021; }

        function load() {
            try { return localStorage.getItem('sidebarCollapsed') === 'true'; } catch (e) { return false; }
        }
        function save(collapsed) {
            try { localStorage.setItem('sidebarCollapsed', collapsed ? 'true' : 'false'); } catch (e) {}
        }
        function apply(collapsed) {
            if (collapsed && isDesktop()) {
                shell.setAttribute('data-side', 'rail');
            } else {
                shell.removeAttribute('data-side');
            }
        }

        apply(load());

        if (toggle) {
            toggle.addEventListener('click', function () {
                var collapsed = shell.getAttribute('data-side') !== 'rail';
                apply(collapsed);
                save(collapsed);
            });
        }

        window.addEventListener('resize', function () { apply(load()); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
