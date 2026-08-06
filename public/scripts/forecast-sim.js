/* NovixFin — Movimentações recentes: simulação da previsão + "Carregar mais".
 *
 * Camada de INTERAÇÃO. Não persiste nada, não chama endpoint nenhum e não toca
 * no saldo real: recarregar a página devolve tudo ao estado do servidor.
 *
 * A regra determinística da previsão continua sendo a do servidor
 * (saldo hoje + a receber − a pagar). O olhinho apenas responde
 * "como ficaria o saldo final se estas transações não existissem":
 *   - PENDENTE oculta  → sai de "a receber" (income) ou "a pagar" (expense).
 *   - EFETIVADA oculta → já está dentro do "saldo hoje", então o efeito dela é
 *     ESTORNADO do saldo hoje (receita de +500 oculta ⇒ saldo hoje −500;
 *     despesa de 200 oculta ⇒ saldo hoje +200).
 */
(function () {
    'use strict';

    var hidden = Object.create(null); // id da transação -> {kind, type, amount}

    // ------------------------------------------------------------ moeda
    // Espelha includes/currency.php: BRL usa ',' decimal e '.' milhar; as
    // demais, o inverso. Mantido aqui para o número simulado sair idêntico ao
    // que o PHP renderizou.
    var SYMBOLS = { BRL: 'R$', USD: 'US$', EUR: '€' };

    function formatCurrency(amount, currency) {
        var code = currency || 'BRL';
        var symbol = SYMBOLS[code] || code;
        var neg = amount < 0;
        var fixed = Math.abs(amount).toFixed(2);
        var parts = fixed.split('.');
        var thousands = code === 'BRL' ? '.' : ',';
        var decimal = code === 'BRL' ? ',' : '.';
        var intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousands);
        // O sinal vai DEPOIS do símbolo ("R$ -5,00"), como o number_format do
        // PHP produz em includes/currency.php — não "-R$ 5,00".
        return symbol + ' ' + (neg ? '-' : '') + intPart + decimal + parts[1];
    }

    // -------------------------------------------------- previsão simulada
    function recalc() {
        var base = window.NOVIX_FORECAST_BASE;
        if (!base) { return; }

        var hiddenIncoming = 0;   // pendentes de receita ocultas
        var hiddenOutgoing = 0;   // pendentes de despesa ocultas
        var hiddenEffectiveNet = 0; // efeito líquido das efetivadas ocultas
        var count = 0;

        Object.keys(hidden).forEach(function (id) {
            var tx = hidden[id];
            count += 1;
            if (tx.kind === 'pending') {
                if (tx.type === 'income') { hiddenIncoming += tx.amount; }
                else { hiddenOutgoing += tx.amount; }
            } else {
                hiddenEffectiveNet += (tx.type === 'income' ? tx.amount : -tx.amount);
            }
        });

        var today = base.today - hiddenEffectiveNet;
        var incoming = base.incoming - hiddenIncoming;
        var outgoing = base.outgoing - hiddenOutgoing;
        var finalBalance = today + incoming - outgoing;

        setText('forecastToday', formatCurrency(today, base.currency));
        setText('forecastIncoming', formatCurrency(incoming, base.currency));
        setText('forecastOutgoing', formatCurrency(outgoing, base.currency));
        setText('forecastFinal', formatCurrency(finalBalance, base.currency));
        setText('forecastHero', formatCurrency(finalBalance, base.currency));

        var notice = document.getElementById('forecastSimNotice');
        var tag = document.getElementById('forecastSimTag');
        var card = document.getElementById('forecastBreakdown');
        var simulating = count > 0;

        if (notice) { notice.hidden = !simulating; }
        if (tag) { tag.hidden = !simulating; }
        if (card) { card.classList.toggle('is-simulated', simulating); }
        var hero = document.getElementById('forecastHero');
        if (hero) { hero.classList.toggle('is-simulated', simulating); }
        setText('forecastSimCount', count + (count === 1 ? ' oculta' : ' ocultas'));
    }

    function setText(id, text) {
        var node = document.getElementById(id);
        if (node) { node.textContent = text; }
    }

    // ------------------------------------------------------- olhinho
    function applyRowState(row, isHidden) {
        row.classList.toggle('is-sim-hidden', isHidden);
        var btn = row.querySelector('[data-sim-eye]');
        if (!btn) { return; }
        btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
        var on = btn.querySelector('.dash-sim-eye-on');
        var off = btn.querySelector('.dash-sim-eye-off');
        // Troca por `style.display`, NÃO pela propriedade `.hidden`: estes são
        // SVGElement, que não implementa a IDL `hidden` do HTMLElement — atribuir
        // `.hidden` criava só uma propriedade solta no objeto e o ícone nunca
        // mudava (era exatamente este o bug do olho que não alternava).
        if (on) { on.style.display = isHidden ? 'none' : ''; }
        if (off) { off.style.display = isHidden ? '' : 'none'; }
        btn.title = isHidden
            ? 'Voltar para a previsão (simulação — nada foi salvo)'
            : 'Ocultar da previsão de saldo (simulação — nada é salvo)';
    }

    function toggleRow(row) {
        if (row.getAttribute('data-sim-eligible') !== '1') { return; }
        var id = row.getAttribute('data-sim-id');
        if (hidden[id]) {
            delete hidden[id];
            applyRowState(row, false);
        } else {
            hidden[id] = {
                kind: row.getAttribute('data-sim-kind'),
                type: row.getAttribute('data-sim-type'),
                amount: parseFloat(row.getAttribute('data-sim-amount')) || 0
            };
            applyRowState(row, true);
        }
        recalc();
    }

    function restoreAll() {
        hidden = Object.create(null);
        // Vale para linha revelada ou não — o estado é sempre reconstruído do DOM.
        document.querySelectorAll('.dash-recent-row.is-sim-hidden').forEach(function (row) {
            applyRowState(row, false);
        });
        recalc();
    }

    // -------------------------------------------------- carregar mais
    /** Linhas de transação AINDA ocultas — a única fonte da contagem. */
    function stillHiddenRows() {
        return document.querySelectorAll('.dash-recent-row[data-recent-hidden="1"]');
    }

    function wireLoadMore() {
        var btn = document.getElementById('dashRecentMore');
        if (!btn) { return; }
        var batch = parseInt(btn.getAttribute('data-batch'), 10) || 8;

        function syncButton() {
            var remaining = stillHiddenRows().length;
            var label = document.getElementById('dashRecentMoreCount');
            if (remaining <= 0) {
                // Some de vez: não há mais nada para revelar nesta tela.
                btn.disabled = true;
                btn.textContent = 'Nada mais a carregar';
                return false;
            }
            if (label) { label.textContent = ' (' + remaining + ' restantes)'; }
            return true;
        }

        btn.addEventListener('click', function () {
            // Revela pelo DISPLAY inline, não pelo atributo `hidden`: a linha
            // traz `display:flex` no próprio style, então esconder por atributo
            // só funcionava enquanto a regra CSS com !important chegasse ao
            // navegador — servida do cache, ela não chegava e nada era ocultado.
            // Revelar por seq garante que o cabeçalho do dia venha junto com a
            // primeira linha daquele dia.
            var hiddenRows = stillHiddenRows();
            var limit = Math.min(batch, hiddenRows.length);
            if (limit === 0) { syncButton(); return; }

            var lastSeq = parseInt(hiddenRows[limit - 1].getAttribute('data-recent-seq'), 10);
            document.querySelectorAll('.dash-recent-item[data-recent-hidden="1"]').forEach(function (node) {
                var seq = parseInt(node.getAttribute('data-recent-seq'), 10);
                if (isNaN(seq) || seq > lastSeq) { return; }
                node.style.display = 'flex';
                node.setAttribute('data-recent-hidden', '0');
            });

            syncButton();
        });

        // A contagem inicial também sai do DOM: se o servidor e o DOM
        // discordarem, quem manda é o que existe na página.
        syncButton();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var reset = document.getElementById('forecastSimReset');
        if (reset) { reset.addEventListener('click', restoreAll); }

        // Delegação: cobre as linhas reveladas pelo "Carregar mais".
        document.addEventListener('click', function (ev) {
            var btn = ev.target.closest ? ev.target.closest('[data-sim-eye]') : null;
            if (!btn || btn.disabled) { return; }
            var row = btn.closest('.dash-recent-row');
            if (row) { toggleRow(row); }
        });

        wireLoadMore();
    });
})();
