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
        el.grid.style.gridTemplateColumns = '46px repeat(' + COLS + ', minmax(96px, 1fr))';

        var corner = document.createElement('div');
        corner.className = 'ph corner';
        frag.appendChild(corner);

        for (var c = 0; c < COLS; c += 1) {
            var ch = document.createElement('div');
            ch.className = 'ph' + (c === sel.c ? ' on' : '');
            ch.textContent = colName(c);
            frag.appendChild(ch);
        }

        for (var r = 0; r < ROWS; r += 1) {
            var rh = document.createElement('div');
            rh.className = 'ph rh' + (r === sel.r ? ' on' : '');
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
                if (r === sel.r && c2 === sel.c) { cls += ' sel'; }
                cell.className = cls;
                cell.dataset.r = String(r);
                cell.dataset.c = String(c2);
                cell.textContent = d.text;
                frag.appendChild(cell);
            }
        }

        el.grid.innerHTML = '';
        el.grid.appendChild(frag);
        syncFormulaBar();
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
        renderGrid();
        var cell = el.grid.querySelector('.pc.sel');
        if (cell) { cell.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
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
            var cell = ev.target.closest ? ev.target.closest('.pc') : null;
            if (!cell || !el.grid.contains(cell)) { return; }
            if (editing) { closeEditor(true); }
            sel = { r: parseInt(cell.dataset.r, 10), c: parseInt(cell.dataset.c, 10) };
            renderGrid();
        });

        el.grid.addEventListener('dblclick', function (ev) {
            if (ev.target.closest && ev.target.closest('.pc')) { openEditor(); }
        });

        el.grid.addEventListener('keydown', function (ev) {
            if (editing) { return; }
            switch (ev.key) {
                case 'ArrowUp': ev.preventDefault(); move(-1, 0); return;
                case 'ArrowDown': ev.preventDefault(); move(1, 0); return;
                case 'ArrowLeft': ev.preventDefault(); move(0, -1); return;
                case 'ArrowRight': ev.preventDefault(); move(0, 1); return;
                case 'Tab': ev.preventDefault(); move(0, ev.shiftKey ? -1 : 1); return;
                case 'Enter': ev.preventDefault(); openEditor(); return;
                case 'F2': ev.preventDefault(); openEditor(); return;
                case 'Delete':
                case 'Backspace': ev.preventDefault(); setCell(sel.r, sel.c, ''); return;
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

        // Atalhos globais só valem quando o foco está dentro da planilha.
        document.addEventListener('keydown', function (ev) {
            if (!(ev.ctrlKey || ev.metaKey)) { return; }
            var scope = document.querySelector('.sheet-screen');
            if (!scope || !scope.contains(document.activeElement)) { return; }
            var k = ev.key.toLowerCase();
            if (k === 's') { ev.preventDefault(); snapshot(); }
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
