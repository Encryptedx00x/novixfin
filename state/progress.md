# NovixFin v3 Execution Progress

## Current State
- **Phase**: CADASTROS (Categorias/Metas/Carteiras) reskin — **COMPLETE LOCALLY (em disco)**,
  mas **NÃO COMMITADO: o projeto não tem repositório git funcional** (ver abaixo). Parado
  para revisão. **10/10 telas tokenizadas.**

## Cadastros reskin — Categorias + Metas + Carteiras (2026-07-26)
- **Report**: /reports/checkpoint_cadastros_reskin.md
- **Files (gravados em disco no source tree)**: NEW public/styles/{categories,goals,wallets}.css;
  edited public/{categories,goals,wallets}.php (só visual + $pageStyles; modais já eram <dialog>).
- **Padrão**: section reescrita com estilo inline var(--token) (fidelidade nativa aos 15 temas)
  + CSS escopado por tela (.cat-screen/.goals-screen/.wallets-screen) que também veste o modal
  (mapa Tailwind->tokens sob .cat-modal/.goals-modal/.wallets-modal). Handlers, campos, CSRF,
  ids e JS intactos.
- **Categorias**: 2 colunas Receitas/Despesas; create (modal) + delete inline (confirm mantido);
  emojis 💰/💸 do select -> texto. (Sem edit/color/tx-count: backend não tem — reportado.)
- **Metas**: cards com distinção Meta x Limite (kind chip + status por token: meta em progresso/
  concluída; limite dentro/perto/estourado). **Bloco falso "+200 pontos/recompensa" REMOVIDO**
  (goal_completed não ligado). Delete ganhou confirm(). Modal #modal-goal + goals.js + goalsData
  intactos.
- **Carteiras**: lista (cards com chips de conta + Ver detalhes) e detalhe (tabela por conta +
  totais por moeda) reskinadas; editWallet/openWalletModal/get-accounts.php/account_ids[] e o
  delete com confirm intactos.
- **10/10 telas tokenizadas** (grep confirma cat-screen/goals-screen/wallets-screen + as 7).
- **BLOQUEIO DE VERSIONAMENTO (crítico, causa provável do "sumiço" anterior)**: o diretório
  do projeto NÃO está sob git funcional. `Desktop/NovixFin/.git` é uma pasta VAZIA (sem HEAD/
  objects/refs); o git sobe para o repo do home (`C:/Users/Encryptedx`, branch gsd/phase-1),
  que NÃO rastreia NENHUM arquivo do NovixFin (0 tracked, não ignorado). `git status` mostra os
  arquivos como `??` e `git diff --stat` vem vazio. **Não commitei** (não há repo válido; criar
  repo/commitar no repo errado é decisão do usuário — risco de vazar .env/commitar árvore
  enorme). Arquivos ESTÃO em disco no source tree (verificado por ls + wc). Decidir com o
  usuário como versionar antes de qualquer commit.

## Remoção do andaime — BLOQUEADA (agora DESBLOQUEÁVEL após validar estas 3)
- **Phase**: REMOÇÃO DO ANDAIME — **BLOQUEADA / NÃO EXECUTADA** (diagnóstico obrigatório
  reprovou). Nada removido. Parado para revisão.

## Remoção do andaime — BLOQUEADA (2026-07-26)
- **Motivo**: o grep provou que a ponte legada AINDA tem consumidores ativos. A premissa de
  "9 telas tokenizadas / Cartões foi a última" está incorreta.
- **3 telas NÃO reskinadas** (sem $pageStyles, sem classe escopada, markup Tailwind cru que
  só é colorido pelos 15 temas via themes.css legado): **Carteiras (wallets.php),
  Categorias (categories.php), Metas (goals.php)**. Remover themes.css agora quebraria as
  três em tema claro.
- **JS consumidor de data-legacy-theme** (migrar p/ getComputedStyle só no dia da remoção):
  chart-tokens.js:59 e dashboard.js:12.
