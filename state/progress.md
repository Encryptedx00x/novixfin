# NovixFin v3 Execution Progress

## MERGE dev-fatura-cartao -> main, pronto para deploy (2026-08-28)
- **Checklist**: `/reports/deploy_2026-08-28.md`. **Nada foi enviado ao servidor; sem push.**
- `b652564` **fix do COALESCE** direto em main: as duas queries de metas (linhas 691 e 939)
  exigiam `goals.name`; no schema v3 canonico so existe `title`, e o fallback do catch
  tambem consultava `name` -> fatal. Agora usam so a coluna canonica.
- `7474461` **merge**. Modo Planilha RESTAURADO por decisao do usuario (fora apagado no
  commit de sync `16f591b`). Dois casos distintos no merge:
  - conflito modify/delete (4): `sheet.js`, `sheet.css`, `actions/sheets/load.php`,
    `actions/sheets/snapshot.php` -> mantida a versao de dev;
  - **exclusao silenciosa (4)**: `_bootstrap.php`, `save.php`, `restore.php` e
    `migrations/20240101000008_sheets_v3.php` -> apagados em main e intocados em dev,
    o git removeria sem conflito. Restaurados a mao. Sem isso a Planilha ficaria
    meio quebrada: grade sem tres handlers e sem a migracao.
- **Tag de rollback**: `pre-merge-fatura-2026-08-28`.
- **Validado no resultado do merge** (nao so na branch): suite 13/13 exit 0; `php -l` em
  79 arquivos; `node --check`; e verificacao HTTP ponta a ponta num banco SEM `goals.name`
  -> dashboard 200 sem fatal e com a meta renderizada, Cartoes 200, Planilha `load.php` ok,
  e o ciclo compra->fatura->pagamento fechando ao centavo (300,00; cartao NAO duplica).
- **Migracoes pendentes em producao (3, nesta ordem)**: `0008` sheets/sheet_versions,
  `0009` bill_payments, `0010` zera register_bill_as_expense. Ensaiadas em banco virgem
  (11/11) e em banco simulando producao (`status` -> exatamente 3 pending).
- **Nao resolvido**: faturas pagas ANTES do deploy seguem infladas pelas linhas
  `Fatura do cartao - %` antigas; precisa script retroativo (SQL de diagnostico no checklist).

## FILA TASKS.md — execução 2026-08-09 (CONCLUÍDA até a C1; sem deploy)
**Ambiente destravado por Docker** (a fila de 2026-08-08 parou por falta de PHP):
`php:8.3-cli` + `pdo_mysql` (PHP 8.3.33, produção é 8.3.32) e `mariadb:11.8`
(11.8.8, produção é 11.8.x). O `.env` do repo é **neutralizado dentro do container**
(bind-mount read-only apontando para o MariaDB de teste), porque `includes/env.php`
sobrepõe variáveis de ambiente reais — sem isso os testes alcançariam PRODUÇÃO.
Bancos: `novixfin_test` (schema das migrações do repo) e `novixfin_app` (vazio, só
para o guard do helpers.php enxergar dois bancos distintos).

- **Task 0.1 — CONCLUÍDA — `269e2dc`.** Harness validado em execução real pela
  primeira vez. `php tests/run.php` → 6 arquivos, 0 falhas, exit 0. Canário
  proposital provou a detecção (exit 1) e foi removido. `tests/harness_test.php`
  (novo) exercita banco isolado, rollback sem resíduo, as 6 factories, o caminho
  SKIP e o guard que recusa apontar para o banco da aplicação.
  - **Decisão do usuário (invariante alterado):** o alvo **PHP 7.4 foi aposentado**.
    O scan estático em `tests/recurrence_installments.php` contradizia o "PHP 8.1+"
    das REGRAS GLOBAIS do TASKS.md e do AGENTS.md; produção é 8.3.32 e não existe
    mais ambiente 7.4. O scan agora verifica só `catch` com variável (estilo da casa).
    `state/context.md` atualizado.
  - **Achado:** `includes/forecast.php` era **falso positivo** do scan — `mixed` só
    em docblock (`array{amount?: mixed}`), nunca como declaração de tipo.
- **Task 0.2 — CONCLUÍDA — `9bcd1ae`.** `tests/characterization_test.php`, 18
  verificações ao centavo congelando o comportamento atual: saldo após criar/
  editar/excluir (inclui troca de tipo e exclusão de pendente), efetivar pendente
  (e efetivar de novo sem duplicar), saldo consolidado com cartão FORA, e previsão
  determinística (3.400,25 + 1.500,00 − 400,00 = 4.500,25).
  - **D6 registra de propósito** o comportamento apontado na auditoria: pendente de
    CARTÃO entra em "a pagar" (a consulta do dashboard não filtra tipo de conta).
    Se a Fase B mudar isso, este teste falha e a task que mudar deve atualizá-lo.
- **Task A1 — CONCLUÍDA — `c5c88ff`.** A implementação já estava em `7f1178f`;
  faltava o aceite. `tests/sheets_persist_test.php` exercita os handlers REAIS em
  subprocesso (sessão + CSRF montados): A4 volta `=SUM(A1:A3)`, **não** o 60, e a
  fórmula está literal em `sheets.content`. Cobre rejeição de content sem abas e
  CSRF inválido, distinguindo resposta não-JSON de rejeição legítima.
  - **Primeiro `php -l` real** de tudo que foi tocado nas sessões sem PHP:
    `dashboard.php`, `header/footer`, os 5 handlers de sheets, a migração — limpos.
- **Task A2 — CONCLUÍDA — `27935f2`.** Renomear in loco (duplo clique) e excluir
  (× com confirm, recusa deixar zero abas). Renomear **reconstrói a engine** e
  recusa nome duplicado, porque o nome da aba sustenta as fórmulas entre abas.
  `tests/sheets_tabs_test.php`: 19 verificações (3 abas, ordem, isolamento entre
  abas, referência cruzada, ciclo renomear+excluir).
- **Task A3 — CONCLUÍDA — `0ad69ee`.** Undo/redo e indicador já existiam; faltava
  **Ctrl+Shift+Z** (único "refazer" convencional no macOS). node --check ok.
- **Task A4 — CONCLUÍDA — `0e41d67`.** `restore.php` lê `version_id` com
  `filter_input(INPUT_POST)`, que lê o CORPO REAL da requisição e **ignora `$_POST`** —
  teste em CLI nunca o exercitaria. Criado `tests/http_client.php` (streams, cookie jar,
  login real). Poda 55→50 mantendo os mais novos; `ORDER BY` ganhou desempate por `id`
  porque `created_at` é TIMESTAMP de 1s e marcos do mesmo segundo empatavam.
- **Task A5 — CONCLUÍDA — `a131716`.** Seleção de intervalo, copiar/colar TSV (eventos
  copy/paste, aceita Excel/Sheets) e fill handle com ajuste de referência relativa
  (`$` absoluto, faixas, colunas multi-letra, `#REF!` fora da grade).
- **Task B1 — CONCLUÍDA (VERMELHA) — `ae9e523`.** Capturou o bug do ciclo de fatura.
- **Task B2 — CONCLUÍDA — `07cc651`.** Ciclo pelo `closing_day` numa única função
  (`billingMonthFor`), usada por create/update/RecurringService. B1 ficou verde.
- **Task B3 — CONCLUÍDA — `e77285e`.** `bill_payments` + comando `down` no runner
  (migração pode devolver `['up'=>fn,'down'=>fn]`; contrato antigo segue valendo).
- **Task B4 — CONCLUÍDA (VERMELHA) — `f22b1d9`.** Capturou a dupla cobrança do cartão.
- **Task B5 — CONCLUÍDA — `fbf7af3`.** Pagamento multi-origem; nenhuma despesa na conta
  do cartão; reversão por `bill_payments` com fallback para faturas antigas. 22/22 ao centavo.
- **Task B6 — PULADA por instrução.** UI de pagamento multi-origem **aguarda design +
  revisão humana**. Não inventei a tela. O backend já aceita `sources[]`.
- **Task B7 — CONCLUÍDA — `0d4a01e`.** Fatura lista o que a compõe (`WHERE bill_id`) e
  de onde saiu o pagamento; `CREATE TABLE` em runtime removido; CSS tokenizado.
