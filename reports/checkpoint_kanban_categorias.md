# Checkpoint — Kanban de categorias: reskin + diagnóstico de dados

Data: 2026-07-27

## Task
Corrigir o Kanban de categorias do dashboard: fora do padrão visual (cards cinza chapado, sem
tokens) e dados aparentemente errados (tudo R$ 0,00). PARTE 1 reskin; PARTE 2 diagnóstico dos
dados. Preservar o drag-and-drop. Parar para revisão.

## PARTE 1 — Visual (tokenizar)
- Os cabeçalhos das 4 colunas (Fixas/Recorrentes, Essenciais, Opcionais, Supérfluos) já eram
  tokenizados (bolinha var(--accent)/--neg/--gold/--muted + --ink). O problema estava nos CARDS,
  renderizados por `public/scripts/kanban.js` com Tailwind fixo: `bg-slate-800/50`,
  `text-white`, `text-emerald-300/text-rose-300` → cinza chapado, ignora o tema.
- Reconstruído o card com estilos inline `var(--token)`:
  - Container: `.dashboard-kanban-card` agora vestido em dashboard.css (`.dash-screen
    .dashboard-kanban-card`): `background:var(--surface)`, `border:1px solid var(--line-soft)`,
    `clip-path:var(--cut)`, `border-radius:var(--r)`, sombra `--card-sh`, hover com borda accent
    e leve translateY.
  - Nome em `--ink`; labels "Atual"/"Anterior" em `--muted`; valores em `--f-num`
    (`data-balance`, respeita ocultar-saldos).
  - Variação em 3 estados por token: gasto subiu (diff > 0) → `--neg` (↑); gasto caiu (diff < 0)
    → `--pos` (↓); sem mudança (|diff| < 0,005) → `--muted` (→). Fim do emerald/rose fixo.
- Mobile: overflow-x + min-width por coluna já existia (`.dashboard-kanban-board > *` em
  @media). Drag-and-drop preservado: `drag`/`dragEnd`/`draggable`/`dataset.categoryId`/`data-column`/
  `window.drop`/`window.allowDrop`/persistência em localStorage intactos.

## PARTE 2 — Dados: o "tudo R$ 0,00" é CORRETO (não é bug)
- Fonte: `categoryVariationsData` (dashboard.php l.1704) = `$categoryVariations`. Cada card mostra
  `current` e `previous`, calculados pela query (l.149-177):
  - `current_month` = SUM de despesas (`type="expense"`) com **`is_effective = 1`** cuja
    `transaction_date` cai no MÊS CORRENTE.
  - `previous_month` = idem para o mês anterior.
  - variação = (current − previous) / previous (ou 100% se previous=0 e current>0; senão 0).
- Por que zero: os gastos do usuário estão majoritariamente **PENDENTES** (`is_effective=0`, com
  data de efetivação futura). A soma de despesas EFETIVADAS do mês é, de fato, 0 → os cards
  mostram R$ 0,00 legitimamente. Categorias aparecem mesmo assim quando têm recorrência
  (`HAVING ... OR has_recurring > 0`), exibindo 0/0 · 0%.
- **Decisão (documentada)**: manter **efetivadas apenas** no cálculo. Razão: TODAS as demais
  métricas de gasto do dashboard (hero, KPIs, gráficos, `totals()`) usam `is_effective=1`.
  Incluir pendentes no kanban o faria contradizer o resto do dashboard. O cálculo já corresponde
  à intenção do design (Atual = mês corrente, Anterior = mês anterior, variação entre os dois).
  NÃO inventamos número: se a fonte legítima dá zero, mostra zero.
- Como confirmar que está certo: efetivar (ou criar já efetivada) uma despesa no mês corrente →
  o card da categoria passa a mostrar o valor real em "Atual" e a variação vs mês anterior.
- Alternativa (NÃO aplicada): incluir pendentes = remover `AND t.is_effective = 1` das duas
  somas — mas diverge das outras métricas; deixaria a decisão inconsistente. Registrado caso o
  usuário prefira.

## Files changed
- **M** public/scripts/kanban.js — card tokenizado (inline var(--token)) + variação 3 estados.
- **M** public/styles/dashboard.css — `.dash-screen .dashboard-kanban-card` (surface/line/cut/
  hover).

## NÃO alterado
Lógica de drag-and-drop e persistência; a query de dados (o cálculo já estava correto e
consistente); nenhum handler financeiro.

## Validation performed
- `node --check` OK: kanban.js.
- dashboard.css: chaves 58/58.
- `php -l` no servidor (indisponível localmente).

## Remaining work (servidor)
- Kanban veste o tema (ametista claro e tinta escuro), sem cinza; variação colorida por
  --neg/--pos/--muted; drag entre colunas funciona e persiste (localStorage).
- Com uma despesa EFETIVADA no mês, o card mostra o valor certo em "Atual" e a variação confere.
- Demais telas sem regressão.

## Confidence
88% — reskin direto e diagnóstico de dados conclusivo (zero é verdade dado o estado pendente);
pende o passe visual e o teste com uma despesa efetivada no servidor.
