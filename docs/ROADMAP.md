# Roadmap NovixFin

> v3 update: the previously "placeholder" items — recurring transactions,
> gamification writes, exports/imports, a real schema migration runner, env
> loader, ACID transactions, dashboard-insights-as-a-service — all shipped in
> v3. This file now reflects what's still genuinely upcoming.

## MVP (entregue, v1)
- Autenticação segura (Argon2id, throttle, CSRF).
- Dashboard com gráficos, histórico e cards animados.
- CRUD de transações e metas financeiras.
- Placeholder de insights IA, conquistas e níveis.

## v3 (entregue em sessão GSD)
- Env loader real (`.env`) + credenciais hardcoded removidas; galhardassem concommecânica de rotação documentada.
- Migration runner ordenado e idempotente (`scripts/migrate.php`) + schema canonical em `/migrations/`.
- Transações de banco envolvendo multi-mutações (`TransactionRepository::create`, `pay-bill`) → ACID.
- Reversão de pagamento agora reverte também as transações automáticas geradas (despesa da fatura, débitos, limite como receita).
- Insights extraídos para `src/Services/InsightService` (regra-base, prontos para troca por IA real).
- Transações recorrentes são agora MATERIALIZADAS pelo cron (`src/Services/RecurringService` + `scripts/cron.php`); o badge "Fixa" finalmente cumpre o prometido.
- Gamificação escreve pontos e badge no DB (`src/Services/GamificationService` integrado em transações e pagamentos); Profile agora mostra progresso real.
- Exportação (CSV + JSON) e importação (CSV com preview) via `public/export.php` + `public/import.php`.
- Bugs de produzção corrigidos: `.htaccess` whitelist, `redirect()` helper, formatting de moeda por currency, `credit-cards.php:380` undefined vars, `CurrencySettingsRepository` deprecated `VALUES()`, `update.php` validação de tipo/propriedade de categoria, broken image + dead link no `index.php`.
- Remoção do hack de supressão do `console.warn` do Tailwind CDN.
- Dead-code e scripts de depuração perigosos arquivados (`test_db.php`, `check-accounts.php`, `install-accounts.php`, snapshot folders v1.7/v2/v2.1).
- CLI-only guard em `actions/transactions/auto-effective.php`.

## Próximas fases (curto prazo)

### Manutenção / hardening
- Remover as probes defensivas `SHOW COLUMNS`/`SHOW TABLES` (Phase 3 Batch C, deferida — só ativar quando as migrations estiverem confirmadas em produção).
- Decompor `public/dashboard.php` (~2135 linhas) em serviços + partials — ainda monolítico.
- Wire `goal_completed` (gamification) no hook de ações de metas.
- Tailwind CDN → bundle local (`npx tailwindcss-cli` ou build de CI).
- Hook gamification em completar-metas e em `month_with_spending_limit_held`.
- Testes automatizados (PHPUnit) + pipeline CI (GitHub Actions).
- Monitoramento: Sentry, logs estruturados, métricas de erro.

### Features curto prazo
- Integração real com APIs de previsão (OpenAI, Vertex AI ou HuggingFace) — substituir o corpo de `InsightService::forUser`.
- Gamificação social: ranking entre amigos, desafios semanais.
- Exportar PDF (além de CSV/JSON) e importar OFX / extratos bancários brasileiros.
- Sync cross-device do kanban (atualmente localStorage-only).## Médio prazo
- Aplicativo mobile (React Native) consumindo uma API REST a ser criada.
- Webhooks para alertas em tempo real (gastos incomuns).
- Machine learning supervisionado para detecção de anomalias.
- Módulo de investimentos, carteira consolidada e simulações.
- Internacionalização (pt-BR, en-US) — multi-moeda já está.

## Longo prazo
- Marketplace de parceiros (descontos, cashback).
- Open Banking: sincronização automática de contas e cartões.
- Assistente conversacional com IA generativa.
- Engine de recomendação personalizada de produtos financeiros.