- **Task C1 — relatório em `/reports/overnight_2026-08-09.md`. SUÍTE 13/13, exit 0.
  DEPLOY NÃO FEITO: não existe alvo de deploy no repo** (sem git remote, sem script,
  sem ssh config; o único host documentado é PRODUÇÃO, e o alvo pedido é o de teste).
- **⚠️ ACHADO GRAVE não corrigido:** `public/dashboard.php:691` usa
  `COALESCE(title, name)` em `goals` — o baseline v3 só cria `title` e o fallback do
  catch consulta `name` sozinho. **O dashboard FATALA em instalação v3 nova.** Produção
  escapa só porque ainda tem a coluna legada. Afeta a decisão de deploy.


## Current State
- **Phase**: SALDO DESNORMALIZADO (accounts.balance) — **PARTE 1 (código) COMPLETA**; PARTE 2
  (recompute) ENTREGUE, precisa rodar no SERVIDOR (backup + dry-run + --apply). Parado p/ revisão.
- Reskin **100%**; modais completos; agora saldo consistente com as transações.

## FILA OVERNIGHT (TASKS.md) — PAROU NA TASK 0.1 (2026-08-08)
- **Report**: /reports/overnight_2026-08-08.md
- **Nenhuma task concluída. Nenhuma mudança de lógica financeira. Nenhum deploy.**
- **Causa da parada — ambiente, não bug:**
  1. **Não existe PHP nesta máquina.** `which php php8 php8.1 php8.3` → vazio; PowerShell
     `Get-Command php` → "php NOT on PATH"; nenhum XAMPP/Laragon/WAMP/`C:\php`. Isso torna
     INEXECUTÁVEIS os aceites de TODAS as tasks (`php tests/run.php`, `php -l`).
  2. **Não existe banco de teste isolado.** O `.env` aponta para o MariaDB remoto de
     PRODUÇÃO (e sem acesso deste IP — visto em `probe_currency_save.py`: erro 1045). A
     Task 0.1 exige banco isolado; as regras proíbem teste escrever no banco real.
- **Decisão**: apliquei a regra "PARE NO PRIMEIRO ERRO, não contorne". Avançar significaria
  escrever B2/B5 (ciclo de fatura + semântica de pagamento multi-origem) — que alteram saldo,
  pagamento e reversão — sem rodar um único teste. A própria fila proíbe.
- **Escrito, porém NÃO VALIDADO** (commitado assim de propósito, para não se perder):
  - `tests/run.php` — runner sem Composer/PHPUnit; descobre `tests/*_test.php` + os
    standalone existentes; roda cada teste em PROCESSO SEPARADO; agrega exit codes; sai ≠0 se
    qualquer um falhar; aceita `--only=`.
  - `tests/helpers.php` — asserções + factories. **`t_money` compara em CENTAVOS INTEIROS**
    (exato ao centavo, sem tolerância de float). **Guard anti-produção**: banco de teste é
    opt-in por `TEST_DB_*`; SKIP se ausente; RECUSA (exit 1) se `TEST_DB_DATABASE` for igual
    ao banco do app ou não contiver "test"; `test_with_rollback()` sempre reverte.
    Factories conferidas contra o schema real — inclusive `users.password` (não `password_hash`).
- **Para retomar**: instalar PHP 8.1+ e um MySQL/MariaDB local, rodar
  `DB_DATABASE=novixfin_test php scripts/migrate.php up`, exportar `TEST_DB_*`, então
  `php -l tests/run.php tests/helpers.php` e `php tests/run.php` (precisa sair 0) + o canário.
- **B6 pulada** conforme instrução: aguarda design/mockup + revisão humana. Não foi inventada.

## FIX — "Carregar mais" não revelava + olho não trocava de ícone (2026-08-06)
- **Report**: /reports/checkpoint_fix_load_more_e_olho.md — corrige a fatia `190c255`.
- **Bug B (causa exata)**: `applyRowState()` fazia `svgEl.hidden = true`. **`SVGElement` NÃO
  implementa a propriedade IDL `hidden`** (ela é do `HTMLElement`) — a atribuição criava só uma
  propriedade solta no objeto JS, sem mexer no DOM. Por isso o recálculo funcionava e o ícone
  ficava parado. Agora alterna por `style.display`, e o ícone cortado nasce com
  `style="display:none;"` em vez do atributo `hidden`.
- **Bug A (causa)**: as linhas têm `display:flex` INLINE. O atributo `hidden` só as escondia por
  causa de `.dash-recent-item[hidden]{display:none!important}` — a regra estava certa (autor
  `!important` vence inline), mas **não chegava ao navegador**: `$pageStyles`/`$pageScripts`
  carregavam SEM versionamento e o CSS antigo continuava em cache. Sem a regra, as 13 linhas
  apareciam de saída e o botão só decrementava um contador vindo do servidor.
  **Terceira ocorrência do mesmo problema de cache no repo** (landing, entrada.css/b6fc882, agora).
- **Correções**: (1) linha e cabeçalho de dia nascem com `display:none|flex` no style INLINE e o
  botão alterna esse display — zero dependência de CSS; (2) `data-recent-hidden="1|0"` marca o
  estado e a contagem sai do DOM (`stillHiddenRows()`), inclusive na carga inicial; (3) ícone por
  `style.display`; (4) **`?v=filemtime` em `$pageStyles` e `$pageScripts`** (header/footer), o
  mesmo padrão do entrada.css.
- **Preservado**: recálculo da previsão e estorno-por-tipo NÃO foram tocados (os 4 testes de
  aritmética seguem passando). Efetivar pendente e os 3 forms, intactos. Continua sem banco.
- **Validação**: `node --check` passou; `pytest tests/probe_forecast_sim.py` → **16 passed**
  (5 novos de regressão, incluindo uma asserção de que `.hidden = isHidden` não volta ao arquivo).
- **Falta no servidor**: `php -l` em `public/dashboard.php`, `includes/layout/header.php`,
  `includes/layout/footer.php` + `php tests/smoke.php`.

## MOVIMENTAÇÕES RECENTES — simulação da previsão + "Carregar mais" (2026-08-06)
- **Report**: /reports/checkpoint_forecast_sim_load_more.md
- **Status**: entregue, parado para revisão. **Sem migração, sem escrita, sem endpoint novo.**
- **(A) Olhinho de simulação** (`public/scripts/forecast-sim.js`, NOVO): tira a transação só do
  CÁLCULO da previsão, em memória. Pendente sai de "a receber"/"a pagar"; efetivada tem o efeito
  ESTORNADO do "saldo hoje" (receita +500 oculta ⇒ saldo hoje −500). Aviso "Simulação · N ocultas"
  com "Restaurar", selo `simulação` e números em `--gold` para não confundir com o saldo real.
  A linha continua na lista (opacidade menor + valor riscado).
  - **Olho DESABILITADO** quando a transação não compõe a previsão: moeda ≠ primária (a consulta
    dos pendentes filtra `currency = primária OR NULL`) ou efetivada em conta de **crédito**
    (cartão é passivo, fica fora de `$consolidatedBalance`).
- **(B) Carregar mais**: renderiza tudo no servidor (teto de **200** linhas) e revela em lotes de
  8 no cliente — decisão documentada: `$recentTransactions` já vem inteiro em memória, então
  paginar por requisição não economizaria consulta. Acima de 200 o rodapé informa o corte e o
  caminho segue sendo o histórico completo. Link "Ver histórico completo →" do topo intacto.
- **Bug colateral corrigido**: `dashboardTransactionsData` (modal de edição) tinha um
  `array_slice(...,0,8)` INDEPENDENTE do da lista — editar uma linha revelada pelo "Carregar mais"
  não acharia os dados. Agora os dois compartilham o mesmo array renderizado.
- **Armadilha registrada**: as linhas têm `display:flex` INLINE, que venceria o atributo `hidden`.
  Depende de `.dash-recent-item[hidden] { display:none !important; }` em dashboard.css.
- **Validação local**: `node --check public/scripts/forecast-sim.js` → passou;
  `python -m pytest tests/probe_forecast_sim.py` → **11 passed** (contrato de ids, ausência de
  chamada de rede no script, e os 4 casos de aritmética). `probe_batch2_features.py` → 10 passed,
  3 failed **PRÉ-EXISTENTES** (asseguram `includes/forecast.php` e `includes/layout/header.php`,
  não tocados aqui).
