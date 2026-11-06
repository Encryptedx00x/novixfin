# NovixFin v3 — Context & Invariants

## Source of truth
- Primary working root: C:\Users\Encryptedx\Desktop\NovixFin
- D:\[ Importante ]\[ Serviços e ferramentas ]\Novix Fin v2 — redundant copy (byte-identical active code; nested "Novix Fin/" = older backup). Treat as read-only. Do NOT modify.
- Old snapshot folders in primary root: v1.7 (antes de mudar design completamente), v2 (prototipo 1), v2.1 (com sidebar correta) — to be archived (moved to /archive), not deleted (rollback path).

## Production environment (atualizado 2026-07-23)
- Produção é **VPS com Docker + Caddy + MariaDB**, rodando **PHP 8.3.32**.
  A hospedagem compartilhada da Hostinger foi descontinuada.
- **Alvo de linguagem: PHP 8.1+ para código NOVO** (match, str_contains,
  nullsafe, union types, promoção de construtor, enums liberados).
  **Não retrofitar** o código 7.4 existente — ele roda bem no 8.3 e reescrever
  é churn com risco de regressão. Modernize só o arquivo que você já está
  mexendo por outro motivo. `catch` continua sempre com variável (estilo da casa).
- **Docroot: `/srv/sites/novixfin/public`. A pasta `actions/` fica FORA dele**
  e só é alcançável pela rota `@actions` no Caddyfile do host
  (`/srv/stack/Caddyfile`).
- **Modo de falha a reconhecer:** sem a rota `@actions`, todo `POST /actions/*`
  cai no fallback e devolve **a landing com HTTP 200**. Nenhum 404, nenhum 500,
  nenhum log — o form parece enviar e não faz nada. Se um formulário "não faz
  nada" em produção mas funciona local, checar a rota `@actions` PRIMEIRO.

## Architecture decisions (v3)
- Stack: plain PHP/PDO, no frameworks.
- Keep file-based routing (no central router).
- Keep page-controller + action-handler + repository structure.
- Composer allowed (vlucas/phpdotenv + phpunit planned). If Hostinger lacks Composer CLI, use a hand-rolled env parser and skip PHPUnit for php -l + manual smoke tests.
- Gitignored: .env, config/config.php (production). Commit .env.example, config/config.example.php.
- Schema consolidation via ordered idempotent migrations → /migrations, tracked in a `migrations` table.

## Migration assumptions
- Live prod schema = current Hostinger DB. Must preserve rows.
- Divergences to resolve: categories.user_id (→ NOT NULL via backfill to a system user if orphaned); goals.name vs title (pick `title`); goals.type enum normalize (saving/spending_limit/income_target/balance_target/expense_limit; drop savings/debt_payoff); accounts.balance vs initial_balance (keep `balance`).
- Remove runtime DDL & SHOW COLUMNS probes after canonical schema is in place.

## Invariants to preserve
- All routes that work today must keep working (login, register, logout, dashboard, history, accounts, wallets, credit-cards, categories, goals, profile, currency-settings).
- PHP sessions + Argon2id/BCrypt auth + single-session CSRF token → keep.
- Redirect(), flash(), requireAuth() public contract → keep (only fix bugs).
- No external services/integrations required (AI stays rule-based, no FX API).
- v3.1 hide-balances privacy mode: `users.hide_balances` is the source of truth. Shared layout must render `body.hide-balances` server-side when enabled to avoid first-paint leaks; JS may toggle and persist, but must not override the DB preference with stale browser storage.
- v3.1 inline category creation: dashboard transaction forms use `actions/categories/create-inline.php` with auth + CSRF and JSON responses. Newly created categories must update the dashboard create/edit category selects without full-page navigation and must preserve category type (`income`/`expense`). Transaction category scope must allow both user-owned categories and global default categories (`user_id IS NULL`).
- ~~PHP 7.4 compatibility~~ **APOSENTADO em 2026-08-09** (decisão do usuário na
  fila do TASKS.md, Task 0.1). Produção roda **PHP 8.3.32** e a hospedagem
  compartilhada foi descontinuada; não existe mais ambiente 7.4. AGENTS.md e as
  REGRAS GLOBAIS do TASKS.md já diziam **PHP 8.1+ para código novo** — esta
  linha era a única fonte em contrário e contradizia as duas.
  O que **permanece obrigatório**: todo `catch` com variável
  (`catch (Throwable $e)`) — estilo da casa, porque catch sem variável torna o
  erro impossível de logar. O scan estático em `tests/recurrence_installments.php`
  agora verifica só isso.
  Continua valendo: **não retrofitar** código 7.4-era existente (churn com risco
  de regressão e zero ganho para o usuário).
