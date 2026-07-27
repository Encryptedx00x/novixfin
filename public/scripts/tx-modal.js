/* NovixFin — controlador visual do modal de lançamento (criar/editar).
   Fonte da verdade dos DADOS continua nos controles nativos ocultos
   (<select name="type">, <select name="category_id">, <input name="is_effective">);
   este script só reflete e dirige esses controles a partir da UI do design
   (toggle Despesa/Receita, chips de categoria, toggle Efetivada/Pendente),
   disparando 'change' para que dashboard.js (filtro de categoria por tipo,
   criação inline, pré-preenchimento na edição) siga funcionando SEM alteração.

   NÃO posta nada novo: os names/ids enviados aos handlers create.php/update.php
   são exatamente os dos controles ocultos. */
(function () {
    'use strict';

    function fire(el) {
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /* ---- Toggle segmentado ligado a um <select> (Despesa/Receita) ---- */
    function refreshSeg(container) {
        var target = document.querySelector(container.getAttribute('data-seg-target'));
        if (!target) { return; }
        var current = String(target.value);
        container.querySelectorAll('[data-seg-val]').forEach(function (btn) {
            var on = btn.getAttribute('data-seg-val') === current;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }
    function wireSeg(container) {
        var target = document.querySelector(container.getAttribute('data-seg-target'));
        if (!target) { return; }
        container.querySelectorAll('[data-seg-val]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                target.value = btn.getAttribute('data-seg-val');
                fire(target);
                refreshSeg(container);
            });
        });
        refreshSeg(container);
    }

    /* ---- Toggle segmentado ligado a um checkbox (Efetivada/Pendente) ---- */
    function refreshSegCheck(container) {
        var target = document.querySelector(container.getAttribute('data-segcheck-target'));
        if (!target) { return; }
        var val = target.checked ? '1' : '0';
        container.querySelectorAll('[data-seg-val]').forEach(function (btn) {
            var on = btn.getAttribute('data-seg-val') === val;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }
    function wireSegCheck(container) {
        var target = document.querySelector(container.getAttribute('data-segcheck-target'));
        if (!target) { return; }
        container.querySelectorAll('[data-seg-val]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                target.checked = btn.getAttribute('data-seg-val') === '1';
                fire(target);
                refreshSegCheck(container);
            });
        });
        refreshSegCheck(container);
    }

    /* ---- Chips espelhando um <select name="category_id"> ---- */
    var CHIP_LIMIT = 8;
    function renderChips(container) {
        var sel = document.querySelector(container.getAttribute('data-chips-target'));
        if (!sel) { return; }
        var current = String(sel.value);
        container.querySelectorAll('[data-chip]').forEach(function (n) { n.remove(); });
        var keep = container.querySelector('[data-chip-keep]');
        var made = [];
        Array.prototype.forEach.call(sel.options, function (opt) {
            var chip = document.createElement('button');
            chip.type = 'button';
            var active = String(opt.value) === current;
            chip.className = 'nvx-chip' + (active ? ' is-active' : '');
            chip.setAttribute('data-chip', '');
            chip.textContent = opt.textContent.trim();
            chip.addEventListener('click', function () {
                sel.value = opt.value;
                fire(sel);
                renderChips(container);
            });
            if (keep) { container.insertBefore(chip, keep); } else { container.appendChild(chip); }
            made.push({ chip: chip, active: active });
        });
        // "Ver mais": colapsa além do limite (mantém o ativo sempre visível) e
        // insere um botão que revela o resto. Nenhuma categoria é perdida.
        if (made.length > CHIP_LIMIT) {
            var hidden = 0;
            made.forEach(function (m, i) {
                if (i >= CHIP_LIMIT && !m.active) { m.chip.classList.add('nvx-chip--collapsed'); hidden++; }
            });
            if (hidden > 0) {
                var more = document.createElement('button');
                more.type = 'button';
                more.className = 'nvx-chip nvx-chip--more';
                more.setAttribute('data-chip', '');
                more.textContent = 'Ver mais (' + hidden + ')';
                more.addEventListener('click', function () {
                    container.querySelectorAll('.nvx-chip--collapsed').forEach(function (c) {
                        c.classList.remove('nvx-chip--collapsed');
                    });
                    more.remove();
                });
                if (keep) { container.insertBefore(more, keep); } else { container.appendChild(more); }
            }
        }
    }
    function wireChips(container) {
        var sel = document.querySelector(container.getAttribute('data-chips-target'));
        if (!sel) { return; }
        renderChips(container);
        // dashboard.js reconstrói as options do select (filtro por tipo / criação
        // inline) — o observer re-renderiza os chips em sincronia.
        var obs = new MutationObserver(function () { renderChips(container); });
        obs.observe(sel, { childList: true });
    }

    function refreshModal(modal) {
        modal.querySelectorAll('[data-seg-target]').forEach(refreshSeg);
        modal.querySelectorAll('[data-segcheck-target]').forEach(refreshSegCheck);
        modal.querySelectorAll('[data-chips-target]').forEach(renderChips);
    }

    function init() {
        document.querySelectorAll('[data-seg-target]').forEach(wireSeg);
        document.querySelectorAll('[data-segcheck-target]').forEach(wireSegCheck);
        document.querySelectorAll('[data-chips-target]').forEach(wireChips);
        // Ao abrir (edição pré-preenche os controles ocultos ANTES de showModal),
        // re-sincroniza a camada visual pelo atributo `open` do <dialog>.
        document.querySelectorAll('dialog.nvx-tx-modal').forEach(function (modal) {
            var obs = new MutationObserver(function (muts) {
                muts.forEach(function (m) {
                    if (m.attributeName === 'open' && modal.hasAttribute('open')) {
                        refreshModal(modal);
                    }
                });
            });
            obs.observe(modal, { attributes: true, attributeFilter: ['open'] });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