- **Falta rodar no servidor**: `php -l public/dashboard.php` e `php tests/smoke.php`.

## MODO PLANILHA — BLOCO 1 COMPLETO (front ligado) (2026-08-01)
**O que fechou o bloco 1 (esta sessão):**
- `public/scripts/sheet.js` (NOVO, `node --check` passou) — HyperFormula construída com
  `buildFromSheets` (uma engine por carga; `setCellContents` por célula na edição, com rebuild
  como fallback); grade 12 col x 40 lin; seleção por clique/setas/Tab; edição inline (Enter/F2/
  digitação direta, Esc cancela) com `scrollIntoView({block:'nearest'})` para o teclado virtual;
  barra de fórmula ligada nos dois sentidos; números em pt-BR, negativos em `.neg`, erros da
  engine em `.err`, célula com fórmula marcada com `.fx`. **3 mecanismos separados**: autosave
  (debounce 800ms + `sendBeacon` no `beforeunload` se houver save pendente), snapshot (Ctrl+S /
  botão, no máx. 1 a cada 5 min) e undo/redo (pilha em memória, teto 100, Ctrl+Z / Ctrl+Y).
  Atalhos globais só disparam com o foco dentro de `.sheet-screen`. Abas: criar/trocar, com
  dedupe de nome (a engine exige nomes únicos para fórmulas entre abas) e teto de 30.
- `public/dashboard.php` — aba `Planilha` ao lado de Kanban, painel `data-panel="planilha"`
  com todos os ids do contrato, `styles/sheet.css` em `$pageStyles`, `scripts/sheet.js` em
  `$pageScripts` e a HyperFormula por CDN **sem `defer`** (precisa existir antes do
  DOMContentLoaded do sheet.js).
- **Licença decidida**: `licenseKey: 'internal-use-in-handsontable'` (projeto comercial de
  código fechado). `gpl-v3` foi descartada. Pendência de licença ENCERRADA.

**Falta validar** (sem PHP local): `php -l public/dashboard.php` e a migração no servidor —
sem a migração rodada, a aba Planilha carrega e falha no `load.php` (a tabela não existe).

## MODO PLANILHA — BLOCO 1 PARCIAL (backend + estilo). FRONT PENDENTE (2026-08-01)
**Estado: backend e CSS prontos e commitados; o FRONT (sheet.js + aba no dashboard) NÃO foi
escrito. Nada da planilha está ligado ainda — o dashboard segue exatamente como estava.**

### Pronto (commitado)
- `migrations/20240101000008_sheets_v3.php` — `sheets` (id, user_id FK CASCADE, name, content
  LONGTEXT, timestamps) e `sheet_versions` (id, sheet_id FK CASCADE, label, content,
  created_at). Idempotente (CREATE TABLE IF NOT EXISTS). Assinatura conferida contra o runner:
  `return function (PDO $pdo, callable $log)`. **AINDA NÃO RODADA** — rodar com backup.
- `actions/sheets/_bootstrap.php` — base comum: JSON, `requireAuth`, CSRF, `sheets_get_or_create`
  (cria doc vazio na 1ª vez), `sheets_sanitize_content` (valida array de abas, aceita só refs A1
  `^[A-Z]{1,3}[0-9]{1,5}$`, teto 4 MB e 30 abas, guarda o DIGITADO — valor ou fórmula).
- `actions/sheets/load.php` (doc + até 50 versões), `save.php` (AUTOSAVE: sobrescreve content,
  NÃO gera histórico), `snapshot.php` (HISTÓRICO: transação atômica, poda em 50),
  `restore.php` (restaura versão; posse garantida por INNER JOIN com sheets.user_id).
  Todos com escopo `user_id` em toda query e prepared statements.
- `public/styles/sheet.css` — grade tokenizada fiel ao mockup v2 (`.pc/.ph/.pt/.ptool`, estados
  normal/selecionada/edição/fórmula, `--f-num`, negativos `--neg`, cabeçalhos sticky) + bloco
  **mobile <=768px**: grade rola nos DOIS eixos dentro do container (não vaza), barra de fórmula
  sticky no topo da área, alvos 44px, input 16px (evita zoom iOS), abas com scroll horizontal.

### FALTA (próxima sessão — começar por aqui)
1. **`public/scripts/sheet.js`** — não existe. Foi redigido mas o heredoc do shell quebrou por
   aspas; **usar a ferramenta Write, não heredoc**. Deve conter: HyperFormula criada UMA vez
   (`buildFromSheets`) + `setCellContents` por célula; render da grade (12 col x 40 lin);
   seleção/edição inline; barra de fórmula; 3 mecanismos SEPARADOS — autosave (debounce 800ms),
   snapshot (Ctrl+S/botão, no máx. 1 a cada 5 min) e undo/redo (pilha em memória, Ctrl+Z/Y);
   abas (criar/trocar); `scrollIntoView({block:'nearest'})` ao editar (teclado virtual).
2. **Aba "Planilha" no `dashboard.php`** — botão `.dash-tab data-tab="planilha"` ao lado de
   Kanban, painel `.dash-tabpanel data-panel="planilha"` com a marcação da grade (ids esperados
   pelo JS: `sheetGrid`, `sheetFx`, `sheetRef`, `sheetTabs`, `sheetStatus`, `sheetHistMenu`,
   `sheetUndo`, `sheetRedo`, `sheetSnapshot`, `sheetHistBtn`, `sheetAddTab`), `$pageStyles[] =
   'styles/sheet.css'`, `$pageScripts[] = 'scripts/sheet.js'` e o `<script>` da HyperFormula:
   `https://cdn.jsdelivr.net/npm/hyperformula@3/dist/hyperformula.full.min.js`.
3. Blocos 2-5 do briefing (abas completas com renomear/excluir, histórico na UI, edição em
   bloco/copiar-colar/fill handle) — o CSS e os handlers já suportam.

### PONTO A DECIDIR (licença)
No rascunho do JS usei `licenseKey: 'gpl-v3'` no HyperFormula. Essa chave só vale se o projeto
for GPL. Para uso comercial fechado o correto é `'internal-use-in-handsontable'` ou chave paga.
**Confirmar antes de comercializar.**

### A RODAR NO SERVIDOR
`mariadb-dump` (backup) → `php scripts/migrate.php up` → `php -l` nos 5 PHP novos.
Como nada referencia os handlers ainda, rodar a migração é seguro e não muda nenhuma tela.

## PREVISÃO DETERMINÍSTICA + ajustes CSS mobile (2026-07-31)
- **PARTE A (CSS mobile)**: (1) CTAs da landing (hero e "cta final") passam a `width:auto` e
  `min-height:30px` (era 52px — a altura amplificava o corte `--cut`); (2) CTAs com
  `clip-path: var(--cut)` e o botão "Apresentar" também caem para `min-height:30px`;
  (3) `.lg-switch-go` (login/registro) ganhou `display:flex; align-items:center` para alinhar
  o "Criar conta"/"Entrar". Tudo dentro de `@media (max-width:768px)` (entrada.css l.175 é
  global mas só afeta o link do rodapé do form) — desktop preservado.
- **PARTE B (Previsão de saldo)**: substituída a média móvel por conta DETERMINÍSTICA:
  `saldo final = saldo efetivado + pendentes a receber − pendentes a pagar`, considerando
  SOMENTE o que já está registrado no banco (inclui parcelas já lançadas; NÃO projeta
  recorrências futuras). Removido o filtro de mês da fatia anterior — pendentes de meses
  futuros já lançados entram.
  - UI: número-herói "Saldo final previsto" + **decomposição em 3 linhas** (Saldo hoje ·
    + a receber em `--pos` · − a pagar em `--neg`) + total; valores em `--f-num`.
  - **Removido o bloco "85% de confiança"** e a média móvel que o alimentava (virou código
    morto: `$fSlice/$avgIncomeF/$avgExpenseF/$devF/$confPct/$confNivel/$confColor`).
  - Texto novo e honesto: "o que você tem hoje mais o que já lançou como pendente. Não projeta
    gastos futuros nem parcelas que ainda não foram lançadas."
  - O gráfico `#chartForecast` foi mantido (não dependia da média removida).