- Balance privacy hotfix: hidden balances are replaced by JS textContent dots
  using `data-balance-value`; CSS pseudo-element masking is only a pre-JS
  first-paint fallback and must not remain active after
  `body.balance-visibility-ready`.
- Balance visibility follow-up hotfix: `public/scripts/balance-visibility.js`
  keeps one page-level `currentHidden` source of truth. Initial state is read
  once from `body[data-hide-balances-default]`; clicks must not re-read that
  dataset or roll the UI back after AJAX persistence failures.
- Balance visibility global-toggle hotfix on 2026-07-10:
  - Shared layout must prefer DB-backed `$user['hide_balances']` over stale
    session data, then sync the value into `$_SESSION['user']['hide_balances']`.
  - `includes/layout/header.php` may fetch the current user when a page did not
    pass `$user`, so non-dashboard pages still get the correct privacy default.
  - The balance visibility button is global for authenticated non-dashboard
    pages; dashboard keeps its original button to avoid duplicate DOM IDs.
  - `public/profile.php` posts an explicit `hide_balances=0` hidden input so
    disabling "Ocultar saldos por padrão" is not represented only by a missing
    checkbox field.
- Balance visibility/profile/bulk-delete follow-up hotfix on 2026-07-10:
  - `public/scripts/balance-visibility.js` delegates clicks from `document`, so
    the shared non-dashboard button and the dashboard button use the same
    handler path.
  - The balance visibility action redirects through `redirect('profile.php')`
    for normal form submissions.
  - History destructive bulk forms post scoped `redirect_to=history.php`.
  - `TransactionRepository::deleteAll()` is transactional, reverses account
    balance effects for effective rows, clears matching `recurring_generated`
    rows, updates affected bills, and rolls back on failure.
- Smoke test invariant: `tests/smoke.php` must explicitly load
  `includes/throttle.php` before W2 throttle checks, and must not call throttle
  helpers after failed callable checks.

## Entrada slice invariants (2026-07-22)
- Canonical theme whitelist lives ONLY in `includes/themes.php` (15 slugs,
  default 'ametista'); validate every theme write with `novix_valid_theme()`.
- users.theme is VARCHAR(20) NOT NULL DEFAULT 'ametista' after migration
  20240101000007; legacy dark/black -> tinta, light -> ametista.
- Pre-auth screens (login/register) inherit data-theme from
  localStorage['novixfin-theme'] via a pre-paint head snippet in
  `includes/layout/entrada.php`; no theme selector on those screens.
- Registration bridges the localStorage theme into users.theme through a hidden
  `theme` input validated in `createUser()`.
- "Esqueci minha senha" has NO backend; the link was removed — do not add a
  partial reset flow.

## Shell slice invariants (2026-07-23)
- A moldura do app (sidebar + topbar) é o design tokenizado em includes/layout/header.php,
  vestida por public/styles/shell.css — escopado sob `#nvx-shell`/`.nvx-*`, não vaza.
- Recolher usa `data-side="rail"` no `#nvx-shell` (public/scripts/shell.js), persistido em
  localStorage['sidebarCollapsed']. Mobile (<1020px): sidebar some, faixa `#nvx-mnav` entra
  (via CSS, sem JS). Os ids antigos #sidebar/#sidebarToggle/#mainContent NÃO existem mais;
  o código de sidebar do main.js ficou inerte (guardado por if) — não remover main.js (tem
  bits genéricos), mas o shell não depende dele.
- Nav sem emojis: ícones de linha família Feather. Grupos PRINCIPAL (Dashboard, Histórico,
  Contas, Carteiras, Cartões, Metas) e GESTÃO (Categorias, Import/Export, Moeda, Perfil).
  Import/Export é UM item -> import.php (ativo também em export.php). Item ativo por
  `aria-current="page"` (detecção via basename).
- Topbar entrega só eyebrow + título (mapa por página em header.php). Busca, seletor de
  mês e sino foram OMITIDOS por não terem backend — não recriar como decorativo.