- **Ruído (não são consumidores)**: archive/**, backups/**, e menções em COMENTÁRIOS de
  dashboard/history/profile/shell.css e themes.php. Único loader ativo: header.php (l.57,62).
- **Nada removido**: header.php, themes.php, themes.css, theme-compat.css intactos.
- **Próximo**: reskinar wallets/categories/goals (tokenizar como as outras; modais de
  wallets/categories já são <dialog>; goals já é <dialog>), validar, e SÓ ENTÃO remover o
  andaime. Reskin real está em 10/13 (contando shell/entrada/landing) — faltam 3 telas.

## Current State (anterior)
- **Phase**: CARTÕES reskin — **COMPLETE LOCALLY** (pende `php -l` + teste FINANCEIRO no
  servidor). Parado para revisão. **9/9 telas tokenizadas.**

## Cartões reskin — última tela tokenizada (2026-07-25)
- **Report**: /reports/checkpoint_cartoes_reskin.md
- **Files**: NEW public/styles/cards.css (escopo .cards-screen + .cards-modal); edited
  public/credit-cards.php (só visual + modais -> <dialog>).
- **Estratégia (financeiro sensível)**: mesma do dashboard/accounts — tokeniza por OVERRIDE
  escopado das utilitárias Tailwind sob `.cards-screen`/`.cards-modal`, SEM reescrever o
  markup. Nenhum id, name, campo, CSRF, confirm() ou o JS de distribuição multi-conta do
  pagamento foi tocado. Reskin puro.
- **Handlers financeiros preservados INTEGRALMENTE** (verificado): pay-bill.php,
  revert-payment.php (2 forms), delete-bill.php, save-transaction-settings.php. Os 3
  confirm() de revert/delete intactos com o mesmo texto. 5 CSRF tokens.
- **Modais -> <dialog> nativo** (resolve o empilhamento com o shell, igual às outras telas):
  payBillModal e transactionSettingsModal viraram `<dialog>`+showModal()/close(); ::backdrop
  tema-aware via cards.css. As 12 chamadas classList('hidden') da LÓGICA de distribuição
  (accountsSection/account-amount-input/distributionSummary/distributionWarning) foram
  mantidas intactas — só as 4 de abrir/fechar modal viraram showModal/close.
- **Qualidade**: valores em --f-num (data-balance); status Aberta/Paga/Vencida/Cancelada
  pelos tokens (Aberta->accent-2, Paga->pos, Vencida->neg, Cancelada->muted); CTAs com
  clip-path var(--cut) (o notch do design); inputs por tokens; focus-visible; 2 emojis de
  aviso dinâmico (⚠️/ℹ️) trocados por texto.
- **LIMITE honesto**: reskin por override de tokens, NÃO re-diagramação. Entrega os 15 temas
  (cor/tipografia/status/notch) com risco ~zero aos forms/financeiro, mas o layout segue a
  estrutura do PHP atual (cards empilhados), não o grid 2-col exato do design (cartão em
  gradiente com número mascarado, coluna de lançamentos ao lado). Re-diagramação = fatia
  futura possível, depois de validar o financeiro.

## FIM DO ANDAIME (próxima fatia, NÃO agora)
Com Cartões tokenizada, as 9 telas do app consomem tokens.css. A ponte de compatibilidade
(data-legacy-theme / theme-compat.css / NOVIX_THEME_LEGACY / themes.css legado) pode ser
removida — mas isso é uma FATIA SEPARADA, só depois que Cartões estiver validada em
produção (financeiro). Não removida nesta fatia.

## Current State (anterior)
- **Phase**: MODAIS CRUD -> <dialog> nativo — **COMPLETE LOCALLY** (pende `php -l` + teste
  tela por tela). Parado para revisão.

## Modais de CRUD padronizados no <dialog> nativo (2026-07-25)
- **Bug**: modais de `<div>` posicionados por CSS disputam z-index com o shell — com o modal
  aberto, o botão de recolher a sidebar continuava clicável por cima (notado em Contas). O
  `<dialog>`+showModal() sobe pra top layer do browser e resolve sozinho (dashboard já fazia).
- **Diagnóstico**: das telas com modal — profile (SEM modal), goals e history JÁ usavam
  `<dialog>`+showModal (ok); **categories, wallets, accounts** usavam `<div>`+classList
  ('hidden') = bug. Convertidas as três.
- **Correção**: `<div overlay><div card>` -> `<dialog card>` (top layer + ::backdrop nativo);
  open `classList.remove('hidden')` -> `showModal()`, close `classList.add('hidden')` ->
  `.close()`; Esc e clicar-fora (e.target===dialog) nativos. Backdrop dimming: accounts via
  accounts.css (`dialog.acc-overlay::backdrop`, tema-aware); categories/wallets via a variante
  Tailwind `backdrop:bg-slate-950/70` (mesma do dashboard).
- **Preservado**: forms, ids, names, campos, CSRF e handlers save/delete intactos; confirmação
  de exclusão (accounts deleteModal) intacta; `creditFields.classList` (subseção do form, NÃO
  é modal) mantido como estava.
- **Validação**: tags/dialog/form balanceados; 0 `<div>` overlay restante; `node --check` dos
  scripts inline OK. Servidor: `php -l public/{accounts,categories,wallets}.php` + teste tela
  a tela (abrir cobre a sidebar, recolher não fica clicável; criar/editar/excluir; Esc/fora).

## Current State (anterior)
- **Phase**: CONTAS + IMPORT/EXPORT + MOEDA reskin — **COMPLETE LOCALLY** (pende `php -l`
  + smoke no servidor e passe visual). Parado para revisão.

## Contas + Import/Export + Moeda — reskin tokenizado (2026-07-25)
- **Report**: /reports/checkpoint_contas_io_moeda_reskin.md
- **Telas reskinadas**: accounts.php, import.php, currency-settings.php. NEW
  public/styles/{accounts,io,moeda}.css (escopo .accounts-screen/.acc-overlay,
  .io-screen, .moeda-screen). export.php NÃO tocado (endpoint de download puro, sem UI).
- **Contas**: cards no layout do design (inline var(--token)); os DOIS toggles preservados
  como forms POST+CSRF (toggle-dashboard.php alimenta a quebra por conta do HERO;
  toggle.php ativa/inativa); modais criar/editar/excluir com ids/names/handlers e todo o
  JS intactos (themados por accounts.css). 📌/🗑️ -> SVG. REMOVIDOS os charts doughnut
  "Despesas/Receitas por Conta" (não existem na tela do design, usavam cores fixas) —
  candidatos a fatia futura se quiserem tokenizados.
- **Import/Export**: export.php é endpoint de download — as OPÇÕES viraram um form GET em
  import.php (formato + escopo) apontando pra export.php (design tem Import+Export lado a
  lado). Upload/preview/commit (csv_file/action/confirm/CSRF) preservados; preview com
  overflow-x. ADICIONADO "Seus IDs de referência" (contas+categorias reais, 2 SELECTs
  read-only). OMITIDO "Baixar modelo CSV" (sem endpoint; não inventei).
- **Moeda**: layout do design; TODOS os names/ids preservados (primary_currency,
  enabled_currencies[], convert_all_to_one/#convert-all-toggle, conversion_target_currency/
  #conversion-target, 6x conversion_rate_*, save.php). Toggle JS legado preservado
  (.hidden). Checkbox "Converter tudo" estilizado como switch (mesmo input). Taxas seguem
  alimentando os chips de câmbio do dashboard (lógica intocada).
- **Ponte de compatibilidade mantida** (Cartões ainda usa CSS legado).
- **Estado das 9 telas**: falta só CARTÕES (credit-cards.php). Depois dela, a ponte
  (theme-compat.css / data-legacy-theme / NOVIX_THEME_LEGACY) poderá ser removida.

## Current State (anterior)
- **Phase**: DASHBOARD CONTENT REBUILD — **COMPLETE LOCALLY** (pende `php -l` + smoke no
  servidor e passe visual de fidelidade). Parado para revisão. Shell segue COMPLETE LOCALLY.

## Dashboard content rebuild — CONCLUÍDO (2026-07-25)
- **Report**: /reports/checkpoint_dashboard_content_rebuild.md
- **Entrega**: miolo de public/dashboard.php reconstruído fiel ao design (2266 -> 1704
  linhas). Estrutura na ordem exata: HERO saldo consolidado (grad + cut-card, quebra por
  tipo de conta, chips câmbio USD/EUR, botão ocultar dentro do hero) -> 3 KPIs (Receitas/
  Despesas com delta %/Pendentes) -> abas Visão geral/Kanban -> Previsão (#chartForecast
  real + barra de Confiança) + Metas em andamento (query nova, até 4) -> Kanban tokenizado
  -> FAB "Lançar".
- **Design de referência (cache)**: scratchpad/app-src.html linhas 316-576 (extraído do
  projeto Claude Design cc9d061b via DesignSync `standalone-app-src.dc.html`). tokens.css já
  define TODOS os tokens do design (--grad/--stage-*/--cut-card/--card-b/--card-sh/--f-num/
  --pos/--neg/--gold) nos 15 temas -> os blocos novos usam estilo inline com var(--token),
  fidelidade nativa, sem CSS base novo.
- **DECISÃO: CRIAR TRANSAÇÃO -> modal do FAB** (`#modal-create-dashboard`). Form/handler/
  CSRF/campos/ids/inline-category IDÊNTICOS; só migrou do card inline para o modal. FAB abre
  via showModal(). O modal é renderizado ANTES do bloco JSON do rodapé para preservar
  `$transactionCategories` -> window.NOVIX_CATEGORY_OPTIONS.
- **DECISÃO REGISTRADA — "Variação de Gastos por Categoria" e "Evolução Financeira":
  REMOVIDAS** (sem equivalente no design; não cabem na Visão geral). Candidatas a uma FATIA
  FUTURA da aba Kanban de categorias. Os dados ainda são preparados no data-prep
  (`$categoryVariations`/`$categoryComparison`/`$historyData`) e vão no bloco JSON do rodapé
  (`categoryVariationsData`/`monthlyData`) — kanban.js pode consumi-los quando a fatia
  chegar. NÃO acomodadas agora.
- **Módulos removidos** (cada um tem tela própria): Cartões/Faturas, Suas contas/
  Despesas-Receitas por Conta (resumo agora no HERO), Meta em destaque (virou Metas em
  andamento), filtros da página, doughnut "Por categoria", previsão duplicada. Canvases
  removidos: #chartMonthly/#chartCategories/#chartExpensesByAccount/#chartIncomesByAccount
  (todos null-guarded em dashboard.js).

## Dashboard content rebuild — COMPLEMENTO (2026-07-25)
- **Report**: /reports/checkpoint_dashboard_content_complement.md
- **Correção de brief**: "Histórico recente" e "Conquistas recentes" NÃO eram duplicação —
  o design da tela DASHBOARD as inclui como RESUMO/atalho. Foram RE-ADICIONADAS (aditivo;
  hero/KPIs/abas/previsão/metas intactos). Saíram da lista de "removidas".
- **MOVIMENTAÇÕES recentes**: dentro da aba Visão geral, após previsão+metas. Agrupado por
  dia com subtotal assinado; linha com inicial da categoria, descrição, conta, pill de
  status (Efetivada=--pos / Pendente=--gold, chip corrigido), valor em --f-num, e "Ver
  histórico completo". Reusa `$recentTransactions` (mesmo array do rodapé JSON — SEM nova
  query no data-prep). Sem coluna de horário: `transaction_date` é DATE e listByUser não
  traz hora — omitida em vez de inventar.
- **EFETIVAR na linha**: pendentes com conta trazem botão "Efetivar" que POSTa um form
  oculto para `/actions/transactions/update.php` (MESMO handler da edição) com todos os
  campos atuais + `is_effective=1` + CSRF + redirect_to=dashboard.php. Não há endpoint de
  efetivar-1-clique no repo; update.php é o handler que a edição do Histórico usa e já faz
  a reversão/aplicação de saldo na transição de is_effective. Só aparece quando há
  account_id (update.php exige conta válida).