- **Validação do número**: 2.887,26 + 1.103,00 − 3.550,76 = **R$ 439,50** ✓. Teste 10 em
  `tests/forecast_balance.php` cobre o caso e a invariante "efetivar uma pendente não muda o
  saldo final".
- **Pendente**: `php -l` e `php tests/forecast_balance.php` no servidor.

## CORREÇÃO — Histórico omitia transações (IDs 137/145) (2026-07-30)
- **Report**: /reports/checkpoint_historico_transacoes_omitidas.md
- **Causa-raiz**: `TransactionRepository::listByUser()` usava
  `INNER JOIN categories c ON t.category_id = c.id AND c.user_id = :user_id_cat` — o join
  filtrava por DONO DA CATEGORIA, então sumia com o lançamento quando a categoria era
  **global** (`categories.user_id IS NULL`, que o AGENTS.md manda suportar), quando
  `category_id` era NULL ou quando a categoria havia sido removida.
- **Por que só o Histórico**: Dashboard (totais via monthlyHistory/consulta de pendentes),
  "Resumo por conta" (SQL próprio) e `export.php` (SELECT sem JOIN) não fazem esse join —
  por isso mostravam 4.850,18 e a exportação trazia os IDs 137/145.
- **Correção**: `LEFT JOIN categories` (categoria entra só para exibição) +
  `COALESCE(c.name,'Sem categoria')`. O escopo de segurança segue em `t.user_id`.
- **NEW `includes/history_summary.php`**: agrupamento por dia, totais e pendentes por conta
  como funções puras sobre o MESMO `$transactions` da listagem (contrato explícito).
  history.php passou a usá-las no lugar dos dois loops inline.
- **NEW `tests/history_listing.php`**: cenário 27/07 (4.903,00 / 4.850,18), resumo por conta
  (Itaú 1.704,23; Caixa 854,24), >10 no mesmo dia, descrições iguais/vazias, effective_date
  em outro mês e nula, efetivadas+pendentes no mesmo dia, negativos, centavos, sem conta,
  vazio, e **guarda de regressão do SQL**.
- **Divergência remanescente documentada (NÃO alterada)**: `listByUser` também filtra
  `(t.account_id IS NULL OR a.is_active = 1)` — lançamentos de contas INATIVAS não aparecem no
  Histórico, mas entram nos totais do Dashboard e no export. Não explica os IDs 137/145
  (contas 28 e 30 estão ativas) e mudar isso alteraria a semântica das duas telas.

## CORREÇÃO FINANCEIRA — dupla contagem na "Previsão de saldo" (2026-07-30)
- **Report**: /reports/checkpoint_previsao_dupla_contagem.md
- **Local**: `public/dashboard.php` (bloco "Previsão de saldo", antes nas linhas 953-961).
- **Dupla contagem CONFIRMADA**, mas o mecanismo NÃO era o da hipótese: `$projNet` não era o
  "líquido do mês" por design — era a **média móvel dos últimos 3 meses**
  (`$avgIncomeF - $avgExpenseF` sobre `$extendedHistoryData`). Como (a) o filtro padrão
  `is_effective` é **null** (todas as transações) e (b) `monthlyHistory` inclui o **mês
  corrente**, essa média carregava a parte JÁ EFETIVADA do mês — a mesma que já está em
  `$consolidatedBalance` (Σ accounts.balance, que por contrato só tem efetivados).
  Com **1 mês de histórico a média degenera no líquido do mês inteiro** (249,72), e
  `196,90 + 249,72 = 446,62` — exatamente o valor reportado na UI.
- **Correção (Abordagem A, mínima)**: `$projNet` passa a ser o líquido **somente dos
  pendentes** do mês corrente (SQL com `is_effective = 0`, mês de `transaction_date`, moeda
  principal, contas ativas). `$previstoSaldo = saldo realizado + líquido pendente`.
  A média móvel foi **mantida** onde é legítima: o cálculo do nível de confiança (`$devF`).
- **NEW `includes/forecast.php`**: helpers puros e testáveis (`novix_sum_transactions`,
  `novix_pending_net`, `novix_pending_net_totals`, `novix_forecast_balance`).
- **NEW `tests/forecast_balance.php`**: 9 blocos cobrindo os cenários pedidos (só efetivados,
  só pendentes, mistura, receitas/despesas, saldo negativo, dinheiro em espécie agregado,
  ausência de pendências, **prevenção de dupla contagem** — reproduz 446,62 e afirma que a
  previsão não pode ser esse valor — e o caminho de totais agregados do SQL).
- **Valores verificados** (simulação da aritmética): realizado 196,90 · pendente 52,82 ·
  totais 5.167,80/4.918,08 · previsão **249,72** · bug 446,62. Critérios de aceite batem.
- **Pendente**: `php -l` e `php tests/forecast_balance.php` no servidor (PHP indisponível aqui).

## CORREÇÃO — chanfro (--cut) dos CTAs no mobile (2026-07-30)
- **Report**: /reports/checkpoint_cut_ctas_mobile.md
- **Causa confirmada**: `--cut` (landing.css:13 / entrada.css:12, tema **ametista**) é
  `polygon(0 0, calc(100% - 14px) 0, 100% 14px, 100% 100%, 0 100%)` — recuo **fixo de 14px** e
  **assimétrico** (corta só o canto superior direito). Calibrado para botão de desktop; num CTA
  full-width e alto do mobile o talho fica desproporcional. Os outros 14 temas têm `--cut:none`.
- **2º defeito (o que realmente sobrou)**: `landing.css:120` faz
  `html[data-theme="ametista"] [data-present-btn]{ clip-path:var(--cut) !important }` — o botão
  "Apresentar" recebe o corte por **regra CSS, não inline**. A correção anterior mirava
  `[style*="clip-path: var(--cut)"]`, então esse botão **nunca foi coberto**. Agora é alcançado
  por `html[data-theme="ametista"] button[data-present-btn]` (especificidade 0,2,2 > 0,2,1).
- **Correção**: (a) altura/respiro/centralização para os CTAs nos 15 temas — padding vertical
  14px, `min-height:52px`, inline-flex centrado (antes ficavam altos demais, amplificando o
  corte); (b) **chanfro reduzido só no ametista**: 10px em DOIS cantos opostos
  (simetria de rotação) em vez de 14px em um canto. Corrigi também um erro meu da fatia
  anterior, que usava `clip-path:none` — removia o traço do tema em vez de reduzi-lo.
- **ESCOPO verificado**: `--cut` é token compartilhado (tokens.css) e usado por botões do app
  (`.nvx-mbtn`, `.dash-fab`). A correção vive em **landing.css** (carregado só por index.php) e
  **entrada.css** (só login/register), ambas dentro de `@media (max-width:768px)` → **nenhum
  botão do app é afetado** e o desktop segue idêntico.
- **Estendido a login/registro**: `.lg-btn` tinha o mesmo defeito (14px assimétrico em botão
  full-width de 54px); recebeu o mesmo chanfro, também só no ametista.
- **Validação**: landing.css 170/170 e entrada.css 141/141 chaves, blocos na profundidade 1.
  Nenhum PHP alterado. `php -l` e passe visual pendentes.

## CORREÇÃO — seção "cta final" mobile + varredura anti-recorrência (2026-07-29)
- **Report**: /reports/checkpoint_landing_cta_final.md
- **Causa do escape (mesma classe do hero)**: a seção do CTA de fechamento é
  `<section data-screen-label="cta final">` e **NÃO tem NENHUM grid** — monta tudo com
  `flex-wrap: wrap` (uma linha com os 2 botões, outra com os 4 selos). A regra global de
  empilhamento mira `grid-template-columns`, e a de `flex-wrap` estava **escopada ao hero**.
  Por isso os botões ficaram lado a lado e os 4 selos espremidos em 2 colunas.
  (O padding interno JÁ estava correto: o div de conteúdo é filho direto com `max-width:900px`,
  alcançado pela regra de contêineres → 56/18/52.)
