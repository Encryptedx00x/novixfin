# Checkpoint — Remoção do ANDAIME de compatibilidade de tema

Data: 2026-07-26

## Task
Remover o andaime de compatibilidade legada de tema, agora que as 10 telas internas estão
tokenizadas (classe `.*-screen` + tokens). Remoção guiada por verificação, não por suposição.
Parar para revisão.

## Diagnóstico (grep no repo real, ANTES de remover)
Consumidores ativos do andaime — todos tratados:
- **themes.css / theme-compat.css**: único loader ativo era `includes/layout/header.php`
  (l.57, l.62). (Demais menções: COMENTÁRIOS em CSS por-tela e docs — inertes.)
- **data-legacy-theme**: emitido no `<html>` (header.php); lido por `chart-tokens.js:59` e
  `dashboard.js:12`.
- **data-theme legado + data-novix-theme** no `<body>` (header.php) — casavam só os seletores
  `body[data-theme="light|dark"]` do themes.css legado.
- **NOVIX_THEME_LEGACY / novix_legacy_theme()** em `includes/themes.php`.
Conclusão: fora do próprio andaime, ZERO consumidor. Removível.

## Files changed
- **M** includes/layout/header.php — removidos os 2 `<link>` legados; `<html data-theme=slug>`
  (sem data-legacy-theme); `<body data-theme=slug>` (sem data-theme legado nem data-novix-theme);
  classes `bg-slate-950 text-slate-100` removidas do body; `$legacyTheme` eliminado.
- **M** includes/themes.php — removidos `NOVIX_THEME_LEGACY` e `novix_legacy_theme()`.
- **M** public/styles/shell.css — nova regra `html, body { background: var(--bg); color: var(--ink); }`
  (repõe o fundo do antigo theme-compat.css, agora por token).
- **M** public/scripts/chart-tokens.js — `palette()` decide isLight pela luminância de `--bg`.
- **M** public/scripts/dashboard.js — fallback isLight idem (getComputedStyle de `--bg`).
- **D** public/styles/themes.css (819 linhas legadas dark/black/light).
- **D** public/styles/theme-compat.css (25 linhas).

## Summary
`data-theme` volta a ser só o slug. O app não infere mais claro/escuro por nome de tema legado:
os gráficos leem a luminância do token real `--bg` em runtime, então funcionam nos 15 temas sem
lista fixa. O fundo da página fora do `#nvx-shell` (overscroll) segue o tema via `var(--bg)`.

## Risks
- **Ponto crítico**: gráficos. Se a leitura de `--bg` falhasse, `isLight` erraria e as cores de
  eixo/legenda perderiam contraste. Mitigado: mesmo critério de luminância nos dois pontos;
  fallback defensivo no dashboard.js; `--bg` existe em todos os 15 temas (tokens.css).
- Body perdeu `bg-slate-950 text-slate-100`: intencional — conteúdo vive dentro do `#nvx-shell`
  (que já define bg/ink por token); os fixos escuros quebrariam o tema claro sem o themes.css.

## Validation performed
- `node --check` OK: chart-tokens.js, dashboard.js.
- Grep pós-remoção: 0 consumidores de data-legacy-theme/NOVIX_THEME_LEGACY/novix_legacy_theme/
  themes.css/theme-compat em .php/.js de public/includes/actions/src.
- `php -l` local indisponível.

## Remaining work (servidor / usuário)
- `php -l includes/themes.php includes/layout/header.php`.
- Passe visual: 10 telas em **ametista** (claro) e **tinta** (escuro) — nada perdeu cor/contraste,
  nada caiu para visual base, e os GRÁFICOS seguem os tokens (teste-chave).

## Confidence
88% — remoção provada órfã por grep, migração de JS robusta por luminância; pende `php -l` e o
passe visual dos gráficos no servidor.