- **CONQUISTAS recentes**: dados REAIS de gamificação (`$user['points']`/`$user['badges']`
  + `\Novix\Services\GamificationService::BADGE_MILESTONES`), mesma fonte do Perfil — não o
  mock antigo. Mostra só emblemas conquistados (limiar maior primeiro, até 4), ícones em
  SVG (sem emoji), "Ver perfil". O array `$achievements` do data-prep (mock) NÃO é mais
  usado na view (segue calculado inofensivamente; candidato a limpeza futura).
- **Sem alteração no data-prep (1-872)** e sem migração. Os 3 forms seguem intactos.

## Hotfix — FAB "Lançar" não abria o modal (2026-07-25)
- **Causa**: o `<script>` inline que liga o clique do FAB roda no parse, ANTES de o
  `<dialog id="modal-create-dashboard">` existir no DOM (o dialog vem depois, fora do
  `<section>`). `getElementById('modal-create-dashboard')` retornava null, o guard
  `if (!fab || !modal) return;` abortava e o listener nunca era ligado. Os 3 ids/dialog/
  handler estavam corretos — era só timing. (O modal de editar funciona porque dashboard.js
  liga em DOMContentLoaded.)
- **Fix**: a fiação do FAB passou a rodar em `DOMContentLoaded` (com fallback se o DOM já
  estiver pronto) — mesmo padrão do dashboard.js. Nada tocado no form/CSRF/create.php nem no
  modal de edição. Esc/cancelar/clicar-fora seguem nativos do `<dialog>`.
- **Validação**: `node --check` do script inline OK; tags/chaves/parênteses balanceados.
  Servidor: `php -l public/dashboard.php` + clique testar abrir/criar/fechar + editar.
- **Contratos JS preservados/verificados**: #kanbanContainer + #toggleKanbanView (oculto —
  kanban.js só popula e define window.drop/allowDrop se ambos existirem; abas controlam a
  visibilidade), #kanbanBoard + 4 data-column, #chartForecast, #balance-visibility-btn/
  -icon/-text (agora no hero), modais edit/delete/inline-category, ids do form.
- **CSS**: dashboard.css mantém o mapa Tailwind->tokens legado (veste os forms/modais que
  seguem usando utilitárias) + novos helpers escopados .dash-stats/.dash-dgrid/.dash-kan/
  .dash-tabpanel/.dash-tab. Escopo 100% sob .dash-screen.
- **Data-prep 1-872 NÃO tocado**; adicionadas só queries read-only no topo do `<section>`
  (pendentes COUNT+SUM, metas em andamento) + derivações de mês/previsão a partir de
  `$extendedHistoryData`.

## Shell Slice — moldura do app tokenizada (2026-07-23)
- **Report**: /reports/checkpoint_shell_reskin.md
- **Escopo**: sidebar (logo cristal, grupos PRINCIPAL/GESTÃO, ícones de linha, item ativo
  por tema, rodapé) + barra superior (eyebrow + título). Afeta as 10 telas do app.