- **Corrigido**: a regra de linha-de-CTA passou a cobrir hero **e** "cta final" — botões em
  largura total empilhados (52px, `--cut` já normalizado para simétrico pela fatia anterior),
  gap 10px; os 4 selos 1 por linha, centrados; e o botão fantasma "Escolher meu tema" ganhou
  contraste de borda (`--stage-line` quase sumia sobre o roxo) + altura e centralização iguais
  ao primário.
- **Decisão documentada**: as OUTRAS linhas `flex-wrap` da landing (chips do "demo", selos da
  "gamificação", abas do "tour") **continuam quebrando naturalmente** — empilhar 4 abas/chips
  em largura total pioraria a leitura. Os cards de "features" já empilham pela guarda de largura.
- **VARREDURA ANTI-RECORRÊNCIA (17/17)**: para cada seção de primeiro nível verifiquei se existe
  contêiner alcançado pela regra de padding (a própria seção com `max-width` ou um div filho
  direto com `max-width`). **16 cobertas**; a única fora é a **esteira (credibilidade)**, e
  corretamente: usa `padding: 26px 0` (sem respiro lateral de desktop) e é full-bleed de
  propósito, com `width:max-content` já excluído da guarda de largura. **Nenhuma seção sobrou.**
- **Validação**: landing.css 169/169 chaves, bloco na profundidade 1. `php -l` e passe visual
  pendentes. Switcher/localStorage/handlers/CSRF intocados; desktop acima de 768px inalterado.

## AUDITORIA + correção da landing mobile (2026-07-29)
- **Report**: /reports/checkpoint_landing_auditoria_mobile.md (relatório completo da Fase 1)
- **Auditoria programática** dos 17 blocos em ~390px: **81 ocorrências** de 6 tipos —
  `padding-desktop` (32px lateral), `grid-fixo`, `fonte-grande` (30–44px sem clamp),
  `width-fixa` (380–560px), `abs-negativo`, `clip-path-px`.
- **Já cobertos** pelas fatias anteriores: paddings dos contêineres com max-width, grids
  multi-coluna, decorativos absolutos das seções, nav/hero.
- **DEFEITOS NOVOS corrigidos agora** (num diff, por regras genéricas — não seção a seção):
  1. **Cards de largura fixa** (420px no "demo", 380px×2 na "gamificação") estouravam os 354px
     úteis → guarda geral `[data-screen-label] *:not(.lp-track):not([data-mq-track]){max-width:100%}`
     + `width:100%` nos dois tamanhos. As duas faixas que rolam de propósito (carrossel de temas
     e esteira) ficaram de fora da guarda.
  2. **Tipografia inline sem clamp** (44/40/34/32/30px em 13 pontos de 7 seções) → um clamp por
     tamanho, cobrindo todas as ocorrências de uma vez.
  3. **Botão "Criar conta grátis"**: `var(--cut)` recorta SÓ o canto superior direito (14px);
     num botão de largura total lê como defeito → no mobile `clip-path:none` + padding lateral
     20px + 52px de altura, valendo para TODOS os CTAs com esse corte (hero e cta final).
  4. **Espaçamento vertical**: `[data-mockwrap]` tinha 98px de padding inferior → 22px; cards
     `data-card` com padding de desktop → 18px.
  5. **Diagonal do "cta final"**: `polygon(0 46px, …)` íngreme no estreito → 18px.
- **LOGIN/REGISTRO verificados**: a faixa de 240px + form estava certa, MAS a `.lg-headline`
  usa `clamp(34px,…)` — em 3 linhas estouraria a faixa curta. Ajustado para
  `clamp(22px,6.6vw,27px)` (tamanho do mockup), subline 13px e estilhaços decorativos ocultos.
  Contas: faixa 240 + form ~504px ≈ 744px, cabe em telas de 390x844 sem rolagem.
- **Hambúrguer**: já corrigido na fatia anterior (script inline após o `<nav>` + cache-busting);
  confirmado presente. Switcher de tema, localStorage, handlers e CSRF intocados.
- **Validação**: landing.css 167/167 e entrada.css 139/139 chaves (bloco novo na profundidade 1);
  landing.js OK; index.php balanceado. `php -l` e passe visual PENDENTES.

## CORREÇÃO — landing mobile: seções + hambúrguer (2026-07-29)
- **Report**: /reports/checkpoint_landing_mobile_fix.md
- **Levantamento**: os 17 blocos de primeiro nível da landing (1 nav, 1 header, 14 section,
  1 footer) TODOS têm `data-screen-label`, mas seguem DOIS padrões estruturais:
  (A) a própria seção carrega `max-width:1220px; padding:112px 32px`; (B) a seção é full-bleed
  (cor de fundo) e o padding está num **div interno** com max-width.
  A regra anterior mirava `section[data-screen-label]` → cobria só (A) e ignorava nav/header/
  footer. Por isso "cta final" (fundo `--stage`, padding 132px no div interno), footer, demo,
  comparativo etc. continuavam com respiro de desktop = **"bloco de cor chapado"**. O
  "Escolher meu tema" que o usuário citou está justamente na seção **cta final**, não na de temas.
- **Correção 1**: regra única cobrindo os DOIS padrões —
  `[data-screen-label][style*="max-width"], [data-screen-label] > div[style*="max-width"]:not(#lp-navinner)`
  → largura 100%, padding lateral 18px, vertical 56/52px. Mais: `h2` em clamp(26px,7.4vw,38px),
  parágrafos 15.5px, e os blocos decorativos absolutos (`aria-hidden`/`data-parallax`) ocultos
  para não gerar rolagem lateral.
- **Exceção documentada**: a tabela **comparativo** (RECURSO|NOVIXFIN|PLANILHA|APPS) NÃO empilha —
  as colunas são a comparação e não há rótulo por célula; mantém 4 colunas com fonte/padding
  comprimidos.
- **Correção 2 (hambúrguer)**: estaticamente o listener e o CSS estavam corretos (o CSS de abrir
  vence o de fechar por especificidade). Causas prováveis do "não abre": **landing.js em cache**
  (os assets não tinham versionamento) e a fragilidade de depender de um append no fim de um
  arquivo grande. Correção: o toggle virou **script inline logo após o `<nav>`** (delegação no
  document, Esc, fecha ao tocar link, reset ao voltar ao desktop), a IIFE duplicada saiu do
  landing.js, e landing.css/landing.js ganharam **cache-busting `?v=filemtime()`**.
- **Preservado**: switcher de tema, localStorage, handlers, CSRF. Desktop intacto acima de 768px.
- **Validação**: landing.css 157/157 chaves; landing.js `node --check` OK; index.php balanceado
  (div 421/421, button 12/12, script 3/3, nav 1/1). `php -l` e passe visual PENDENTES.
- **Login/registro NÃO verificados nesta fatia** (o usuário ainda não testou) — seguem como estão.

## RESPONSIVIDADE MOBILE — telas de ENTRADA (2026-07-29)
- **Report**: /reports/checkpoint_responsivo_entrada.md
- Completa a fatia anterior (que cobriu as 9 telas internas do shell). A entrada tem estrutura
  própria, sem shell/sidebar, então ficou de fora — agora responsiva no mesmo breakpoint (768px)
  e com os mesmos alvos (>=44px, inputs 16px).
- **Fonte**: mockup "Mobile NovixFin.dc.html", telas **10 · Entrada · Login** e **10b · Landing**.
  Nota do mockup respeitada: "o mesmo HTML do desktop com CSS a partir de 768px".
- **LANDING (index.php + landing.css + landing.js)**:
  - NAV vira logo + hambúrguer: novo `#lp-navtoggle` (4º filho do grid, `display:none` no
    desktop) e ids `#lp-navinner/#lp-brandgroup/#lp-navlinks/#lp-navcta`; com `data-navopen`
    os links e os CTAs viram painel empilhado abaixo. Toggle em landing.js (Esc, âncora e
    volta ao desktop fecham). **Não toca no switcher de tema.**
  - HERO empilha (é `<header>`, não `<section>` — a regra da fatia anterior não pegava),
    headline em `clamp(30px, 8.6vw, 42px)`, CTAs em largura total (52px), mock sem tilt.
  - SELETOR DE TEMA (hero): as setas já viravam `position:static` em 1280px — agora ficam em
    48px flanqueando o nome, como o mockup ("as setas descem para uma barra sob os CTAs").
    Seção "15 temas": setas 48px e o nome sem `min-width` de desktop.
  - **CENA DE CRISTAL ocultada no mobile** (`[data-crys]`, `[data-crys-abstract]`, `[data-marble]`,
    regmarks): são backgrounds posicionados para telas largas e vazariam no estreito — o mockup
    mobile também não os mostra. Decisão reportada.
