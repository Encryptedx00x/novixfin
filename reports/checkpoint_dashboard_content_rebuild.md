# Checkpoint — Fatia DASHBOARD (conteúdo) reconstruído fiel ao design

Data: 2026-07-25

## Tarefa
Reconstruir o miolo de `public/dashboard.php` para ficar IDÊNTICO ao design
(enxuto), preservando os 3 forms de transação e toda a lógica de dados. Sem
migração. Shell já estava portado; aqui foi só a área de conteúdo.

Fonte visual: Claude Design "NovixFin App.html" (projeto
cc9d061b-7dba-4245-a9e3-792106285382), tela DASHBOARD — obtida via DesignSync
(`standalone-app-src.dc.html`, bloco linhas 316-576), cacheada em
`scratchpad/app-src.html`.

## Files changed
- `public/dashboard.php` — miolo do `<section>` reconstruído (2266 -> 1704 linhas).
- `public/styles/dashboard.css` — grids responsivos + estados de aba + hover
  (aditivo; o mapa Tailwind->tokens legado foi mantido para vestir forms/modais).

## Estrutura-alvo entregue (nesta ordem, nada além)
1. HERO "Saldo consolidado": card `var(--grad)` + `clip-path:var(--cut-card)`,
   valor grande em `--f-num`, quebra por tipo de conta (Corrente/Poupança/
   Dinheiro/… conforme contas reais), chips de câmbio USD/EUR (só quando há taxa),
   botão de ocultar saldo (`#balance-visibility-btn` dentro do hero).
2. 3 KPIs: Receitas do mês, Despesas do mês (com delta % vs mês anterior quando
   há dado), Pendentes (contagem real + total).
3. Abas "Visão geral" / "Kanban de categorias" (toggle por `[hidden]`, JS inline).
4a. Previsão de saldo: painel do design com o gráfico REAL `#chartForecast`
    (forecast-chart.js, que já lê tokens via chart-tokens.js) + barra "Confiança
    da previsão" (Alta/Média/Baixa por desvio da média móvel).
4b. Metas em andamento: lista real (query nova, até 4 metas) + "Nova meta".
5. Kanban de categorias: `#kanbanBoard` + 4 colunas `data-column` tokenizadas.
6. FAB "Lançar" (`.dash-fab`, `filter:drop-shadow`).

## Dados reais religados
- Hero: `$accounts` (saldo/tipo; exclui `type=credit`), `$primaryCurrency`,
  `convertCurrency()` para os chips USD/EUR.
- KPIs mês: `$extendedHistoryData` (último ponto = mês atual; penúltimo = delta).
  Pendentes: query nova (COUNT + SUM de não-efetivadas na moeda principal).
- Metas: query nova (goals em andamento, COALESCE(title,name), com fallback name).
- Previsão: média móvel dos últimos 3 meses de `$extendedHistoryData`.

## Decisões fechadas aplicadas
- CRIAR TRANSAÇÃO movido para o MODAL do FAB (`#modal-create-dashboard`). O form,
  o handler `/actions/transactions/create.php`, CSRF, campos, ids e o trigger de
  categoria inline são os MESMOS — só migraram do card inline para o modal, no
  padrão dos modais de edição existentes. O FAB abre via `showModal()`.
- "Variação de Gastos por Categoria" e "Evolução Financeira": REMOVIDOS (sem
  equivalente no design; não cabem na Visão geral). REGISTRADOS como candidatos a
  uma fatia futura da aba Kanban — ver progress.md.

## Módulos antigos removidos (cada um tem tela própria)
Histórico recente (Histórico); Cartões/Faturas Vencidas/Próximas (Cartões);
Suas contas/Despesas por Conta/Receitas por Conta (Contas — resumo agora no HERO);
Conquistas recentes (Perfil); Meta em destaque -> virou "Metas em andamento";
Filtros da página, "Por categoria" (doughnut), "Previsão de Gastos" duplicada,
"Visualização Kanban" antiga com toggle. Canvases removidos: `#chartMonthly`,
`#chartCategories`, `#chartExpensesByAccount`, `#chartIncomesByAccount`
(todos null-guarded em dashboard.js — remoção é segura).

## Preservado (inegociável)
- 3 forms + handlers create/update/delete com CSRF, campos, recorrência/parcelas,
  confirmação de exclusão — intactos (form de criação movido verbatim para o modal).
- Nenhuma mudança em cálculo de saldo, previsão ou atomicidade.
- `$transactionCategories` continua definido ANTES do bloco JSON do rodapé (o
  modal de criação é renderizado antes) -> `window.NOVIX_CATEGORY_OPTIONS` ok.
- Contratos JS mantidos: `#kanbanContainer` + `#toggleKanbanView` (kanban.js só
  define `window.drop`/`allowDrop` e popula o board se ambos existirem — o toggle
  fica oculto, as abas controlam a visibilidade), `#kanbanBoard`, 4 `data-column`,
  `#chartForecast`, `#balance-visibility-btn/-icon/-text`, modais edit/delete/
  inline-category, ids do form (`type`, `category`, `account_id`, `amount`, …).

## Qualidade
`--f-num`/tabular-nums em todos os valores (via `[data-balance]` + inline);
`--accent-text` no texto pequeno colorido; gráfico e kanban lendo tokens; FAB com
`filter:drop-shadow`; focus-visible com anel `--accent`; CSS escopado `.dash-screen`;
sem emojis autorais (o único emoji é o do ícone de olho, injetado por
balance-visibility.js — controle global pré-existente); sem travessões nos textos
novos.

## Validação
- Local: PHP indisponível (`php -l` não roda aqui). Checagens estáticas OK:
  - `<?php`/`?>` 95/95; chaves 137/137; parênteses 912/912.
  - `<section>` 1/1; `<dialog>` 4/4; `<form>` 4/4.
  - Todos os hooks JS exigidos presentes; 4 `data-column`; 3 `name="_token"`;
    handlers create/update/delete presentes; 0 canvases removidos remanescentes;
    0 módulos removidos remanescentes.
- Servidor (pendente): `php -l public/dashboard.php` + `php tests/smoke.php`.

## TESTE (servidor) — foco da revisão
1. Dashboard idêntico ao design em um tema escuro e um claro.
2. Criar (via FAB -> modal), editar e excluir transação funcionam.
3. Hero mostra saldo consolidado real + quebra por conta; chips de câmbio quando há.
4. Previsão e kanban com as cores do tema; abas alternam Visão geral/Kanban.
5. Metas reais no painel; ocultar saldos funciona no botão do hero.
6. As demais telas sem regressão.

## Confiança
82% — reconstrução estática consistente e contratos JS verificados; pende o
`php -l` + smoke no servidor e o passe visual de fidelidade.