- **Files**: NEW public/styles/shell.css (100% escopado #nvx-shell/.nvx-*), NEW
  public/scripts/shell.js (recolher via data-side="rail", persiste localStorage
  'sidebarCollapsed'); includes/layout/header.php (markup do shell reescrito, <head>+ponte
  de tema verbatim); includes/layout/footer.php (carrega shell.js; fechadores já batiam).
- **Nav**: 15 emojis REMOVIDOS (grep confirma zero na nav). Ícones de linha família Feather
  (a mesma do design — confirmada pelos eye/search/bell capturados). Os paths exatos por
  item do design ficaram além do corte de 256KB do cache; usei os glifos Feather
  correspondentes, trocáveis 1:1 se o HTML salvo divergir.
- **Import/Export**: UM item -> import.php (que já linka export.php); ativo também em
  export.php. Registrado.
- **OMITIDO por falta de backend (nada falso)**:
  - Sino de notificações: OMITIDO (zero backend).
  - Busca no topo: OMITIDA (não há full-text; filtros do Histórico são por campo).
  - Seletor de período/mês no topo: OMITIDO (sem parâmetro global de mês; Dashboard tem o
    próprio filtro na página).
  - Consequência: lado direito da topbar fica limpo (só eyebrow+título). Reportado — é
    honesto, não fica estranho.
  - "Plano Pessoal": NÃO inventado. Rodapé mostra nome + email.
- **Ocultar saldos**: preservado (mesmo #balance-visibility-btn/-icon/-text, mesmo handler,
  balance-visibility.js intocado). Consolidado em UM controle no rodapé da sidebar (design
  mostra na sidebar E na topbar; um só evita id duplicado). Renderizado só fora do
  Dashboard (que tem o próprio botão no conteúdo — sem id duplicado). Sair preservado.
- **Compatibilidade**: ponte data-legacy-theme/theme-compat.css/NOVIX_THEME_LEGACY mantida
  verbatim. shell.css escopado. O shell antigo (#sidebar/#mainContent + main.js) some; o
  código de sidebar do main.js fica inerte (getElementById null, guardado por if) e as
  regras #sidebar do main.css miram elemento ausente. Nenhuma tela referencia ids removidos.
- **Validação local**: node --check shell.js OK; header PHP balanceado; tags do shell
  batem com o footer; nav sem emoji; contratos JS (balance + shell) presentes; as 10 telas
  emparelham header+footer. PHP local indisponível -> servidor: php -l header/footer + smoke.
- **TESTE (servidor)**: percorrer as 9 telas — shell idêntico, item ativo, recolher,
  Ocultar saldos, 4 legadas sem regressão, um tema claro e um escuro.

## Landing fix + Dashboard Slice (2026-07-23)
- **Report**: /reports/checkpoint_dashboard_reskin.md

### 0) Landing — rótulo/headline por tema
- Causa real: NÃO era falta de lógica. landing.js já atualizava headline/subtítulo/rótulo/
  rodapé via renderTheme() dentro do startViewTransition. O bug era UM hook HTML malformado
  em index.php:144 — `data-lp="selName"` tinha caído dentro da tag de fechamento
  `</span data-lp=...>` (artefato do transform da fatia Landing). O rótulo congelava.
- Fix: `<span data-lp="selName">Ametista</span>` bem-formado. Sem outros hooks malformados.
  15 headlines distintos confirmados. Só 1 linha mudada.

### 1) Dashboard — DECISÃO DE ESTRATÉGIA
- 2256 linhas com os 3 forms financeiros + recorrência + faturas de cartão. Reescrever o
  markup = risco alto p/ os forms. Escolhi **tokenizar por override escopado**, não reescrever.
- NEW public/styles/dashboard.css: mapeia utilitárias Tailwind (slate/violet/emerald/rose)
  → tokens dos 15 temas, 100% escopado sob `.dash-screen` (0 seletores fora). Zero markup de
  form/id/data-attr/JS tocado. Vence o themes.css legado via prefixo `html .dash-screen` +
  `!important` só nas cores (dashboard.css carrega depois via $pageStyles).
- Markup mínimo: `.dash-screen` na section + 3 dialogs; `id="dash-create"`; FAB de
  lançamento (filter:drop-shadow, não box-shadow — clip-path recortaria).
- Preservado/verificado por script: 3 forms + CSRF; todos getElementById de dashboard.js e
  kanban.js; 5 canvas de gráfico; tags balanceadas (section/dialog/form).
- Item 3: [data-balance] → --f-num tabular; focus-visible anel --accent; kanban overflow-x
  no mobile; --accent-text no texto pequeno colorido.

### 2) Gráficos seguindo tokens (ponto crítico) — ACHADO
- kanban.js: NÃO lê data-theme, SEM cores fixas (100% CSS). Nada a mudar.
- dashboard.js (~94 cores fixas) e forecast-chart.js (~41) usavam violeta/emerald/rose fixos.
- NEW public/scripts/chart-tokens.js: window.NVXCharts.palette() lê os tokens via
  getComputedStyle e devolve cores concretas + helper .a(alpha) (canvas não resolve var()).
- Ambos os arquivos: paleta P resolvida no build; todas as cores fixas → P.accent/pos/neg/
  gold/muted/ink/surface com o alpha original. chart-tokens.js primeiro no $pageScripts.
- BUG pego: o transform reescreveu o '#cbd5f5' que eu usei como fallback dentro do próprio
  objeto fallback → auto-referência circular. Corrigido p/ literal '#8891a8' nos 2 arquivos.

### LIMITE (transparência)
- Reskin do dashboard é por override de tokens, NÃO re-diagramação estrutural. Entrega os 15
  temas (cor/tipografia/chips/FAB) com risco ~zero aos forms, mas o layout dos cards segue o
  do PHP atual (não o layout exato do design). Re-diagramação = possível fatia futura.

### TESTE (servidor)
1. Landing: trocar tema muda cor+fonte+rótulo+headline+subtítulo juntos.
2. Dashboard: criar, editar, excluir transação funcionam.
3. Gráficos e kanban com as cores do tema em um tema escuro e um claro.
4. As outras 7 telas sem regressão.

## Histórico Slice — reskin + housekeeping de ambiente (2026-07-23)

## Histórico Slice — reskin + housekeeping de ambiente (2026-07-23)
- **Report**: /reports/checkpoint_historico_reskin.md

### HOUSEKEEPING (item 0) — ambiente de produção atualizado
- **Produção NÃO é mais Hostinger shared hosting**: agora é VPS com Docker + Caddy +
  MariaDB rodando **PHP 8.3.32**. AGENTS.md e context.md atualizados.
- **Alvo de linguagem: PHP 8.1+ para código NOVO.** Match, str_contains, nullsafe,
  union types, promoção de construtor e enums liberados. **NÃO retrofitar** o código
  7.4 existente (churn + risco sem ganho); modernizar só arquivo já em edição por
  outro motivo. `catch` com variável vira estilo da casa.
- **Rota @actions documentada (armadilha de deploy):** docroot é
  `/srv/sites/novixfin/public` e `actions/` fica FORA dele. Sem a rota `@actions` em
  `/srv/stack/Caddyfile`, todo `POST /actions/*` cai no fallback e devolve a landing
  com **HTTP 200** — sem 404, sem 500, sem log. Form "não faz nada" em produção mas
  funciona local ⇒ checar `@actions` PRIMEIRO.

### JS (item 3) — achado
`public/scripts/history.js` **não consome data-theme** (diferente do dashboard.js, que
precisou de ajuste na fatia Perfil). Nenhuma alteração necessária no JS.

### RESKIN
- NEW `public/styles/history.css` — 100% escopado sob `.hist-screen`/`.hist-modal`
  (verificado: 0 seletores fora do escopo). Ponte data-legacy-theme intacta, mapa
  NOVIX_THEME_LEGACY NÃO alterado.
- `public/history.php` — estrutura do design (filtros, 3 cards de resumo, lista
  agrupada por dia com subtotal, chips, estado vazio, breakpoints 1279/1020/640).
- **Contrato do history.js verificado por script: 21 IDs + 8 data-attrs + 16 campos
  name= — nenhum faltando.** `data-balance` mantido nos 8 pontos monetários.
- **AÇÕES DESTRUTIVAS preservadas**: os 3 fluxos seguem com `<dialog>` de confirmação,
  mesmo texto de aviso, CSRF, `redirect_to` e mesma condição de exibição do botão por
  conta. Handlers intocados.
- **Desvio deliberado**: o design põe uma lixeira por linha no "Resumo por conta";
  NÃO portei — criaria novo ponto de entrada destrutivo. Reportado.
- **Não inventado**: filtros de Categoria/Moeda, paginação, botão "Efetivar" inline e
  chips de filtros ativos existem no design mas não no backend — não renderizados.
- Qualidade: `--f-num`+tabular-nums nos números, `--accent-text` no texto pequeno
  colorido, chips só com tokens (0 cores locais, 0 classes Tailwind de cor restantes),
  focus-visible com anel `--accent`.

### RISCO A OLHAR NO TESTE
- A visão "Lista" passou de tabela plana para **agrupada por dia com subtotal** — é a
  mudança visual mais perceptível (apresentação apenas, sobre dados já carregados).
- Subtotais/resumos somam sem conversão de moeda (mesma limitação da soma anterior).
  Se o uso multi-moeda for real, vale um follow-up — não inventei regra financeira.

### TESTE (servidor)
1. Filtros funcionam e preservam estado na URL (inclusive junto com `view`).
2. Editar transação salva.
3. **As duas ações destrutivas ainda pedem confirmação** e funcionam.
4. Tela correta em um tema escuro e um claro.
5. As outras 8 telas sem regressão visual.

## Perfil Slice — theming do shell + Perfil tokenizado (2026-07-22)

## Perfil Slice — theming do shell + Perfil tokenizado (2026-07-22)
- **Report**: /reports/checkpoint_perfil_theming_shell.md
- **Scope**: shell aditivo (tokens.css) + reskin só do Perfil. Nenhum core financeiro.

### DIAGNÓSTICO (item 1 do escopo)
- themes.css só conhece dark/black/light; nenhum dos 15 slugs casa, então as 9 telas
  internas caem hoje no visual base escuro do Tailwind, qualquer que seja o tema.
- **Regressão encontrada**: a migração da fatia Entrada mapeou light -> ametista, então
  quem usava tema claro perdeu o claro nas telas internas desde então.
- Formas dos seletores legados: 88 `body[data-theme=...]`, 96 descendentes
  `[data-theme=...] .classe`, e só 2 ancorados em `html`.
- Único consumidor JS: dashboard.js (cor da legenda dos gráficos).

### DECISÃO DE COMPATIBILIDADE (uma só, documentada)
Ponte de atributo duplo, viável porque 184/186 regras casam por body/descendência:
- `<html data-theme="<slug>" data-legacy-theme="<dark|light>">` -> tokens.css + Perfil
- `<body data-theme="<legado>" data-novix-theme="<slug>">` -> themes.css veste as 9 telas
- `theme-compat.css` repõe as 2 regras de fundo ancoradas no `<html>`.
- Mapa `NOVIX_THEME_LEGACY` (includes/themes.php): 8 slugs claros -> `light`;
  7 escuros -> `dark`. Usei `dark` (não `black`) para os escuros de propósito: assim
  **usuários de tema escuro não veem mudança nenhuma**, e só os claros mudam — voltando
  ao tema claro legado, que é CSS já shipado e polido (checkpoint_p1_light_theme_polish).
- Remover a ponte quando todas as telas internas consumirem tokens.css.

### DÍVIDA REGISTRADA — consolidação de tokens
Os tokens dos 15 temas agora existem em TRÊS arquivos: `entrada.css`, `landing.css`
(ambos em produção, intocados) e o novo `tokens.css` (fonte canônica do app, e o único
com `--f-num`/`--accent-text`). Consolidar num único arquivo só depois que as telas
internas estiverem reskinadas — mexer agora arriscaria Entrada/Landing já entregues.

### Files
- NEW public/styles/tokens.css (SÓ custom properties — garantia verificada por script:
  0 seletores de componente, 0 declarações não-token), theme-compat.css, profile.css
  (100% escopado sob `.pf-screen`).
- includes/themes.php: + NOVIX_THEME_META, NOVIX_THEME_FONTS/novix_theme_fonts_url(),
  NOVIX_THEME_LEGACY/novix_legacy_theme(). Whitelist e novix_valid_theme() REUSADOS.
- includes/layout/header.php: fallback 'dark' -> ametista validado; tema resolvido antes
  do `<html>` (server-side, sem flash); atributos da ponte; carrega tokens/compat/fontes;
  novo hook `$pageStyles` (espelha `$pageScripts`); script inline sincroniza localStorage.
- public/scripts/dashboard.js: isLight passa a ler data-legacy-theme (1 linha).
- src/Services/GamificationService.php: 3 consts private -> public (SÓ visibilidade).
- public/profile.php: reskin completo.

### Perfil — honestidade dos dados
- 3 forms + handlers + CSRF + onchange->submit preservados; nenhum handler alterado.
- Gamificação REAL (users.points/level/badges), limiares lidos de GamificationService.
- "Como os pontos entram" mostra só eventos com caller real (+10 efetivada, +15 fatura
  paga, +2 pendente). **goal_completed (+50) e spending_limit_held (+20) NÃO aparecem** —
  existem no service mas nenhum caller dispara (verificado por grep).
- Badges: critério exibido como limiar de PONTOS, porque é assim que recomputeBadges()
  concede (inclusive a badge de nome `goal_complete`).
- Corrigidos: `data-balance` removido dos pontos (não é dinheiro); input hidden `theme`
  duplicado removido; `mb_*` guardado por function_exists (mbstring não garantido).

### TESTE (servidor) — foco da revisão
1. Trocar tema no Perfil persiste em users.theme e reflete ao recarregar.
2. Mesmo tema aparece na landing/login (localStorage).
3. Ocultar-saldo e dados pessoais seguem funcionando.
4. **Confirmar Dashboard, Histórico, Cartões, Contas, Categorias, Metas, Import/Export,
   Moedas e Carteiras.** Esperado: temas escuros idênticos a hoje; temas claros passam a
   renderizar o tema claro legado (mudança intencional, restaura a regressão).

## Landing Slice — Seletor Seta (2026-07-22)

## Landing Slice — Seletor Seta (2026-07-22)
- **Report**: /reports/checkpoint_landing_seletor_seta.md
- **Scope**: public/index.php reescrito com a landing tokenizada aprovada (hero com
  setas grandes sem fundo, carrossel acoplado clicável, View Transitions, modo
  Apresentar, olhinho, FAQ, comparativo, CTA final). Página pública; auth/saldo/
  cartão/recorrência intocados. Redirect index.php raiz preservado.
- **Assets DECISION**: imagens commitadas (não CSS puro) em public/assets/:
  quartzo.png = cluster de cristais (esq.), cristaldireito.png = cristal facetado
  (dir.), marmore-trim.png = tira de mármore (extraída do base64 da referência,
  verificada byte a byte). marmore.png movido junto mas não referenciado.
- **Files**: public/index.php (rewrite), NEW public/styles/landing.css,
  NEW public/scripts/landing.js, public/assets/*.png.
- **Tema**: mesma chave localStorage['novixfin-theme'] + whitelist de
  includes/themes.php (pre-paint snippet). Setas hero + seletor + carrossel com
  UMA fonte de estado; document.startViewTransition com fallback e
  prefers-reduced-motion sem morph. CTAs viram "Abrir app" se autenticado.
- **CSS DECISION**: landing.css separado de entrada.css (tokens da landing têm
  --prism/--disp-* e escopo ametista v5); unificar num tokens.css comum é
  follow-up consciente, não desta fatia.
- **Validation**: node --check landing.js OK; 0 placeholders; tags PHP balanceadas.
  Pendente no servidor: php -l public/index.php + browser pass (tema sincronizado,
  persistência até login/registro, mobile).

## Entrada Slice — Login/Registro 15 temas + users.theme (2026-07-22)
- **Report**: /reports/checkpoint_entrada_theme_slice.md
- **Scope**: login.php/register.php reskin (design tokenizado v4, herança de tema
  pré-auth via localStorage['novixfin-theme']), whitelist central dos 15 slugs,
  migração users.theme, bridge localStorage -> DB no cadastro. Nenhum arquivo do
  core financeiro tocado.
- **Files**: NEW includes/themes.php, NEW migrations/20240101000007_theme_15_slugs_v3.php,
  NEW public/styles/entrada.css, NEW includes/layout/entrada.php;
  edited actions/profile/update-theme.php, includes/auth.php (createUser theme +
  session fallback), public/login.php, public/register.php.
- **Backfill decision**: light -> ametista; dark/black -> tinta; NULL/unknown -> ametista.
  Column becomes VARCHAR(20) NOT NULL DEFAULT 'ametista'. Non-destructive, idempotent.
- **Esqueci minha senha DECISION**: link removido do login (não existe backend de
  reset; nada de fluxo parcial). Fica para uma fatia própria (token + e-mail).
- **Deploy order**: run `php scripts/migrate.php up` BEFORE/with the code deploy —
  registration now inserts users.theme explicitly.
- **Validation**: local static only (PHP indisponível). Server pending:
  php -l nos arquivos tocados, migrate up, tests/smoke.php, browser pass.
- **Known follow-ups (App slice, intentionally untouched)**: public/profile.php
  ainda mostra o seletor antigo (dark/black/light — agora cai em 'ametista');
  header/dashboard.js ainda usam fallback 'dark'; themes.css não conhece os 15 slugs.

## Previous State
- **Phase**: BALANCE VISIBILITY / PROFILE / BULK DELETE HOTFIX COMPLETE LOCALLY (server PHP/browser validation pending)
- **Confidence**: 82% for this hotfix (pending server PHP lint/smoke and manual browser validation)
- **Working root**: C:\Users\Encryptedx\Desktop\NovixFin
- **Started**: 2026-06-22
- **Build final report**: /reports/final-summary.md
- **Validation final report**: /reports/final_validation.md
- **Recommendation**: SAFE TO DEPLOY (score 90/100)

## Hotfix - Balance Visibility Profile Action + Bulk Delete
- **Report**: /reports/checkpoint_hotfix_balance_visibility_profile_bulk_delete.md
- **Status**: COMPLETE locally; server PHP lint/smoke and manual browser validation still required.
- **Scope**: Regression hotfix only. No roadmap work, no RecurringService changes, no cron behavior, no deployment config, no credentials.
- **Root cause**:
  - Non-dashboard balance toggle relied on a direct one-time listener and was not robust enough for shared-layout usage.
  - Profile balance preference action used an absolute `/public/profile.php` redirect instead of the existing profile redirect helper path.
  - Bulk transaction delete used an older non-transactional repository path, with no action-level failure guard and no recurring-generated cleanup.
- **Fix**:
  - `public/scripts/balance-visibility.js` now delegates clicks, updates all toggle button instances, avoids `Promise.finally()`, and keeps body state aligned after clicks.
  - `actions/profile/update-balance-visibility.php` redirects with `redirect('profile.php')`.
  - Profile/history forms post explicit scoped `redirect_to` values.
  - `actions/transactions/delete_all.php` catches failures and returns to history with a flash.
  - `TransactionRepository::deleteAll()` now runs inside a transaction, cleans `recurring_generated`, reverses effective account balance effects, updates affected bills, and rolls back on failure.
- **Files changed**:
  - `public/scripts/balance-visibility.js`
  - `actions/profile/update-balance-visibility.php`
  - `public/profile.php`
  - `public/history.php`
  - `actions/transactions/delete_all.php`
  - `actions/transactions/delete_by_account.php`
  - `src/Repositories/TransactionRepository.php`
  - `tests/probe_batch2_features.py`
  - `reports/checkpoint_hotfix_balance_visibility_profile_bulk_delete.md`
  - `state/progress.md`
  - `state/context.md`
- **Validation run locally**:
  - `node --check public\scripts\balance-visibility.js` -> passed.
  - `node --check public\scripts\main.js` -> passed.
  - `node --check public\scripts\history.js` -> passed.
  - PHP 7.4 blocker static scan -> no matches.
  - Static PowerShell assertions for this hotfix -> passed.
- **Validation unavailable locally**:
  - `php --version` -> unavailable.
  - `python -m pytest ...` -> unavailable.
  - `py -m pytest ...` -> Python 3.11 exists but pytest is not installed.
  - Scoped git status/diff via the parent worktree is blocked by sandbox permissions/dubious ownership.
- **Remaining validation**:
  - Targeted `php -l` on changed PHP files and `php tests/smoke.php`.
  - Manual browser checks for profile preference persistence, accounts toggle, history bulk delete, and refresh persistence.

## Hotfix - Balance Visibility Global Toggle
- **Report**: /reports/checkpoint_hotfix_balance_visibility_global_toggle.md
- **Status**: COMPLETE locally; server PHP lint/smoke and manual browser validation still required.
- **Scope**: Balance visibility regression only. No roadmap work, no financial logic, no RecurringService, no TransactionRepository, no deployment config, no credentials, no cron behavior.
- **Root cause**:
  - `includes/layout/header.php` used `$_SESSION['user']['hide_balances'] ?? true`, so stale/incomplete sessions could keep hiding balances even after `users.hide_balances` was disabled.
  - The global `balance-visibility.js` masked `[data-balance]` values on all pages, but the eye button existed only on `public/dashboard.php`.
- **Fix**:
  - Shared header now fetches the current user when needed, prefers `$user['hide_balances']` from DB, and syncs it into the session.
  - Shared header renders the balance visibility button on authenticated non-dashboard pages; dashboard keeps its existing button to avoid duplicate IDs.
  - Profile form now posts explicit `hide_balances=0` when the checkbox is unchecked.
  - Footer comment and Batch 2 probe were updated for the shared-toggle behavior.
- **Files changed**:
  - `includes/layout/header.php`
  - `includes/layout/footer.php`
  - `public/profile.php`
  - `tests/probe_batch2_features.py`
  - `reports/checkpoint_hotfix_balance_visibility_global_toggle.md`
  - `state/progress.md`
  - `state/context.md`
- **Validation run locally**:
  - `node --check public\scripts\balance-visibility.js` -> passed.
  - `node --check public\scripts\main.js` -> passed.
  - `node --check public\scripts\dashboard.js` -> passed.
  - `node --check public\scripts\history.js` -> passed.
  - PHP 7.4 blocker static scan -> no matches.
  - Static PowerShell assertions for DB/session sync and global toggle -> passed.
- **Validation unavailable locally**:
  - `php --version` -> unavailable.
  - `python -m pytest` -> unavailable in this sandbox.
  - `py -m pytest tests\probe_batch2_features.py` -> Python 3.11 has no pytest.
  - `py -3.13 -m pytest tests\probe_batch2_features.py` -> blocked by WindowsApps access.
- **Remaining validation**:
  - `php -l includes/layout/header.php`
  - `php -l includes/layout/footer.php`
  - `php -l public/profile.php`
  - `php tests/smoke.php`
  - Manual browser check on dashboard, accounts, history, credit cards, and profile.

## v3.1 P2 - Recurrence by Quantity/Installments
- **Report**: /reports/checkpoint_p2_recurrence_installments.md
- **Hotfix report**: /reports/checkpoint_hotfix_p2_migration_parser_overflow.md
- **Status**: COMPLETE locally; Hetzner PHP validation still required before recurring cron is allowed.
- **Scope**: Additive recurrence installment support only. No paid/received workflow, notes, P3 forecast, credit-card pay/revert changes, or balance semantics changes.
- **Counting rule**:
  - Parent recurring transaction = installment 1/N.
  - Generated children = installments 2/N through N/N.
  - Generation stops when `1 + COUNT(recurring_generated rows for parent) >= recurrence_total_occurrences`.
- **Schema**:
  - New migration `migrations/20240101000006_add_recurrence_installments_v3.php`.
  - Adds nullable `transactions.parent_transaction_id`, `transactions.installment_index`, `transactions.recurrence_total_occurrences`, and `recurring_generated.installment_index`.
  - Adds supporting indexes and backfills child `parent_transaction_id` from `recurring_generated`.
- **Files changed**:
  - `migrations/20240101000006_add_recurrence_installments_v3.php`
  - `src/Repositories/TransactionRepository.php`
  - `src/Services/RecurringService.php`
  - `actions/transactions/create.php`
  - `actions/transactions/update.php`
  - `public/dashboard.php`
  - `public/history.php`
  - `public/scripts/dashboard.js`
  - `public/scripts/history.js`
  - `tests/probe_recurrence_installments.py`
  - `tests/recurrence_installments.php`
  - `reports/checkpoint_p2_recurrence_installments.md`
  - `state/progress.md`
  - `state/context.md`
- **Validation run locally**:
  - Hetzner hotfix persisted the SQL migration parser/comment fix and aligned the recurrence overflow harness with the parent-day anchor path.
  - Post-pause hardening fixed generated-child edit preservation so existing child `installment_index` / `recurrence_total_occurrences` are not cleared by the shared edit path.
  - `python -m pytest tests\probe_recurrence_installments.py` -> 6 passed.
  - `python -m pytest tests\probe_batch2_features.py tests\probe_currency_save.py` -> 10 passed.
  - `python -m pytest tests\probe_batch2_features.py tests\probe_currency_save.py tests\probe_recurrence_installments.py` -> 16 passed.
  - `node --check public\scripts\dashboard.js` -> passed.
  - `node --check public\scripts\history.js` -> passed.
  - `node --check public\scripts\balance-visibility.js` -> passed.
  - `node --check public\scripts\main.js` -> passed.
  - `node --check public\scripts\kanban.js` -> passed.
  - `node --check public\scripts\forecast-chart.js` -> passed.
  - PHP 7.4 blocker static scan -> no matches.
  - Post-pause rerun: requested JS checks passed, Batch 2 probes passed (10), recurrence installment probes passed (6), PHP 7.4 blocker static scan still has no matches, and `php --version` is still unavailable locally.
- **Hetzner hotfix local validation**:
  - `python -m pytest tests\probe_migration_parser.py` -> 2 passed.
  - `python -m pytest tests\probe_recurrence_installments.py` -> 7 passed.
  - `python -m pytest tests\probe_batch2_features.py tests\probe_currency_save.py tests\probe_recurrence_installments.py tests\probe_migration_parser.py` -> 19 passed.
  - `node --check public\scripts\dashboard.js`, `history.js`, `balance-visibility.js`, and `main.js` -> passed.
  - PHP 7.4 blocker static scan -> no matches.
  - `php --version` -> unavailable locally.
- **Remaining validation on Hetzner**:
  - `docker compose exec -w /srv/sites/novixfin php php -l scripts/migrate.php`
  - `docker compose exec -w /srv/sites/novixfin php php -l src/Services/RecurringService.php`
  - `docker compose exec -w /srv/sites/novixfin php php -l tests/recurrence_overflow.php`
  - `docker compose exec -w /srv/sites/novixfin php php -l tests/recurrence_installments.php`
  - `docker compose exec -w /srv/sites/novixfin php php tests/recurrence_overflow.php`
  - `docker compose exec -w /srv/sites/novixfin php php tests/recurrence_installments.php`
  - `docker compose exec -w /srv/sites/novixfin php php tests/smoke.php`
  - Do not run recurring cron until these tests pass.

## Tiny Visual Hotfix - Sidebar Footer Polish
- **Report**: /reports/checkpoint_hotfix_sidebar_footer.md
- **Status**: COMPLETE locally; manual browser visual confirmation still required.
- **Scope**: Visual-only sidebar footer CSS. No roadmap work, no financial logic, no balance visibility, no RecurringService behavior, and no TransactionRepository behavior touched.
- **Files changed**:
  - `public/styles/main.css`
  - `reports/checkpoint_hotfix_sidebar_footer.md`
  - `state/progress.md`
  - `state/context.md`
- **Summary**:
  - `.sidebar-footer` now stretches to the full sidebar width instead of shrinking around footer contents.
  - Username row and logout button now use a fixed nav-like 2.5rem height.
  - Username text is constrained to a single ellipsized line.
  - Logout button border is neutralized in the sidebar footer override, with a subtle rose hover state.
  - Collapsed footer keeps the username hidden, logout icon centered, and tooltip overflow visible.
- **Validation run locally**:
  - `node --check public\scripts\main.js` -> passed.
  - `php -l includes/layout/header.php` -> local PHP unavailable; must run on Hostinger if needed.
- **Remaining validation**: browser visual check in expanded and collapsed sidebar states.

## Urgent Hotfix Follow-up - Smoke + Balance Toggle + Sidebar
- **Report**: /reports/checkpoint_hotfix_smoke_balance_sidebar.md
- **Status**: COMPLETE locally; Hostinger PHP lint/smoke and manual browser validation still required.
- **Scope**: Regression hotfix only. No roadmap work, no financial logic changes, no RecurringService behavior changes, and no TransactionRepository behavior changes.
- **Smoke test fixes**:
  - `tests/smoke.php` now uses `class_exists()` instead of `ReflectionClass::exists()`.
  - `InsightService::forUser()` return type inspection uses `ReflectionNamedType::getName()` instead of string-casting `ReflectionType`.
  - `tests/smoke.php` explicitly requires `includes/throttle.php` before W2 throttle checks.
  - Throttle identifier checks are guarded by `$hasThrottleFunctions` so missing helpers fail the smoke test without fataling immediately.
- **Balance toggle fix**:
  - `public/scripts/balance-visibility.js` now has one `currentHidden` source of truth and one `setHidden(hidden)` path.
  - Initial state is read once from `body[data-hide-balances-default]`.
  - Clicks update UI immediately, persist asynchronously, and do not roll back the UI on AJAX failure.
- **Sidebar fix**:
  - `includes/layout/header.php` adds scoped footer/user/logout hooks.
  - `public/styles/main.css` adds scoped sidebar footer alignment rules for normal and collapsed states.
- **Validation run locally**:
  - `node --check public\scripts\balance-visibility.js` -> passed.
  - `node --check public\scripts\main.js` -> passed.
  - `node --check public\scripts\dashboard.js` -> passed.
  - `node --check public\scripts\kanban.js` -> passed.
  - `node --check public\scripts\forecast-chart.js` -> passed.
  - `python -m pytest tests\probe_batch2_features.py tests\probe_currency_save.py` -> 10 passed.
- **Remaining validation on Hostinger**: targeted `php -l` list and `php -d display_errors=1 -d error_reporting=E_ALL tests/smoke.php`.

## Urgent Hotfix - PHP 7.4 CLI Compatibility + Balance Visibility
- **Report**: /reports/checkpoint_hotfix_php74_balance_visibility.md
- **Status**: COMPLETE locally; Hostinger PHP lint/smoke and browser validation still required.
- **Server fact**: Hostinger CLI reports PHP 7.4.33, so active PHP source must avoid PHP 8-only syntax unless the CLI binary is explicitly changed and verified.
- **PHP compatibility fixes**:
  - Added variables to all active variableless `catch` blocks found by scan.
  - Replaced constructor property promotion with explicit typed properties and assignments.
  - Removed `mixed` type declarations from active source.
  - Replaced `str_contains()` in `tests/smoke.php` with `strpos()` checks.
- **Balance visibility fix**:
  - Replaced permanent CSS pseudo-element masking with JS text replacement using `data-balance-value`.
  - CSS masking now acts only as a pre-JS first-paint fallback until `body.balance-visibility-ready`.
- **Validation run locally**:
  - `node --check public\scripts\balance-visibility.js` -> passed.
  - `node --check public\scripts\dashboard.js` -> passed.
  - `node --check public\scripts\kanban.js` -> passed.
  - `node --check public\scripts\forecast-chart.js` -> passed.
  - `python -m pytest tests\probe_batch2_features.py tests\probe_currency_save.py` -> 7 passed.
- **Remaining validation on Hostinger**: targeted `php -l` list and `php tests/smoke.php`.

## v3.1 P1 Light Theme / UI Polish Checkpoint
- **Report**: /reports/checkpoint_p1_light_theme_polish.md
- **Status**: COMPLETE pending PHP/server smoke and manual browser verification.
- **Summary**: Targeted light-theme contrast fixes were added for dashboard forecast controls, `Próx. semana`, Kanban columns/cards, the empty achievements state, and account pie chart legends. Dashboard recent history now shows up to 8 items instead of 5.
- **Files changed**: `public/styles/themes.css`, `public/scripts/dashboard.js`, `public/scripts/forecast-chart.js`, `public/scripts/kanban.js`, `public/dashboard.php`, plus checkpoint/state files.
- **Validation run locally**:
  - `node --check public\scripts\dashboard.js` -> passed.
  - `node --check public\scripts\forecast-chart.js` -> passed.
  - `node --check public\scripts\kanban.js` -> passed.
  - `python -m pytest tests\probe_batch2_features.py tests\probe_currency_save.py` -> 5 passed.
- **Remaining validation**: `php -l public/dashboard.php`, `php tests/smoke.php`, and manual light-theme UI smoke on a PHP-capable environment.

## v3.1 Batch 2 Checkpoint - Tasks 4-6
- **Report**: /reports/checkpoint_batch2_tasks4_6.md
- **Task 4 - Sidebar polish**: COMPLETE by code inspection. `includes/layout/header.php` and `public/styles/main.css` now align the user row and logout button horizontally with the nav item rhythm; no duplicate shared-layout `<body>` tag was found.
- **Task 5 - Hide/show balances**: COMPLETE pending PHP/server smoke. `users.hide_balances` migration/action/session loading were already present from OpenCode; this pass finished first-paint masking by rendering `body.hide-balances` server-side, removed stale balance `localStorage` override behavior, and expanded `data-balance` coverage across dashboard/history/accounts/credit-cards.
- **Task 6 - Inline category creation**: COMPLETE pending PHP/server smoke. `actions/categories/create-inline.php` was already present; this pass added the dashboard modal, JS submit handler, shared category option registry, select refresh behavior for create/edit transaction forms, and category SQL scope alignment for global default categories (`user_id IS NULL`) plus user-owned categories.
- **New regression probe**: `tests/probe_batch2_features.py` (5 static checks).
- **Validation run locally**:
  - `python -m pytest tests\probe_batch2_features.py` -> 5 passed.
  - `python -m pytest tests\probe_batch2_features.py tests\probe_currency_save.py` -> 5 passed.
  - `node --check public\scripts\dashboard.js` -> passed.
  - `node --check public\scripts\balance-visibility.js` -> passed.
  - `php --version` -> unavailable locally.
- **Remaining validation**: run PHP lint/smoke and manual UI smoke on a PHP-capable environment before production.

## POST-VALIDATION PATCH — Recurring Overflow (W1 RESOLVED)
- **Bug addressed**: monthly/yearly recurrence on day-of-month ≥ 28 inherited PHP `+1 month` overflow (Jan 31 → Mar 3) + day-ratchet-down after first clamp (Feb 28 would propagate 28 forward forever instead of recovering the original 31st).
- **Files changed**:
  - `src/Services/RecurringService.php` — added `advanceAndClamp` helper; rewrote `nextOccurrence` monthly/same_day and yearly branches to use the anchor + same-month-first + clamp; `generateForParent` pre-resolves the parent's day-of-month as the anchor and passes it across iterations so no ratchet-down.
  - `tests/recurrence_overflow.php` — new regression harness (186 lines, 32 assertions + 2 × 12-step monthly chain simulations + 1 × 4-step yearly leap chain simulation). Edge cases documented in §11 of `final_validation.md`.
- **Score impact**: +3 points (functional correctness recovered 2pts, data integrity recovered 1pt). Overall 85 → 88.
- **W1 status**: RESOLVED.

## Validation Phase Findings (NEW)
- Phase A (code audit): https://reports/validation_phaseA.md — 4 critical/medium bugs FOUND + FIXED:
  1. ✅ TransactionRepository::deleteByAccount didn't revert account balances (CRITICAL) → fixed (pre-fetch effective rows + reverse-sum + atomic wrap)
  2. ✅ RecurringService didn't trigger goal recalc after materialising children (MEDIUM) → fixed via post-loop recalculateAllGoals for uniqueness-touched user ids
  3. ✅ auto-effective.php cron didn't trigger goal recalc (MEDIUM) → fixed via tracked-user-list + post-loop recalculateAllGoals
  4. ✅ TransactionRepository::delete not wrapped in transaction (MEDIUM) → wrapped DELETE+balance+bill in TX with rollback on failure
- Phase B (functional simulation): https://reports/validation_phaseB.md — 25+ flows traced statically; all PASS (some with documented caveats)
- Phase C (validation suite): https://reports/validation_phaseC.md — 65 PHP files structurally balanced; 102 __DIR__-includes resolve; 0 broken Novix\ use refs; all form actions resolve; 4 migrations idempotent; smoke test statically traces PASS
- Phase D (final validation): https://reports/final_validation.md — score 85/100; recommendation DEPLOY WITH WARNINGS; deploy checklist documented

## Validation Phase Output
- 4 bug fixes shipped under /reports/validation_phaseA.md
- New state file: /state/active_php_files.txt (65 active PHP files inventoried)
- Modifications made: src/Repositories/TransactionRepository.php (delete + deleteByAccount), src/Services/RecurringService.php (users_touched + post-loop recalc), actions/transactions/auto-effective.php (touch list + post-loop recalc), scripts/cron.php (require goals.php)

## Build Phase Status (preserved from build cycle)
- [x] Phase 0 — Security hardening & dead-code removal   (checkpoint_phase0.md)
- [x] Phase 1 — Real config & env                         (checkpoint_phase1.md)
- [x] Phase 2 — Schema consolidation + migration runner    (checkpoint_phase2.md)
- [~] Phase 3 — Repository layer + DB transactions + bugs (PARTIAL — Batch C deferred)
- [~] Phase 4 — Decompose dashboard.php                  (PARTIAL — insights extracted)
- [x] Phase 5 — Recurring transaction generation          (checkpoint_phase5.md)
- [~] Phase 6 — Gamification implementation               (PARTIAL — tx + bill wired; goal_completed defined-not-wired)
- [x] Phase 7 — Export / Import tooling                    (checkpoint_phase7.md)
- [x] Phase 8 — AI insights service                        (checkpoint_phase4_8.md)
- [~] Phase 9 — Tailwind/assets cleanup                   (PARTIAL — suppression hack removed)
- [x] Phase 10 — Tests & docs (cold smoke + TESTING.md + ROADMAP)

## Phase Status
- [x] Phase 0 — Security hardening & dead-code removal   (checkpoint_phase0.md)
- [x] Phase 1 — Real config & env                         (checkpoint_phase1.md)
- [x] Phase 2 — Schema consolidation + migration runner    (checkpoint_phase2.md)
- [~] Phase 3 — Repository layer + DB transactions + bugs (PARTIAL — Batch C deferred; Batches A,B,D,E,F complete. Reports: phase3a/3b/3de/3f)
- [~] Phase 4 — Decompose dashboard.php                  (PARTIAL — insights extracted; full decomposition deferred until PHPUnit cycle.)
- [x] Phase 5 — Recurring transaction generation          (checkpoint_phase5.md)
- [~] Phase 6 — Gamification implementation               (PARTIAL — engine + tx/bill wired; goal_completed defined-not-wired.)
- [x] Phase 7 — Export / Import tooling                    (checkpoint_phase7.md)
- [x] Phase 8 — AI insights service                        (checkpoint_phase4_8.md)
- [~] Phase 9 — Tailwind/assets cleanup                   (PARTIAL — suppression hack removed; CDN kept.)
- [x] Phase 10 — Tests & docs (cold smoke test + TESTING.md + ROADMAP refreshed + final-summary)

## Deliberate Deferrals (per "blocked by dependency" / "deferred for safety")
1. **Phase 3 Batch C** — collapse `SHOW COLUMNS`/`SHOW TABLES` probes. Blocked by deploy-contract (migrations must be applied first). Probes are harmless per-request lookups; collapse once prod-verified.
2. **Phase 4 full decomposition** of `public/dashboard.php` monolith. High-risk without PHPUnit safety net. Insights already extracted.
3. **Phase 6 goal_completed hook** — defined in `EVENT_POINTS` (GamificationService) but not yet called from `actions/goals/save.php`. Phase-10-plus cycle.
4. **Phase 9 Tailwind bundle** — CDN kept (would need `npx tailwindcss-cli` + Node bootstrapping). The `console.warn` suppression hack is removed so the warning surfaces honestly.
5. **Schema tightenings**: `DROP goals.name`, `categories.user_id NOT NULL`, FKs for `goals.account_id` — parked as later idempotent migrations needing a data audit first.

## Hard requirements the user MUST do before deploying v3 to prod
1. ROTATE the leaked DB password (`@#$Novix200505`) on the Hostinger MySQL panel and update the new `.env` on the server.
2. Create `.env` on the server following `docs/SETUP.md`.
3. **Run the migrations before exposing v3 code**: `php scripts/migrate.php status` → `php scripts/migrate.php up`. (Action handlers no longer perform runtime `CREATE TABLE IF NOT EXISTS`; missing tables would fatal.)
4. Add Hostinger cron: `0 2 * * * /usr/bin/php <raiz>/scripts/cron.php all >> <raiz>/logs/cron.log 2>&1`.
5. Run cold smoke test on the server (`php tests/smoke.php`) — must exit 0.

## Files added/modified count
- 17 new files; ~25 files modified; 7 dangerous files + 3 snapshot folders + 3 destructive SQL archived under /archive/phase0/.

## Blockers
- none remaining.

## Next Actions
- Run-down is complete. See /reports/final-summary.md for the closing summary and `docs/ROADMAP.md` for remaining short-term roadmap items.

## POST-VALIDATION PATCH — Login/Register Throttle Cookie-Clear Bypass (W2 RESOLVED)
- **Bug addressed**: pre-v3 login throttle stored its counter in `$_SESSION`, identified by the session cookie — clearing cookies reset it to zero. `register.php` had NO throttle at all.
- **Assessment**: MEDIUM-HIGH. Argon2id slows each guess but doesn't cap rate; credential-stuffing window was effectively unmitigated.
- **Recommendation (acted on)**: PATCH BEFORE DEPLOY.
- **Implementation (DB-backed, no external deps)**:
  - New migration `20240101000004_add_login_attempts_v3.sql` — `login_attempts` table (id INT, identifier CHAR(64), ip VARCHAR(45), attempted_at TIMESTAMP) with indexes `(identifier, attempted_at)`, `(ip, attempted_at)`, `(attempted_at)`.
  - New helper `includes/throttle.php` — 5 pure functions: `throttle_identifier(email, ip)`, `throttle_identifier_for_register(ip)`, `is_throttled(identifier, max=5, windowSec=300)`, `record_failed_attempt(identifier, ip)`, `clear_failed_attempts(identifier)`. Fail-open on DB error with error_log (pre-migration deploy doesn't brick all logins).
  - `includes/bootstrap.php` now `require_once throttle.php` so every entry point has it.
  - `public/login.php` rewrote the `$_SESSION`-based throttle block to use the DB-backed one (5 attempts per email+IP per 5min; counter extends while blocked).
  - `public/register.php` (was throttles) now wrapped: 10 / 10min IP-only, with email-enumeration attempts counted.
  - `tests/smoke.php` extended with 10 new assertions (callable, determinism, sha256 hex shape, IP-keyed, login≠register identifier).
- **Files changed**: 6 (1 new migration, 1 new helper, 1 bootstrap edit, 2 form edits, 1 test edit).
- **Score impact**: security posture recovered to full marks (+2 pts). Overall 88 → 90/100.
- **Recommendation upgraded**: DEPLOY WITH WARNINGS → **SAFE TO DEPLOY**.
- **W2 status: RESOLVED.**