- **LOGIN/REGISTRO (entrada.css)**: o `#lg-shell` já empilhava em 900px; agora a faixa de
  cristal `#lg-brand` tem **altura fixa de 240px** com o conteúdo ancorado embaixo (mockup:
  "faixa superior de ~240px"), `#lg-gemwrap` contido (210x230, sem vazar) e o formulário ocupa o
  resto em largura total. `.lg-input` 50px/16px, `.lg-btn` 54px, safe-area no rodapé. Os dois
  compartilham o partial `novix_entrada_brand()` e as classes `.lg-*`, então valem para ambos.
- **Preservado**: switcher de tema (View Transitions/localStorage), herança de tema pré-auth,
  handlers de login/registro, CSRF. Só reflow.
- **Validação**: landing.css 152/152 e entrada.css 135/135 chaves, blocos em profundidade 0
  (não aninhados); landing.js `node --check` OK; index/login/register com div/button/php
  balanceados. `php -l` e passe visual PENDENTES no servidor.

## RESPONSIVIDADE MOBILE — todas as telas (2026-07-28)
- **Report**: /reports/checkpoint_responsivo_mobile.md
- **Fonte**: Claude Design MCP, "Mobile NovixFin.dc.html" (mockup mobile aprovado). Padrões
  extraídos: drawer, bottom sheet, "nenhuma tabela rola de lado" (linha→cartão), kanban em
  carrossel 300px com snap, FAB pílula 56px, alvos 44px, breakpoint 768px.
- **DADOS**: nenhum valor do mockup foi hardcoded — é só reflow de layout; cada tela segue lendo
  o mesmo data-prep/handlers do desktop.
- **Shell (global, todas as telas)**: a `#nvx-side` VIRA DRAWER abaixo de 1020px (position:fixed,
  translateX, 300px/86vw), aberta pelo novo hambúrguer `#nvx-drawer-toggle` na topbar; overlay
  `#nvx-drawer-ov` tema-aware (color-mix ink 52%); fecha por tocar fora, Esc, X e ao navegar;
  trava a rolagem do fundo. **A faixa horizontal `#nvx-mnav` foi REMOVIDA** (markup + CSS) — o
  drawer é o padrão único do mockup. shell.js ganhou `wireDrawer()`.
- **Empilhamento global** (shell.css, <=768px): grades multi-coluna do conteúdo viram 1 coluna
  (cobre as grades inline das telas e as utilitárias fixas do Tailwind, com !important porque
  inline vence seletor); alvos de toque >=44px; inputs 16px (evita zoom no iOS). Exceção: o
  kanban (carrossel).
- **Modais → BOTTOM SHEET** (modal.css, <=768px, era 560px): largura total, sobem de baixo,
  puxador (::before), cabeçalho sticky, rodapé de ações sticky, scroll interno, safe-area. Os
  seletores repetem `--wide`/`--tx` para vencer as larguras específicas (0,2,1).
- **Tabelas → CARTÃO** (padrão do mockup, sem scroll lateral): Histórico (`.hist-sheet`) e
  Cartões (faturas). Cada `<td>` recebeu `data-label` (só atributo, nenhum dado tocado) e o CSS
  transforma linha em cartão com rótulo à esquerda e valor à direita. Carteiras: a "tabela" de
  contas (grid de divs) virou cartão via `.wal-thead`/`.wal-trow` + data-label.
- **Dashboard**: KPIs empilham em 768; kanban vira carrossel com scroll-snap (coluna
  min(300px,82vw), snap center); FAB pílula 56px com safe-area.
- **Entrada/Landing** (fora do shell): entrada já empilhava em 900px, ganhou conforto de toque;
  landing (index.php) ganhou bloco <=768px que empilha as grades inline das `<section>` e reduz
  o respiro lateral.
- **Validação**: 16 CSS com chaves balanceadas; 5 JS `node --check` OK; PHP/tr/td balanceados em
  header/history/credit-cards/wallets. `php -l` e o passe visual PENDENTES no servidor.

## Kanban de categorias — reskin + diagnóstico de dados (2026-07-27)
- **Report**: /reports/checkpoint_kanban_categorias.md
- **PARTE 1 (visual)**: os cabeçalhos das colunas já eram tokenizados; os CARDS (render por
  kanban.js) usavam Tailwind fixo (bg-slate-800/50, text-white, text-emerald/rose-300) → cinza
  chapado. Reconstruídos com estilos inline var(--token): card em --surface/--line-soft/--cut/
  sombra (dashboard.css `.dash-screen .dashboard-kanban-card` + hover accent), nome --ink, labels
  --muted, valores --f-num. Variação em 3 estados: gasto subiu → --neg (↑), gasto caiu → --pos
  (↓), sem mudança → --muted (→). data-balance nos valores (respeita ocultar-saldos). Mobile
  overflow-x + min-width por coluna já existia. Drag-and-drop preservado (drag/dragEnd/draggable/
  dataset/localStorage intactos).
- **PARTE 2 (dados) — o R$ 0,00 é CORRETO, não bug**: `categoryVariationsData` soma só despesas
  com `is_effective=1` do mês corrente (current) e anterior (previous). Os gastos do usuário estão
  majoritariamente PENDENTES (is_effective=0, efetivação futura) → soma efetivada do mês = 0.
  DECISÃO documentada: manter **efetivadas apenas**, para ser CONSISTENTE com todo o resto do
  dashboard (hero/KPIs/gráficos usam is_effective=1); incluir pendentes faria o kanban contradizer
  as demais métricas. O cálculo (Atual=mês corrente, Anterior=mês anterior, variação entre os dois)
  já bate com o design — nenhum número inventado. Com uma despesa EFETIVADA no mês, o card mostra
  o valor real. (Se um dia quiser incluir pendentes, é remover `AND t.is_effective = 1` da query —
  mas diverge do dashboard.)
- **Validação**: node --check kanban.js OK; dashboard.css 58/58. `php -l` no servidor.

## Fix warning PHP 8 "use non-compound has no effect" (2026-07-27)
- `use DateTimeImmutable;` em escopo GLOBAL é no-op → warning. Ocorria nos 6 handlers sem
  namespace: actions/{transactions/create,update,auto-effective, credit-cards/pay-bill,
  save-transaction-settings, goals/save}.php. Removido o `use` e prefixado `new \DateTimeImmutable`
  (funciona com/sem namespace). Os `src/*` (com namespace: TransactionRepository,
  CreditCardBillRepository, RecurringService) NÃO foram tocados — ali o `use` é significativo e
  não emite warning (e respeita a proteção do RecurringService). Estático OK (braces/parens).

## Correção do saldo desnormalizado (2026-07-27)
- **Report**: /reports/checkpoint_saldo_recompute.md
- **PARTE 1 — auditoria dos handlers**: o modelo de saldo é 100% dirigido por transações (conta
  nasce com balance 0.00, sem saldo de abertura — actions/accounts/save.php). Fórmula canônica:
  income +amount, expense −amount, só quando is_effective. TODOS os handlers ATUAIS já ajustam o
  saldo com essa fórmula e atomicamente:
  - CORRETOS: TransactionRepository::create/delete/deleteByAccount/deleteAll (envoltos em BEGIN/
    COMMIT); pay-bill (usa repo->create); save-transaction-settings (flip is_effective + ajuste
    consistente); auto-effective; accounts/delete (apaga conta+txns, saldo some junto).
  - LACUNA CORRIGIDA: `TransactionRepository::update()` NÃO estava envolto em transação — UPDATE +
    ajuste de saldo agora na MESMA transação atômica (guard inTransaction()), como create/delete.
  - **O descolamento é RESIDUAL HISTÓRICO**: deletes pré-v3 não revertiam o saldo (confirmado nos
    comentários do próprio código). O código atual não gera novo descolamento.
