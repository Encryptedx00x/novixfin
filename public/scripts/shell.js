/* NovixFin — comportamento do shell (recolher sidebar).
   Alterna data-side="rail" no #nvx-shell (grid 264px <-> 72px), persistido em
   localStorage['sidebarCollapsed'] (mesma chave do shell antigo). No mobile a
   sidebar vira DRAWER (gaveta) — ver wireDrawer() no fim deste arquivo. */
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

        wireDrawer(shell);
    }

    /* ---- DRAWER (mobile): hambúrguer + overlay + Esc + tocar fora ----
       A gaveta é a própria #nvx-side; aqui só alternamos data-drawer no shell.
       O hambúrguer não existe no desktop (CSS), então nada disso dispara lá. */
    function wireDrawer(shell) {
        var burger = document.getElementById('nvx-drawer-toggle');
        var overlay = document.getElementById('nvx-drawer-ov');
        var closeBtn = document.getElementById('nvx-drawer-close');
        var side = document.getElementById('nvx-side');
        if (!burger || !side) { return; }

        function open() {
            shell.setAttribute('data-drawer', 'open');
            burger.setAttribute('aria-expanded', 'true');
            document.body.classList.add('nvx-drawer-open');
            if (overlay) { overlay.hidden = false; }
        }
        function close() {
            shell.removeAttribute('data-drawer');
            burger.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('nvx-drawer-open');
            if (overlay) { overlay.hidden = true; }
        }
        function isOpen() { return shell.getAttribute('data-drawer') === 'open'; }

        burger.addEventListener('click', function () { isOpen() ? close() : open(); });
        if (closeBtn) { closeBtn.addEventListener('click', close); }
        if (overlay) { overlay.addEventListener('click', close); }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen()) { close(); }
        });

        // Navegar fecha a gaveta (links levam a outra página, mas fecha antes
        // de sair evita o flash da gaveta aberta ao voltar pelo histórico).
        side.addEventListener('click', function (e) {
            if (e.target.closest('a[href]')) { close(); }
        });

        // Voltando ao desktop, garante estado limpo.
        window.addEventListener('resize', function () {
            if (window.innerWidth > 1020 && isOpen()) { close(); }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
