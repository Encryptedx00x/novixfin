/* NovixFin — Modo Planilha (bloco 1: grade, fórmulas, autosave, histórico).
 *
 * Contratos importantes:
 *  - O que trafega e o que é gravado é SEMPRE o que o usuário digitou (texto,
 *    número ou fórmula) — nunca o resultado calculado. O cálculo é local, na
 *    HyperFormula, e é refeito a cada carga.
 *  - Três mecanismos SEPARADOS, de propósito:
 *      autosave  → sobrescreve o estado vivo (debounce 800ms). Não gera histórico.
 *      snapshot  → marco no histórico (Ctrl+S / botão), no máximo 1 a cada 5min.
 *      undo/redo → pilha em memória (Ctrl+Z / Ctrl+Y), não toca no servidor.
 */
(function () {
    'use strict';

    var COLS = 12;
    var ROWS = 40;
    var AUTOSAVE_MS = 800;
    var SNAPSHOT_MIN_INTERVAL = 5 * 60 * 1000;
    var UNDO_LIMIT = 100;

    var el = {};
    var hf = null;
    var state = { id: 0, tabs: [], active: 0, versions: [] };
    var sel = { r: 0, c: 0 };
    // Ponta oposta da seleção em bloco. Igual a `sel` quando é uma célula só.
    var selEnd = { r: 0, c: 0 };
    var dragging = null; // 'select' | 'fill' enquanto o mouse está pressionado
    var undoStack = [];
    var redoStack = [];
    var saveTimer = null;
    var lastSnapshotAt = 0;
    var editing = null;

    // ---------------------------------------------------------------- utils
    function colName(i) {
        var s = '';
        i += 1;
        while (i > 0) {
            var m = (i - 1) % 26;
            s = String.fromCharCode(65 + m) + s;
            i = Math.floor((i - 1) / 26);
        }
        return s;
    }

    function refOf(r, c) { return colName(c) + (r + 1); }

    /** Índice de coluna a partir das letras ("A"→0, "AA"→26). */
    function colIndex(letters) {
        var n = 0;
        for (var i = 0; i < letters.length; i += 1) {
            n = n * 26 + (letters.charCodeAt(i) - 64);
        }
        return n - 1;
    }

    /** Retângulo normalizado da seleção atual. */
    function normRange() {
        return {
            r1: Math.min(sel.r, selEnd.r),
            r2: Math.max(sel.r, selEnd.r),
            c1: Math.min(sel.c, selEnd.c),
            c2: Math.max(sel.c, selEnd.c)
        };
    }

    function isMultiSelection() {
        var g = normRange();
        return g.r1 !== g.r2 || g.c1 !== g.c2;
    }

    /**
     * Desloca as referências relativas de uma fórmula em (dr, dc) — o que o
     * Excel/Sheets fazem ao copiar ou arrastar. `$` trava a parte absoluta.
     * Limitação conhecida: opera no texto todo, então uma referência escrita
     * DENTRO de uma string literal também seria deslocada.
     */
    function shiftFormula(formula, dr, dc) {
        if (dr === 0 && dc === 0) { return formula; }
        return formula.replace(
            /(\$?)([A-Z]{1,3})(\$?)(\d{1,5})/g,
            function (match, absCol, letters, absRow, digits) {
                var c = colIndex(letters);
                var r = parseInt(digits, 10) - 1;
                if (absCol !== '$') { c += dc; }
                if (absRow !== '$') { r += dr; }
                if (r < 0 || c < 0) { return '#REF!'; }
                return absCol + colName(c) + absRow + (r + 1);
            }
        );
    }

    /** Move a fórmula/valor de origem para o destino, ajustando referências. */
    function translated(raw, dr, dc) {
        if (typeof raw !== 'string' || raw.charAt(0) !== '=') { return raw; }
        return shiftFormula(raw, dr, dc);
    }

    function activeTab() { return state.tabs[state.active] || null; }

    function rawAt(r, c) {
        var tab = activeTab();
        if (!tab) { return ''; }
        return tab.cells[refOf(r, c)] || '';
    }

    var numFmt = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 });

    function status(text, busy) {
        if (!el.status) { return; }
        el.status.innerHTML = '';
        if (busy) {
            var dot = document.createElement('span');
            dot.className = 'sheet-dot';
            el.status.appendChild(dot);
        }
        el.status.appendChild(document.createTextNode(text));
    }

    function post(url, data) {
        var body = new FormData();
        body.append('_token', window.NOVIX_CSRF_TOKEN || '');
        Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
        return fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (res) { return res.json(); });
    }

    // ------------------------------------------------------- HyperFormula
    // Nomes de aba precisam ser únicos para a engine; deduplicamos na criação
    // para que fórmulas entre abas ('Página 2'!A1) continuem válidas.
    function uniqueName(base) {
        var names = state.tabs.map(function (t) { return t.name; });
        if (names.indexOf(base) === -1) { return base; }
        var n = 2;
        while (names.indexOf(base + ' (' + n + ')') !== -1) { n += 1; }
        return base + ' (' + n + ')';
    }

    function tabToMatrix(tab) {
        var maxR = 0;
        var maxC = 0;
        Object.keys(tab.cells).forEach(function (ref) {
            var m = /^([A-Z]{1,3})([0-9]{1,5})$/.exec(ref);
            if (!m) { return; }
            var c = 0;
            for (var i = 0; i < m[1].length; i += 1) {
                c = c * 26 + (m[1].charCodeAt(i) - 64);
            }
            maxC = Math.max(maxC, c);
            maxR = Math.max(maxR, parseInt(m[2], 10));
        });
        maxR = Math.max(maxR, ROWS);
        maxC = Math.max(maxC, COLS);
        var rows = [];
        for (var r = 0; r < maxR; r += 1) {
            var row = [];
            for (var c2 = 0; c2 < maxC; c2 += 1) {
                row.push(tab.cells[refOf(r, c2)] || null);
            }
            rows.push(row);
        }
        return rows;
    }

    function rebuildEngine() {
        if (!window.HyperFormula) { return; }
        var sheets = {};
        state.tabs.forEach(function (tab) { sheets[tab.name] = tabToMatrix(tab); });
        if (hf) {
            try { hf.destroy(); } catch (e) { /* engine já descartada */ }
        }
        hf = window.HyperFormula.buildFromSheets(sheets, {
            // Projeto comercial de código fechado — a chave 'gpl-v3' NÃO se aplica.
            licenseKey: 'internal-use-in-handsontable',
            useColumnIndex: true
        });
    }

    function sheetId() {
        if (!hf) { return null; }
        var tab = activeTab();
        if (!tab) { return null; }
        var id = hf.getSheetId(tab.name);
        return (id === undefined || id === null) ? null : id;
    }

    /** Valor exibido: número formatado, texto, ou o erro da fórmula. */
    function displayAt(r, c) {
        var raw = rawAt(r, c);
        if (raw === '') { return { text: '', num: false, neg: false, err: false }; }
        var sid = sheetId();
        if (sid === null) {
            return { text: raw, num: false, neg: false, err: false };
        }
        var v;
        try {
            v = hf.getCellValue({ sheet: sid, row: r, col: c });
        } catch (e) {
            return { text: '#ERRO', num: false, neg: false, err: true };
        }
        if (v === null || v === undefined) { return { text: '', num: false, neg: false, err: false }; }
        if (typeof v === 'object') {
            return { text: String(v.value || v.type || '#ERRO'), num: false, neg: false, err: true };
        }
        if (typeof v === 'number') {
            return { text: numFmt.format(v), num: true, neg: v < 0, err: false };
        }
        if (typeof v === 'boolean') {
            return { text: v ? 'VERDADEIRO' : 'FALSO', num: false, neg: false, err: false };
        }
        return { text: String(v), num: false, neg: false, err: false };
    }

    // ------------------------------------------------------------- render
    function renderGrid() {
        if (!el.grid) { return; }
        var frag = document.createDocumentFragment();
        var g = normRange();
        var multi = isMultiSelection();
        el.grid.style.gridTemplateColumns = '46px repeat(' + COLS + ', minmax(96px, 1fr))';

        var corner = document.createElement('div');
        corner.className = 'ph corner';
        frag.appendChild(corner);

        for (var c = 0; c < COLS; c += 1) {
            var ch = document.createElement('div');
            ch.className = 'ph' + (c >= g.c1 && c <= g.c2 ? ' on' : '');
            ch.textContent = colName(c);
            frag.appendChild(ch);
        }

        for (var r = 0; r < ROWS; r += 1) {
            var rh = document.createElement('div');
            rh.className = 'ph rh' + (r >= g.r1 && r <= g.r2 ? ' on' : '');
            rh.textContent = String(r + 1);
            frag.appendChild(rh);

            for (var c2 = 0; c2 < COLS; c2 += 1) {
                var cell = document.createElement('div');
                var d = displayAt(r, c2);
                var cls = 'pc';
                if (d.num) { cls += ' num'; }
                if (d.neg) { cls += ' neg'; }
                if (d.err) { cls += ' err'; }
                if (rawAt(r, c2).charAt(0) === '=') { cls += ' fx'; }
                var inBlock = r >= g.r1 && r <= g.r2 && c2 >= g.c1 && c2 <= g.c2;
                if (inBlock && multi) { cls += ' blk'; }
                if (r === sel.r && c2 === sel.c) { cls += ' sel' + (multi ? ' anchor' : ''); }
                cell.className = cls;
                cell.dataset.r = String(r);
                cell.dataset.c = String(c2);
                cell.textContent = d.text;

                // Alça de preenchimento: no canto inferior-direito da seleção.
                if (r === g.r2 && c2 === g.c2) {
                    var handle = document.createElement('span');
                    handle.className = 'sheet-fill';
                    handle.setAttribute('data-fill-handle', '');
                    handle.title = 'Arraste para preencher (ajusta as referências)';
                    cell.appendChild(handle);
                }

                frag.appendChild(cell);
            }
        }

        el.grid.innerHTML = '';
        el.grid.appendChild(frag);
        syncFormulaBar();
    }

    // ------------------------------------------------- copiar / colar / fill

    /** Bloco selecionado como TSV, com o que foi DIGITADO (fórmula inclusa). */
    function selectionAsTsv() {
        var g = normRange();
        var rows = [];
        for (var r = g.r1; r <= g.r2; r += 1) {
            var line = [];
            for (var c = g.c1; c <= g.c2; c += 1) {
                line.push(rawAt(r, c));
            }
            rows.push(line.join('\t'));
        }
        return rows.join('\n');
    }

    function copySelection() {
        var tsv = selectionAsTsv();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(tsv).then(function () {
                status(isMultiSelection() ? 'Bloco copiado' : 'Célula copiada', false);
            }, function () {
                status('Não foi possível copiar', false);
            });
        }
        return tsv;
    }

    /**
     * Cola TSV a partir da célula selecionada. Serve para bloco copiado daqui
     * e para TSV colado de fora (Excel/Sheets/planilha do Google).
     * `origin` != null significa cópia interna: as fórmulas são deslocadas.
     */
    function pasteTsv(text, origin) {
        if (typeof text !== 'string' || text === '') { return; }
        var lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
        if (lines.length && lines[lines.length - 1] === '') { lines.pop(); }
        if (!lines.length) { return; }

        pushUndo();

        var baseR = Math.min(sel.r, selEnd.r);
        var baseC = Math.min(sel.c, selEnd.c);
        var tab = activeTab();
        if (!tab) { return; }

        for (var i = 0; i < lines.length; i += 1) {
            var cols = lines[i].split('\t');
            for (var j = 0; j < cols.length; j += 1) {
                var r = baseR + i;
                var c = baseC + j;
                if (r >= ROWS || c >= COLS) { continue; }
                var value = cols[j];
                if (origin) {
                    value = translated(value, r - (origin.r + i), c - (origin.c + j));
                }
                var ref = refOf(r, c);
                if (value === '') { delete tab.cells[ref]; } else { tab.cells[ref] = value; }
            }
        }

        // Muitas células de uma vez: reconstruir a engine é mais barato e mais
        // seguro do que N chamadas de setCellContents.
        rebuildEngine();
        selEnd = {
            r: Math.min(ROWS - 1, baseR + lines.length - 1),
            c: Math.min(COLS - 1, baseC + lines[0].split('\t').length - 1)
        };
        sel = { r: baseR, c: baseC };
        renderGrid();
        scheduleSave();
        status('Bloco colado', false);
    }

    /** Delete/Backspace apaga o bloco inteiro, não só a célula-âncora. */
    function clearSelection() {
        if (!isMultiSelection()) {
            setCell(sel.r, sel.c, '');
            return;
        }
        var g = normRange();
        var tab = activeTab();
        if (!tab) { return; }
        pushUndo();
        for (var r = g.r1; r <= g.r2; r += 1) {
            for (var c = g.c1; c <= g.c2; c += 1) {
                delete tab.cells[refOf(r, c)];
            }
        }
        rebuildEngine();
        renderGrid();
        scheduleSave();
    }

    /** Preenche o retângulo arrastado a partir do bloco de origem. */
    function fillTo(target) {
        var src = normRange();
        var r2 = Math.max(src.r2, target.r);
        var c2 = Math.max(src.c2, target.c);
        if (r2 === src.r2 && c2 === src.c2) { return; }

        pushUndo();
        var tab = activeTab();
        var srcRows = src.r2 - src.r1 + 1;
        var srcCols = src.c2 - src.c1 + 1;

        for (var r = src.r1; r <= r2; r += 1) {
            for (var c = src.c1; c <= c2; c += 1) {
                var inSource = r <= src.r2 && c <= src.c2;
                if (inSource) { continue; }
                // Célula-modelo correspondente dentro do bloco de origem.
                var mr = src.r1 + ((r - src.r1) % srcRows);
                var mc = src.c1 + ((c - src.c1) % srcCols);
                var model = tab.cells[refOf(mr, mc)] || '';
                var ref = refOf(r, c);
                if (model === '') {
                    delete tab.cells[ref];
                } else {
                    tab.cells[ref] = translated(model, r - mr, c - mc);
                }
            }
        }

        rebuildEngine();
        selEnd = { r: r2, c: c2 };
        renderGrid();
        scheduleSave();
    }

    function syncFormulaBar() {
        if (el.ref) { el.ref.textContent = refOf(sel.r, sel.c); }
        if (el.fx) { el.fx.value = rawAt(sel.r, sel.c); }
    }

    function switchTab(i) {
        if (i === state.active) { return; }
        closeEditor(false);
        state.active = i;
        sel = { r: 0, c: 0 };
        renderTabs();
        renderGrid();
    }

    /**
     * Renomear in loco. O nome da aba é o identificador da sheet na engine, e
     * fórmulas entre abas ('Página 2'!A1) dependem dele — então renomear
     * reconstrói a engine, e um nome duplicado é recusado (viraria ambiguidade).
     */
    function startRename(index, button) {
        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'pt-rename';
        input.value = state.tabs[index].name;
        input.setAttribute('aria-label', 'Renomear aba');
        button.replaceWith(input);
        input.focus();
        input.select();

        var done = false;
        function commit(save) {
            if (done) { return; }
            done = true;
            var name = input.value.trim().slice(0, 60);
            var current = state.tabs[index].name;

            if (!save || name === '' || name === current) {
                renderTabs();
                return;
            }
            var clash = state.tabs.some(function (t, j) { return j !== index && t.name === name; });
            if (clash) {
                status('Já existe uma aba com esse nome', false);
                renderTabs();
                return;
            }
            pushUndo();
            state.tabs[index].name = name;
            rebuildEngine(); // a engine indexa sheets por nome
            renderTabs();
            renderGrid();
            scheduleSave();
        }

        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') { ev.preventDefault(); commit(true); }
            else if (ev.key === 'Escape') { ev.preventDefault(); commit(false); }
            ev.stopPropagation();
        });
        input.addEventListener('blur', function () { commit(true); });
    }

    function deleteTab(index) {
        if (state.tabs.length <= 1) {
            status('A planilha precisa de pelo menos uma aba', false);
            return;
        }
        var name = state.tabs[index].name;
        if (!window.confirm('Excluir a aba "' + name + '" e tudo que está nela?\n\nNão dá para desfazer depois de salvar.')) {
            return;
        }
        pushUndo();
        state.tabs.splice(index, 1);
        if (state.active >= state.tabs.length) { state.active = state.tabs.length - 1; }
        else if (index < state.active) { state.active -= 1; }
        sel = { r: 0, c: 0 };
        rebuildEngine();
        renderTabs();
        renderGrid();
        scheduleSave();
    }

    function renderTabs() {
        if (!el.tabs) { return; }
        el.tabs.innerHTML = '';
        state.tabs.forEach(function (tab, i) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'pt' + (i === state.active ? ' on' : '');
            b.title = 'Clique para abrir · duplo clique para renomear';

            var label = document.createElement('span');
            label.textContent = tab.name;
            b.appendChild(label);

            // O × só aparece na aba ativa, e só se houver mais de uma.
            if (i === state.active && state.tabs.length > 1) {
                var close = document.createElement('span');
                close.className = 'pt-x';
                close.textContent = '×';
                close.setAttribute('role', 'button');
                close.setAttribute('aria-label', 'Excluir aba ' + tab.name);
                close.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    deleteTab(i);
                });
                b.appendChild(close);
            }

            b.addEventListener('click', function () { switchTab(i); });
            b.addEventListener('dblclick', function (ev) {
                ev.preventDefault();
                if (i !== state.active) { switchTab(i); }
                startRename(i, b);
            });

            el.tabs.appendChild(b);
        });
    }

    function renderVersions() {
        if (!el.histMenu) { return; }
        el.histMenu.innerHTML = '';
        if (!state.versions.length) {
            var empty = document.createElement('div');
            empty.className = 'pv-item';
            empty.style.color = 'var(--muted)';
            empty.textContent = 'Nenhuma versão salva ainda.';
            el.histMenu.appendChild(empty);
            return;
        }
        state.versions.forEach(function (v) {
            var item = document.createElement('div');
            item.className = 'pv-item';

            var label = document.createElement('div');
            label.style.flex = '1';
            label.style.minWidth = '0';
            var title = document.createElement('div');
            title.style.fontSize = '12.5px';
            title.style.fontWeight = '600';
            title.textContent = v.label || 'Marco salvo';
            var when = document.createElement('div');
            when.style.fontFamily = 'var(--f-m)';
            when.style.fontSize = '11px';
            when.style.color = 'var(--muted)';
            when.textContent = v.created_at || '';
            label.appendChild(title);
            label.appendChild(when);

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ptool';
            btn.style.width = 'auto';
            btn.style.padding = '0 10px';
            btn.style.fontSize = '11.5px';
            btn.textContent = 'Restaurar';
            btn.addEventListener('click', function () { restoreVersion(v.id); });

            item.appendChild(label);
            item.appendChild(btn);
            el.histMenu.appendChild(item);
        });
    }

    // ----------------------------------------------------------- mutações
    function pushUndo() {
        undoStack.push(JSON.stringify({ tabs: state.tabs, active: state.active }));
        if (undoStack.length > UNDO_LIMIT) { undoStack.shift(); }
        redoStack.length = 0;
        syncUndoButtons();
    }

    function syncUndoButtons() {
        if (el.undo) { el.undo.disabled = undoStack.length === 0; }
        if (el.redo) { el.redo.disabled = redoStack.length === 0; }
    }

    function applySnapshotState(json) {
        var parsed = JSON.parse(json);
        state.tabs = parsed.tabs;
        state.active = Math.min(parsed.active, state.tabs.length - 1);
        sel = { r: 0, c: 0 };
        rebuildEngine();
        renderTabs();
        renderGrid();
        scheduleSave();
    }

    function undo() {
        if (!undoStack.length) { return; }
        redoStack.push(JSON.stringify({ tabs: state.tabs, active: state.active }));
        applySnapshotState(undoStack.pop());
        syncUndoButtons();
    }

    function redo() {
        if (!redoStack.length) { return; }
        undoStack.push(JSON.stringify({ tabs: state.tabs, active: state.active }));
        applySnapshotState(redoStack.pop());
        syncUndoButtons();
    }

    function setCell(r, c, raw) {
        var tab = activeTab();
        if (!tab) { return; }
        var ref = refOf(r, c);
        if ((tab.cells[ref] || '') === raw) { return; }
        pushUndo();
        if (raw === '') { delete tab.cells[ref]; } else { tab.cells[ref] = raw; }
        var sid = sheetId();
        if (sid !== null) {
            try {
                hf.setCellContents({ sheet: sid, row: r, col: c }, [[raw === '' ? null : raw]]);
            } catch (e) {
                rebuildEngine();
            }
        }
        renderGrid();
        scheduleSave();
    }

    // ------------------------------------------------------------- edição
    function openEditor(seedFromKey) {
        if (editing) { return; }
        var cell = el.grid.querySelector('.pc.sel');
        if (!cell) { return; }
        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'sheet-edit';
        input.value = seedFromKey === undefined ? rawAt(sel.r, sel.c) : seedFromKey;
        cell.appendChild(input);
        editing = { input: input, r: sel.r, c: sel.c };
        // Teclado virtual no mobile: garante que a célula em edição fique visível.
        cell.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);

        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                closeEditor(true);
                move(1, 0);
            } else if (ev.key === 'Escape') {
                ev.preventDefault();
                closeEditor(false);
            } else if (ev.key === 'Tab') {
                ev.preventDefault();
                closeEditor(true);
                move(0, ev.shiftKey ? -1 : 1);
            }
            ev.stopPropagation();
        });
        input.addEventListener('blur', function () { closeEditor(true); });
    }

    function closeEditor(commit) {
        if (!editing) { return; }
        var ed = editing;
        editing = null;
        var value = ed.input.value;
        if (ed.input.parentNode) { ed.input.parentNode.removeChild(ed.input); }
        if (commit) { setCell(ed.r, ed.c, value); } else { renderGrid(); }
        if (el.grid) { el.grid.focus(); }
    }

    function move(dr, dc) {
        sel.r = Math.min(ROWS - 1, Math.max(0, sel.r + dr));
        sel.c = Math.min(COLS - 1, Math.max(0, sel.c + dc));
        selEnd = { r: sel.r, c: sel.c }; // mover sem Shift desfaz o bloco
        renderGrid();
        var cell = el.grid.querySelector('.pc.sel');
        if (cell) { cell.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
    }

    /** Estende a seleção (Shift+setas): a âncora fica parada. */
    function extend(dr, dc) {
        selEnd.r = Math.min(ROWS - 1, Math.max(0, selEnd.r + dr));
        selEnd.c = Math.min(COLS - 1, Math.max(0, selEnd.c + dc));
        renderGrid();
    }

    // ------------------------------------------------------- persistência
    function payload() {
        return JSON.stringify({
            tabs: state.tabs.map(function (t) { return { name: t.name, cells: t.cells }; })
        });
    }

    function scheduleSave() {
        if (saveTimer) { clearTimeout(saveTimer); }
        status('Alterações não salvas', true);
        saveTimer = setTimeout(save, AUTOSAVE_MS);
    }

    function save() {
        saveTimer = null;
        status('Salvando…', true);
        post('/actions/sheets/save.php', { content: payload() })
            .then(function (res) {
                if (res && res.ok) { status('Salvo às ' + res.saved_at, false); }
                else { status((res && res.error) || 'Falha ao salvar', false); }
            })
            .catch(function () { status('Sem conexão — não salvo', false); });
    }

    function snapshot() {
        var now = Date.now();
        if (now - lastSnapshotAt < SNAPSHOT_MIN_INTERVAL) {
            var mins = Math.ceil((SNAPSHOT_MIN_INTERVAL - (now - lastSnapshotAt)) / 60000);
            status('Aguarde ' + mins + ' min para o próximo marco', false);
            return;
        }
        lastSnapshotAt = now;
        status('Criando marco…', true);
        post('/actions/sheets/snapshot.php', { content: payload(), label: 'Marco salvo' })
            .then(function (res) {
                if (res && res.ok) {
                    state.versions = res.versions || [];
                    renderVersions();
                    status('Marco criado às ' + res.saved_at, false);
                } else {
                    lastSnapshotAt = 0;
                    status((res && res.error) || 'Falha ao criar marco', false);
                }
            })
            .catch(function () {
                lastSnapshotAt = 0;
                status('Sem conexão — marco não criado', false);
            });
    }

    function restoreVersion(versionId) {
        status('Restaurando…', true);
        post('/actions/sheets/restore.php', { version_id: versionId })
            .then(function (res) {
                if (!res || !res.ok) {
                    status((res && res.error) || 'Falha ao restaurar', false);
                    return;
                }
                pushUndo();
                adoptContent(res.content);
                if (el.histMenu) { el.histMenu.hidden = true; }
                status('Versão restaurada', false);
            })
            .catch(function () { status('Sem conexão — não restaurado', false); });
    }

    function adoptContent(content) {
        var tabs = (content && Array.isArray(content.tabs)) ? content.tabs : [];
        state.tabs = tabs.map(function (t) {
            return { name: String(t.name || 'Página'), cells: (t.cells && typeof t.cells === 'object') ? t.cells : {} };
        });
        if (!state.tabs.length) { state.tabs = [{ name: 'Página 1', cells: {} }]; }
        state.active = 0;
        sel = { r: 0, c: 0 };
        rebuildEngine();
        renderTabs();
        renderGrid();
    }

    function load() {
        status('Carregando…', true);
        fetch('/actions/sheets/load.php', { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (res) {
                if (!res || !res.ok) {
                    status('Falha ao carregar a planilha', false);
                    return;
                }
                state.id = res.id;
                state.versions = res.versions || [];
                adoptContent(res.content);
                renderVersions();
                syncUndoButtons();
                status(res.updated_at ? 'Salvo em ' + res.updated_at : 'Pronto', false);
            })
            .catch(function () { status('Sem conexão', false); });
    }

    // --------------------------------------------------------------- wire
    function wire() {
        el.grid.setAttribute('tabindex', '0');

        el.grid.addEventListener('mousedown', function (ev) {
            // Alça de preenchimento tem prioridade sobre a seleção.
            if (ev.target.hasAttribute && ev.target.hasAttribute('data-fill-handle')) {
                ev.preventDefault();
                dragging = 'fill';
                return;
            }
            var cell = ev.target.closest ? ev.target.closest('.pc') : null;
            if (!cell || !el.grid.contains(cell)) { return; }
            if (editing) { closeEditor(true); }

            var r = parseInt(cell.dataset.r, 10);
            var c = parseInt(cell.dataset.c, 10);

            if (ev.shiftKey) {
                selEnd = { r: r, c: c };       // Shift+clique estende
            } else {
                sel = { r: r, c: c };
                selEnd = { r: r, c: c };
                dragging = 'select';           // arrastar seleciona o bloco
            }
            renderGrid();
        });

        // Arrastar: estende a seleção ou a alça de preenchimento.
        el.grid.addEventListener('mouseover', function (ev) {
            if (!dragging) { return; }
            var cell = ev.target.closest ? ev.target.closest('.pc') : null;
            if (!cell) { return; }
            var r = parseInt(cell.dataset.r, 10);
            var c = parseInt(cell.dataset.c, 10);
            if (dragging === 'select') {
                selEnd = { r: r, c: c };
                renderGrid();
            } else if (dragging === 'fill') {
                el.grid.setAttribute('data-fill-target', r + ':' + c);
            }
        });

        document.addEventListener('mouseup', function () {
            if (dragging === 'fill') {
                var target = el.grid.getAttribute('data-fill-target');
                if (target) {
                    var parts = target.split(':');
                    fillTo({ r: parseInt(parts[0], 10), c: parseInt(parts[1], 10) });
                    el.grid.removeAttribute('data-fill-target');
                }
            }
            dragging = null;
        });

        el.grid.addEventListener('dblclick', function (ev) {
            if (ev.target.closest && ev.target.closest('.pc')) { openEditor(); }
        });

        el.grid.addEventListener('keydown', function (ev) {
            if (editing) { return; }
            // Shift+setas estende o bloco em vez de mover a seleção.
            var step = ev.shiftKey ? extend : move;
            switch (ev.key) {
                case 'ArrowUp': ev.preventDefault(); step(-1, 0); return;
                case 'ArrowDown': ev.preventDefault(); step(1, 0); return;
                case 'ArrowLeft': ev.preventDefault(); step(0, -1); return;
                case 'ArrowRight': ev.preventDefault(); step(0, 1); return;
                case 'Tab': ev.preventDefault(); move(0, ev.shiftKey ? -1 : 1); return;
                case 'Enter': ev.preventDefault(); openEditor(); return;
                case 'F2': ev.preventDefault(); openEditor(); return;
                case 'Delete':
                case 'Backspace': ev.preventDefault(); clearSelection(); return;
                default: break;
            }
            if (ev.ctrlKey || ev.metaKey || ev.altKey) { return; }
            if (ev.key.length === 1) {
                ev.preventDefault();
                openEditor(ev.key);
            }
        });

        if (el.fx) {
            el.fx.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    setCell(sel.r, sel.c, el.fx.value);
                    el.grid.focus();
                } else if (ev.key === 'Escape') {
                    ev.preventDefault();
                    syncFormulaBar();
                    el.grid.focus();
                }
            });
        }

        if (el.undo) { el.undo.addEventListener('click', undo); }
        if (el.redo) { el.redo.addEventListener('click', redo); }
        if (el.snapshotBtn) { el.snapshotBtn.addEventListener('click', snapshot); }

        if (el.histBtn && el.histMenu) {
            el.histMenu.hidden = true;
            el.histBtn.addEventListener('click', function (ev) {
                ev.stopPropagation();
                el.histMenu.hidden = !el.histMenu.hidden;
            });
            document.addEventListener('click', function (ev) {
                if (el.histMenu.hidden) { return; }
                if (!el.histMenu.contains(ev.target) && ev.target !== el.histBtn) {
                    el.histMenu.hidden = true;
                }
            });
        }

        if (el.addTab) {
            el.addTab.addEventListener('click', function () {
                if (state.tabs.length >= 30) {
                    status('Limite de 30 abas atingido', false);
                    return;
                }
                pushUndo();
                state.tabs.push({ name: uniqueName('Página ' + (state.tabs.length + 1)), cells: {} });
                state.active = state.tabs.length - 1;
                sel = { r: 0, c: 0 };
                rebuildEngine();
                renderTabs();
                renderGrid();
                scheduleSave();
            });
        }

        // ---- área de transferência ----
        // Usa os eventos copy/paste (e não só Ctrl+C/V) para que o TSV colado
        // de fora — Excel, Google Sheets — chegue pelo clipboardData.
        var lastCopyOrigin = null;

        document.addEventListener('copy', function (ev) {
            var scope = document.querySelector('.sheet-screen');
            if (!scope || !scope.contains(document.activeElement)) { return; }
            if (editing) { return; }
            ev.preventDefault();
            var g = normRange();
            lastCopyOrigin = { r: g.r1, c: g.c1 };
            var tsv = selectionAsTsv();
            if (ev.clipboardData) { ev.clipboardData.setData('text/plain', tsv); }
            status(isMultiSelection() ? 'Bloco copiado' : 'Célula copiada', false);
        });

        document.addEventListener('paste', function (ev) {
            var scope = document.querySelector('.sheet-screen');
            if (!scope || !scope.contains(document.activeElement)) { return; }
            if (editing) { return; }
            ev.preventDefault();
            var text = ev.clipboardData ? ev.clipboardData.getData('text/plain') : '';
            // Cópia interna desloca as referências; TSV de fora entra literal.
            pasteTsv(text, lastCopyOrigin);
        });

        // Atalhos globais só valem quando o foco está dentro da planilha.
        document.addEventListener('keydown', function (ev) {
            if (!(ev.ctrlKey || ev.metaKey)) { return; }
            var scope = document.querySelector('.sheet-screen');
            if (!scope || !scope.contains(document.activeElement)) { return; }
            var k = ev.key.toLowerCase();
            if (k === 's') { ev.preventDefault(); snapshot(); }
            // Ctrl+Shift+Z é o "refazer" do resto do mundo (e o único no
            // macOS, onde Ctrl+Y não é convenção) — aceita os dois.
            else if (k === 'z' && ev.shiftKey) { ev.preventDefault(); redo(); }
            else if (k === 'z') { ev.preventDefault(); undo(); }
            else if (k === 'y') { ev.preventDefault(); redo(); }
        });

        // Autosave pendente não pode se perder ao sair da página.
        window.addEventListener('beforeunload', function () {
            if (saveTimer) {
                clearTimeout(saveTimer);
                saveTimer = null;
                var body = new FormData();
                body.append('_token', window.NOVIX_CSRF_TOKEN || '');
                body.append('content', payload());
                if (navigator.sendBeacon) {
                    navigator.sendBeacon('/actions/sheets/save.php', body);
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        el = {
            grid: document.getElementById('sheetGrid'),
            fx: document.getElementById('sheetFx'),
            ref: document.getElementById('sheetRef'),
            tabs: document.getElementById('sheetTabs'),
            status: document.getElementById('sheetStatus'),
            histMenu: document.getElementById('sheetHistMenu'),
            undo: document.getElementById('sheetUndo'),
            redo: document.getElementById('sheetRedo'),
            snapshotBtn: document.getElementById('sheetSnapshot'),
            histBtn: document.getElementById('sheetHistBtn'),
            addTab: document.getElementById('sheetAddTab')
        };
        if (!el.grid) { return; } // página sem o Modo Planilha

        if (!window.HyperFormula) {
            status('Motor de cálculo indisponível', false);
            return;
        }

        wire();
        syncUndoButtons();
        load();
    });
})();