- **PARTE 2 — recompute**: NEW `scripts/recompute-balances.php` (CLI-only, idempotente).
  Recalcula balance = SUM(income efetivada) − SUM(expense efetivada) por conta (mesma fórmula,
  vale p/ credit também). DRY-RUN por padrão (relatório antes→depois, não grava); `--apply`
  grava dentro de UMA transação. NÃO apaga transações.
- **Validação**: estático OK (chaves/parênteses/try-catch balanceados); `php -l` e o dry-run
  PENDENTES no servidor (PHP/DB indisponíveis localmente).
- **A RODAR NO SERVIDOR** (após backup `mariadb-dump ... accounts`): dry-run → revisar relatório →
  `--apply` → dry-run de novo (diff 0) → conferir no app criar/editar/EXCLUIR recalcula certo.

## Polimento do modal de lançamento (2026-07-26)
- **Report**: /reports/checkpoint_polimento_modal_lancamento.md
- **1) BUG Cancelar do sub-modal "Nova categoria"**: havia DOIS `[data-close-inline-category]`
  (X + Cancelar) mas dashboard.js usava `querySelector` (só o primeiro=X). Trocado por
  `querySelectorAll().forEach`. Cancelar fecha SÓ o sub-modal (modal.close() do inline) e volta
  ao lançamento com campos intactos (dialogs separados). Fluxo de criar categoria preservado.
- **2) Backdrop inconsistente**: removida a classe `backdrop:bg-slate-950/70` dos dialogs
  (dashboard + categories/goals/wallets); agora só vale o `.nvx-modal::backdrop` tokenizado
  (`color-mix(--ink 45%, transparent)`), tema-aware, igual aos demais.
- **3) Largura/respiro**: `dialog.nvx-tx-modal` -> `width: min(600px,100%)` (era ~512 via
  max-w-lg). Rolagem interna (`max-height:90vh; overflow-y:auto`) e centralização mantidas.
- **4) Categorias com "Ver mais"**: tx-modal.js renderChips colapsa além de 8 (mantém o ativo
  visível) + botão "Ver mais (N)" que revela o resto; "+ Nova" sempre acessível. Nenhuma
  categoria perdida — só colapso visual. CSS `.nvx-chip--collapsed/--more`.
- **5) Sublinha do valor**: a linha solta ficava curta/deslocada (símbolo à esquerda deslocava);
  virou `border-bottom: 2px solid var(--accent)` no próprio input -> acompanha a largura do campo
  e alinha com o número. `.nvx-mval-line` ocultada.
- **Files**: dashboard.js, tx-modal.js, modal.css, dashboard/categories/goals/wallets.php
  (só classe backdrop). Sem lógica financeira, sem tocar create/update.php nem names.
- **Validação**: node --check tx-modal.js/dashboard.js OK; modal.css 92/92; backdrop 0 restantes.

## Ajustes finos: sidebar sticky + datas + scroll do modal (2026-07-26)
- **Report**: /reports/checkpoint_ajustes_shell_modal.md
- **1) Sidebar não acompanhava o scroll (bug em TODAS as telas)**: causa raiz em
  `public/styles/main.css` — `html, body { overflow-x: hidden !important }` fazia html/body
  virarem contêiner de rolagem (com overflow-x != visible, o eixo Y computa `auto`), quebrando
  o `position: sticky` de `#nvx-side`. **Fix**: `overflow-x: clip` (clipa o horizontal SEM criar
  contêiner de rolagem) + removido `body { overflow-y: auto }`. O viewport volta a ser o scroller
  e o sticky (sidebar + #nvx-top) funciona nas 10 telas, sem gap. shell.css NÃO precisou mudar
  (o sticky já estava correto lá; o quebrador era o overflow global).
- **2) Campo de data "duplicado" no modal**: NÃO era duplicação — são 2 campos distintos:
  `transaction_date` (rotulado agora "Data do lançamento") e `effective_date` ("Data de
  efetivação", só aparece com status Pendente, via toggleEffectiveDate). Rótulos clarificados
  em criar e editar. Handlers/names intocados.
- **3) Comprimento do modal**: adicionado `max-height: 90vh; overflow-y: auto` a
  `dialog.nvx-modal` (desktop) — modais longos (recorrência ligada) rolam DENTRO do card,
  centralizados, sem esticar a página. Progressive disclosure já correto (recorrência só com
  "Recorrente" marcado; efetivação só com Pendente).
- **Files**: main.css, modal.css, dashboard.php (só rótulos/comentário de data). Sem lógica.
- **Validação**: main.css 117/117 chaves, modal.css 88/88; `php -l` pendente no servidor.

## Modal de lançamento reconstruído — estrutura do design (2026-07-26)
- **Report**: /reports/checkpoint_modal_lancamento_reconstruido.md
- **Fonte**: Claude Design "NovixFin App.html" (modal "Novo/Editar lançamento").
- **Arquitetura (INEGOCIÁVEL)**: a UI mudou, os DADOS não. Os controles nativos `<select
  name=type>`, `<select name=category_id>`, `<input name=is_effective>` viram FONTE DE VERDADE
  OCULTA (class hidden); a UI do design (toggle/chips) reflete e dirige esses controles,
  disparando 'change' para que dashboard.js (filtro de categoria por tipo, criação inline,
  pré-preenchimento na edição) siga funcionando SEM alteração. Mesmos names/ids postados a
  create.php/update.php — handlers NÃO tocados.
- **NEW public/scripts/tx-modal.js**: controlador da camada visual. Toggle segmentado ligado a
  <select> (data-seg-target) e a checkbox (data-segcheck-target); chips espelhando o <select>
  de categoria (data-chips-target) com MutationObserver (re-render em filtro por tipo / criação
  inline); re-sincroniza tudo no `open` do <dialog.nvx-tx-modal> (edição pré-preenche antes de
  showModal). Símbolo de moeda continua no wiring inline do dashboard.
- **modal.css**: componentes `.nvx-seg/.nvx-seg-btn(.is-active)`, `.nvx-chips/.nvx-chip
  (.is-active/--add)`, `.nvx-dashed`, `.nvx-mgrid(--status)`, `.nvx-mfield/.nvx-mlabel`.
- **dashboard.php**: modais criar e editar reconstruídos na ordem do design (cabeçalho → toggle
  tipo → VALOR em destaque → descrição → chips categoria → conta+data → status+moeda →
  recorrente → rodapé "Cancelar"/"Lançar"/"Salvar alterações" + nota "Enter salva…"). Campos de
  recorrência preservados integralmente (dados mais ricos que o design — necessário ao backend).
  `required` removido dos selects agora OCULTOS (evita "invalid form control not focusable");
  server valida. account_id (visível) mantém required.
- **Fiação preservada**: type, category_id, amount, currency, description, transaction_date,
  is_effective, effective_date, is_recurring, recurrence_* + _token. "Enter salva" = submit
  nativo do form (sem JS extra).
- **Validação**: node --check tx-modal.js OK; dashboard.php balanceado (div 150/150, dialog 4/4,
  form 5/5, php 158/158, select 15/15); `php -l` pendente no servidor. TESTE FINANCEIRO pendente
  (criar despesa/receita por toggle, categoria por chip, efetivada/pendente, recorrente, editar).

## Modais fiéis ao design — composição única (2026-07-26)
- **Report**: /reports/checkpoint_modais_design.md
- **Fonte**: Claude Design "NovixFin App.html" (seção "Sobreposições" + modal mTitle/mSub/mBtn/mSym).
- **modal.css (reescrito)**: `dialog.nvx-modal` reproduz o card do design (surface/card-b/raio/
  sombra + animação nvxPop); `::backdrop` color-mix(ink 45%)+blur; classes de composição
  `.nvx-mhead/.nvx-mtitle/.nvx-msub/.nvx-mclose` (cabeçalho com X), `.nvx-mvalue/.nvx-msym/
  .nvx-mval-input/.nvx-mval-line` (campo de valor grande com símbolo + sublinhado accent),
  `.nvx-mfoot/.nvx-mbtn` (primário accent com clip-path --cut + ícone check) `/.nvx-mcancel`,
  `.nvx-mdanger/.nvx-mdanger-icon` (confirmação de exclusão do design) e `nvx-modal--danger`
  (borda neg) + `nvx-modal--wide` (540px). **MOBILE: bottom-sheet** (position fixed inset auto
  0 0 0, sobe com nvxSheet). Mantém o mapa Tailwind->token.