- "Ocultar saldos" fica no rodapé da sidebar como `#balance-visibility-btn` (+ #-icon/-text),
  renderizado só fora do Dashboard (que tem o próprio no conteúdo — evita id duplicado).
  balance-visibility.js e o handler continuam intocados. Sem "Plano Pessoal": rodapé mostra
  nome + email.
- footer.php fecha `</div></main></div>` = content, #nvx-main, #nvx-shell. Se mexer no
  aninhamento do shell, manter essa ordem.

## Dashboard slice + charts invariants (2026-07-23)
- Gráficos (dashboard.js, forecast-chart.js) NÃO podem ter cor fixa: leem os tokens em
  runtime via `window.NVXCharts.palette()` (public/scripts/chart-tokens.js), que resolve
  `getComputedStyle(<html>)` porque canvas não entende var()/color-mix. Novo gráfico deve
  usar `P.accent/.pos/.neg/.gold/.muted/.ink/.surface` + `.a(alpha)`, nunca hex/rgb fixo.
- chart-tokens.js precisa carregar ANTES de dashboard.js/forecast-chart.js no $pageScripts.
- kanban.js é 100% CSS/token (não lê data-theme, sem cor fixa) — manter assim.
- dashboard.css tokeniza a tela por OVERRIDE escopado das utilitárias Tailwind sob
  `.dash-screen` (não reescreve markup — preserva os 3 forms e hooks JS). Vence o themes.css
  legado via `html .dash-screen` + `!important` só nas cores. É dívida ligada à mesma ponte
  de compatibilidade; remover quando o markup largar as utilitárias. Nada de regra fora de
  `.dash-screen`/`.dash-fab`.
- FAB e qualquer superfície com clip-path do tema usa `filter: drop-shadow`, nunca
  `box-shadow` (o clip-path recorta box-shadow).
- Landing: os textos por tema (headline h1a/h1acc/h1post, sub, rótulo selName, rodapé
  themeName) são atualizados por renderTheme() em landing.js via hooks `[data-lp="..."]`.
  Todo hook tem que ser um elemento de ABERTURA bem-formado — atributo em tag de fechamento
  quebra silenciosamente (foi o bug do selName).

## Perfil slice / theming do shell invariants (2026-07-22)
- `public/styles/tokens.css` é a fonte canônica dos tokens do APP e contém APENAS
  custom properties. NÃO adicione seletores de componente nele: a garantia de que
  carregá-lo no shell não muda as telas não reskinadas depende disso.
- Ponte de compatibilidade transitória no shell:
  `<html data-theme="<slug>" data-legacy-theme="<dark|light>">` e
  `<body data-theme="<legado>" data-novix-theme="<slug>">`. themes.css veste as telas
  antigas pelo atributo do body; tokens.css veste o Perfil pelo atributo do html.
  Mapa em `NOVIX_THEME_LEGACY` (includes/themes.php). Remover a ponte, o
  `theme-compat.css` e o mapa quando TODAS as telas internas consumirem tokens.
- Quem ler tema em JS para decidir claro/escuro deve usar `data-legacy-theme`
  (o `data-theme` do html é o slug, não dark/light) — ver dashboard.js.
- CSS de tela reskinada fica escopado sob um prefixo próprio (`.pf-screen` no Perfil)
  e é carregado via `$pageStyles` (espelha o `$pageScripts` do footer).
- Perfil só exibe eventos de gamificação com caller real. `goal_completed` e
  `spending_limit_held` existem em GamificationService mas NÃO são disparados por
  ninguém — não apresentá-los como ativos enquanto não forem ligados.
- Os limiares de nível/badge vêm das consts públicas de GamificationService; não
  duplicar esses números em views.
- Dívida: tokens dos 15 temas duplicados em entrada.css, landing.css e tokens.css.
  Consolidar só após o reskin das telas internas.

## Landing slice invariants (2026-07-22)
- public/index.php é o porte fiel da landing "Seletor Seta"; a fonte visual da
  verdade é o projeto Claude Design (arquivo "NovixFin - Landing Seletor Seta").
- Setas da hero, seletor da seção "15 jeitos" e carrossel compartilham UMA fonte
  de estado em public/scripts/landing.js (state.theme); qualquer novo switcher
  deve passar por goTheme()/applyTheme(), nunca setar data-theme direto.
- Troca de tema usa document.startViewTransition com fallback direto e
  prefers-reduced-motion sem morph; persiste em localStorage['novixfin-theme']
  (mesma chave que Entrada lê) validado contra includes/themes.php.
- Cena de cristal ametista depende de public/assets/{quartzo,cristaldireito,
  marmore-trim}.png — não renomear sem atualizar landing.css.
- landing.css e entrada.css duplicam os tokens dos 15 temas de propósito (a
  landing tem --prism/--disp-* extras); unificação é refactor consciente futuro.

## Known placeholders to wire for real in v3
- Recurring transactions: scheduler generates materialized occurrences. New recurring_instances table or parent_transaction_id self-FK + occurrence_date.
- Gamification: UserRepository::updateProgress must actually be called from transaction/bill/goal actions.
- Insights: move heuristics out of dashboard.php into src/Services/InsightService.php.

## Local environment constraints
- **No PHP binary on dev machine** (PATH + depth-4 search of C:\ returned nothing).
- Cannot run `php -l` for syntax validation locally. Mitigation:
  - Apply minimal, surgical diffs via the edit tool (exact replacements).
  - Visual brace/paren/semicolon review after each edit.
  - Optional: lightweight syntax sanity via PowerShell regex (count of `<?php` vs `EOF`, parens balance) before commit.
  - Final syntax verification happens on the Hostinger server when the user deploys.
- Novix Fin v2 on D:\ is a read-only redundancy copy; do NOT modify.

## v3.1 Batch 2 local validation status
- Latest checkpoint: `/reports/checkpoint_batch2_tasks4_6.md`.
- `python -m pytest tests\probe_batch2_features.py` passes (5 checks).
- `node --check public\scripts\dashboard.js` and `node --check public\scripts\balance-visibility.js` pass.
- PHP lint/smoke still needs a PHP-capable environment.

## v3.1 P1 light theme polish status
- Latest UI checkpoint: `/reports/checkpoint_p1_light_theme_polish.md`.
- Light-theme contrast fixes are intentionally scoped through dashboard component classes:
  - `.dashboard-forecast-controls`
  - `.dashboard-forecast-week-chip`
  - `.dashboard-kanban-board`
  - `.dashboard-kanban-card`
  - `.dashboard-achievements-empty`
- Do not broaden these into global Tailwind-style utility overrides unless a later audit proves the issue affects multiple pages.
- Dashboard recent history now shows up to 8 items. Keep the PHP-rendered slice and the script JSON slice aligned.
- Local validation passed for `public/scripts/dashboard.js`, `public/scripts/forecast-chart.js`, `public/scripts/kanban.js`, and the Batch 2 Python probes. PHP/browser validation remains server-side.

## v3.1 hotfix PHP 7.4 + balance visibility status
- Latest hotfix checkpoint: `/reports/checkpoint_hotfix_php74_balance_visibility.md`.
- Active PHP source was audited for PHP 7.4 CLI blockers: variableless catch,
  constructor property promotion, `mixed`, `match`, nullsafe operator, and
  `str_contains`.
- Balance visibility now uses JS text replacement instead of a permanent CSS
  overlay: hidden state shows only dots, visible state restores the original
  `data-balance-value`.
- Local validation can only run JS/Python because PHP is unavailable locally.
  Hostinger must still run the targeted `php -l` commands and
  `php tests/smoke.php`.

## v3.1 hotfix smoke + balance toggle + sidebar status
- Latest hotfix checkpoint: `/reports/checkpoint_hotfix_smoke_balance_sidebar.md`.
- `tests/smoke.php` now mirrors the production manual fix by using
  `class_exists()` instead of `ReflectionClass::exists()`.
- Smoke return-type inspection uses `ReflectionNamedType::getName()` to avoid
  PHP 7.4 `ReflectionType::__toString()` deprecation output.
- W2 throttle smoke checks require `includes/throttle.php` directly and guard
  the identifier assertions behind `$hasThrottleFunctions` to avoid fataling
  after a failed callable check.
- Balance visibility now uses `currentHidden` plus `setHidden(hidden)` as the
  single source of truth and does not roll back UI state if preference
  persistence fails.
- Sidebar footer alignment is scoped through `.sidebar-footer`,
  `.sidebar-user-row`, and `.sidebar-logout-btn`.
- Local validation passed for the requested JS checks and Python probes; PHP
  lint/smoke and manual browser validation remain server-side.

## v3.1 tiny sidebar footer visual hotfix status
- Latest hotfix checkpoint: `/reports/checkpoint_hotfix_sidebar_footer.md`.
- The follow-up patch is CSS-only in `public/styles/main.css`.
- Expanded sidebar footer must stretch full width, keep the username on one
  ellipsized line, and make logout match the 2.5rem nav-item rhythm.
- Collapsed sidebar must keep the username hidden, logout icon centered, and
  logout tooltip overflow visible.
- This status does not alter the balance visibility contract or any financial
  core behavior.

## v3.1 P2 recurrence installments status
- Latest recurrence checkpoint: `/reports/checkpoint_p2_recurrence_installments.md`.
- New migration: `migrations/20240101000006_add_recurrence_installments_v3.php`.
- Counting rule is fixed for this implementation:
  - parent recurring transaction is installment 1/N
  - generated children are installments 2/N through N/N
  - no installment N+1 should be generated
- New nullable schema fields:
  - `transactions.parent_transaction_id`
  - `transactions.installment_index`
  - `transactions.recurrence_total_occurrences`
  - `recurring_generated.installment_index`
- `recurring_generated` remains the idempotency source for generated
  occurrences. `RecurringService` computes consumed installments as
  `1 + COUNT(recurring_generated rows for parent)`.
- Existing `recurrence_end_date` and no-limit recurrence modes must continue
  working; count mode clears the end date, date mode clears the count, and
  never mode clears both.
- Existing generated children are not deleted or rewritten by the migration.
  The migration only backfills deterministic `transactions.parent_transaction_id`
  from `recurring_generated`.
- `RecurringService::nextOccurrence()` and the Jan 31 anchor-day behavior were
  not changed; run `php tests/recurrence_overflow.php` on Hostinger after
  deploying this batch.
- Post-pause hardening on 2026-07-06 fixed a generated-child edit metadata
  preservation issue: `actions/transactions/update.php` detects child rows by
  `transactions.parent_transaction_id`, and `TransactionRepository::update()`
  can now skip updating installment fields for those rows.
- Hetzner hotfix on 2026-07-08:
  - `scripts/migrate.php` strips SQL `--` line comments before semicolon
    splitting so migration comments cannot be executed as SQL fragments.
  - Existing `.sql` migration line comments were normalized to avoid
    semicolons, and `tests/probe_migration_parser.py` now enforces this.
  - `tests/recurrence_overflow.php` now simulates the no-explicit
    `recurrence_day` monthly chain by carrying the parent transaction
    day-of-month anchor, matching `RecurringService::generateForParent()`.
  - Do not run recurring cron on Hetzner until `tests/recurrence_overflow.php`,
    `tests/recurrence_installments.php`, and `tests/smoke.php` pass in the PHP
    container.
- Local validation passed for JS syntax, Python probes, and PHP 7.4 static
  blockers. PHP lint/migration/smoke still need the Hetzner PHP container.

## Dangerous production credentials (must rotate)
- Hardcoded in config/config.php:8-13 — Hostinger u545165688_NovixADM / @#$Novix200505.
- Also in docs/SETUP.md:27. User approved rotation as part of Phase 1 (final step, post env loader); user picks new password out of band.

## Critical bugs to fix (Phase 3 unless dependency forces earlier)
- public/credit-cards.php:380 undefined $totalAmount/$paidAmount
- public/index.php:142 broken image, :222 dead roadmap link
- .htaccess:10 incomplete rewrite whitelist
- actions/transactions/update.php missing category-type validation
- actions/credit-cards/revert-payment.php missing transaction reversion
- CurrencySettingsRepository deprecated VALUES(col) syntax
- includes/helpers.php:16 redirect() missing entries
- Multi-statement mutations not wrapped in DB transactions
- includes/currency.php:19 wrong number format for USD/EUR

## Files destined for /archive (Phase 0)
- test_db.php, public/check-accounts.php, public/install-accounts.php, create.php (root)
- actions/login.php, actions/register.php, actions/logout.php (duplicates of public/)
- database/update-accounts.sql, database/fix_categories.sql, database/add_effective_date.sql
- v1.7 (antes de mudar design completamente)/, v2 (prototipo 1)/, v2.1 (com sidebar correta)/

## Planilhas — 2026-11-04
- Alterações na barra de fórmulas precisam ser confirmadas antes de mudar de célula, criar marco ou sair. Persistência integrada ainda requer validação.

## Operação de planilhas — FPM real 2026-11-05
- Observar proxy e FPM antes das requisições relevantes; Finishing e fila impedem troca. Invalidação deve ocorrer no FPM com prova comportamental. Reload gracioso durante trabalho pós-resposta perdeu 2/2 gravações no laboratório: não usar como prova de drenagem ou bootstrap aprovado. Relatório vigente em reports/sheets_final_audit_and_fpm_2026-11-05.md.

## Primeira transição — protocolo vigente 2026-11-05
- fpm_get_status() consulta o pool existente sem pm.status_path/reload. Excluir somente o PID da sonda validada; saturação é aborto, não ociosidade. Trava temporária no bootstrap + drenagem após invalidação protege contra admissões tardias; manter trava no rollback legado. Ver reports/sheets_first_transition_2026-11-05.md.