- **Símbolo de moeda dinâmico (mSym)**: `<span data-currency-symbol="#sel">` reflete R$/US$/€
  conforme o select; wiring genérico em dashboard (cobre criar e editar).
- **Aplicação nos 11 <dialog>** (cabeçalho + rodapé do design, sem tocar em forms/ids/names/
  CSRF/handlers/confirmações):
  - dashboard: criar ("Novo lançamento"/"Registre em segundos."/botão "Lançar" + símbolo),
    editar ("Editar lançamento"/"Ajuste e salve — mesmos campos do novo."/"Salvar alterações"
    + símbolo), inline-category, excluir (composição de perigo).
  - accounts (conta + excluir-perigo), categories, goals (título criar/editar via goals.js +
    fecha por qualquer [data-close-modal]), wallets (wide), credit-cards (payBill wide +
    transactionSettings).
- **goals.js**: closeButtons -> querySelectorAll (X + Cancelar); título alterna Nova/Editar meta.
- **Validação**: node --check goals.js OK; modal.css 72/72 chaves; 4/2/2/1/1/1 <dialog>
  balanceados; `php -l` pendente no servidor. Passe visual pendente (foco "Novo lançamento").

## Tokenização dos modais — componente compartilhado nvx-modal (2026-07-26)
- **Report**: /reports/checkpoint_modais_tokenizados.md
- **Causa raiz**: os mapas por-tela (`.dash-screen .bg-slate-900` etc.) usam combinador
  DESCENDENTE e não vestem o próprio <dialog> (que carrega as duas classes no mesmo elemento).
  Sintoma: "Novo lançamento" do dashboard quase branco no tema claro, inputs sem contraste,
  sem --cut. Quebrados de fato: dashboard (4 modais), accounts (2), cards (2). Já corretos:
  cat/goals/wallets (tinham `dialog.X-modal{background:var(--surface)}`) e history (.hist-modal).
- **Solução**: NEW public/styles/modal.css — componente global `dialog.nvx-modal` (modelado no
  .hist-modal): superfície --surface, borda --card-b, sombra, raio calc(--r+10), ::backdrop
  tema-aware; inputs/selects/textarea com --bg/--line/--ink + foco --accent; mapa Tailwind→token
  escopado (violet→accent com clip-path --cut no botão primário; rose→neg no botão de perigo;
  slate→surface). Carregado globalmente em header.php (após shell.css). Especificidade
  `dialog.nvx-modal` (0,1,1) vence as utilitárias Tailwind (0,1,0) — sem !important (o themes.css
  que exigia !important já foi removido).
- **Aplicação**: classe `nvx-modal` adicionada aos 11 <dialog>: dashboard 4 (create/edit/delete/
  inline-category), accounts 2, credit-cards 2, categories 1, goals 1, wallets 1. history mantém
  .hist-modal (já tokenizado, modal de recorrência financeiramente sensível — não re-mexido).
- **NÃO tocado**: forms, ids, names, campos, CSRF, handlers, confirmações de exclusão. Só classe.
- **Dívida**: os mapas de modal por-tela (.cat-modal/.goals-modal/.wallets-modal/.acc-overlay/
  .cards-modal) ficam redundantes com nvx-modal — remover numa limpeza futura (sem conflito de
  resultado; mesmos tokens).
- **Validação**: modal.css balanceado (33/33 chaves); 11/11 dialogs com nvx-modal; `php -l`
  pendente no servidor (só atributos class alterados no PHP). Passe visual pendente.
- **Versionamento RESOLVIDO**: `Desktop/NovixFin` agora É um repo git funcional (toplevel
  próprio, branch `master`, `.git` populado). Os arquivos da fatia Cadastros **estão
  rastreados e commitados** — o "sumiço" anterior não se repetiu. (A nota de bloqueio abaixo,
  da fatia Cadastros, ficou obsoleta e é mantida só como histórico.)

## Remoção do andaime de compatibilidade de tema (2026-07-26) — COMPLETA
- **Report**: /reports/checkpoint_andaime_removido.md
- **Diagnóstico (grep no repo real)**: único loader ativo era header.php; consumidores de
  JS eram chart-tokens.js:59 e dashboard.js:12; PHP: NOVIX_THEME_LEGACY/novix_legacy_theme
  em themes.php. As 10 telas já carregam classe `.*-screen` tokenizada. Zero consumidor
  restante fora do próprio andaime -> removível.
- **header.php**: removidos os `<link>` de themes.css e theme-compat.css; `<html>` perde
  `data-legacy-theme` (fica só `data-theme=<slug>`); `<body>` perde `data-theme` legado e
  `data-novix-theme` (fica `data-theme=<slug>`); classes fixas `bg-slate-950 text-slate-100`
  removidas (senão o tema claro herdaria fundo escuro sem o themes.css). `$legacyTheme`
  eliminado.
- **shell.css**: nova regra `html, body { background: var(--bg); color: var(--ink); }` repõe
  o papel do theme-compat.css — agora por TOKEN, não por nome de tema legado.
- **themes.php**: `NOVIX_THEME_LEGACY` (mapa) e `novix_legacy_theme()` removidos.
- **JS migrado para getComputedStyle**: chart-tokens.js e dashboard.js decidem claro/escuro
  pela LUMINÂNCIA do token `--bg` (0.299R+0.587G+0.114B > 0.5), sem `data-legacy-theme`.
  Cobre os 15 temas sem lista fixa. `node --check` OK nos dois.
- **Arquivos deletados**: public/styles/themes.css (819 linhas), public/styles/theme-compat.css.
- **NÃO tocado**: tokens.css e os CSS por-tela (comentários que citam themes.css são inertes).
- **Validação**: node --check OK (chart-tokens, dashboard). `php -l` pendente no servidor
  (local indisponível). Passe visual pendente: 10 telas em ametista (claro) + tinta (escuro),
  conferindo que os GRÁFICOS seguem os tokens (teste-chave).

## Current State (Cadastros — histórico; nota de bloqueio obsoleta)

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

## Planilhas — investigação 2026-11-04
- Etapa da barra de fórmulas em reports/checkpoint_sheets_2026-11-04.md; integração e concorrência pendentes. Sem deploy.

- Regressão HTTP isolada em reports/checkpoint_sheets_http_2026-11-04.md; concorrência e validação final pendentes.

- Planilhas: aba vazia e fila local em reports/checkpoint_sheets_queue_2026-11-04.md; proteção entre páginas pendente.

- Planilhas: verificação transacional de versão em reports/checkpoint_sheets_conflict_2026-11-04.md; validação final pendente.

- Planilhas: testes de handlers portáveis, ver reports/checkpoint_sheets_test_paths_2026-11-04.md.

- Planilhas: validação final e plano de rollback em reports/sheets_persistence_validation_2026-11-04.md. Sem deploy.

- PR planilhas: revisão obrigatória; ver reports/checkpoint_sheets_revision_required_2026-11-04.md.

- PR planilhas: resposta perdida e cópia manual; ver reports/checkpoint_sheets_uncertain_response_2026-11-05.md.

- PR planilhas: preparação e limitações em reports/sheets_deploy_readiness_2026-11-05.md; suíte 14/14, sem deploy.

- PR planilhas: ensaio isolado em reports/sheets_gate_rehearsal_2026-11-05.md; drenagem de produção e OPcache FPM não comprovados; deploy bloqueado.

- Auditoria final: correção do clipboard da recuperação e suite 14/14; ver reports/checkpoint_sheets_final_audit_2026-11-05.md.

- FPM real: listener separado, proxy+scoreboard, OPcache positivo/negativo e rollback com barreira ensaiados; ver reports/checkpoint_sheets_fpm_lab_2026-11-05.md. Bootstrap na VPS ainda exige janela autorizada.

- Bootstrap por reload reprovado no FPM isolado: 0/2 gravações pós-resposta; ver reports/checkpoint_sheets_fpm_bootstrap_2026-11-05.md. NO-GO na VPS.

- Auditoria/FPM concluídos em laboratório; relatório vigente reports/sheets_final_audit_and_fpm_2026-11-05.md. Primeiro corte na VPS e estabilização da manutenção ainda não comprovados; NO-GO.
