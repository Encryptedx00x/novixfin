# Checkpoint — Tokenização dos modais (componente compartilhado nvx-modal)

Data: 2026-07-26

## Task
Tokenizar os modais ainda no visual antigo (card branco chapado, inputs sem estilo, sem --cut).
Sintoma reportado: "Novo lançamento" do dashboard aparece branco mesmo em tema claro. Varrer
todas as telas. Um único estilo de modal reutilizado. Parar para revisão.

## Diagnóstico (antes de editar)
Modais por tela e estado:
- **history**: edit + delete x3 — JÁ tokenizados (.hist-modal / .hist-input) = referência.
- **cat / goals / wallets**: JÁ corretos — tinham `dialog.X-modal { background: var(--surface) }`.
- **dashboard (4), accounts (2), credit-cards (2)**: QUEBRADOS.

Causa raiz: os mapas por-tela usam combinador DESCENDENTE (`.dash-screen .bg-slate-900\/90`),
que não atinge o próprio `<dialog>` (mesmo elemento carrega `.dash-screen` + `.bg-slate-900/90`).
O container ficava sem superfície/borda/--cut; `.dash-screen{background:var(--bg)}` vencia ->
quase branco no tema claro; inputs (`.dash-screen input`) com --bg sobre --bg, baixo contraste.

## Solução (unificada)
NEW `public/styles/modal.css` — componente global `dialog.nvx-modal`, modelado no `.hist-modal`
que já funcionava:
- Container: `background: var(--surface)`, `border: var(--card-b)`, sombra, `border-radius:
  calc(var(--r)+10px)`; `::backdrop` tema-aware (color-mix ink 45% + blur).
- Campos: input/select/textarea com `--bg`/`--line`/`--ink`, radius `--r-chip`, foco `--accent`
  (focus-visible + :focus); checkbox/radio `accent-color: var(--accent)`; number/date em `--f-num`.
- Mapa Tailwind->token escopado ao modal: violet->accent (botão primário com `clip-path: var(--cut)`),
  rose->neg (botão de perigo com --cut, preserva a semântica de exclusão), slate->surface,
  emerald->pos, text-white->ink, border-white->line, rounded->raio do tema.
- Especificidade `dialog.nvx-modal` (0,1,1) vence as utilitárias Tailwind (0,1,0). SEM !important
  (o themes.css legado que exigia !important foi removido na fatia anterior).
- Carregado globalmente em `includes/layout/header.php` (após shell.css), então um só vestido
  serve todas as telas.

## Files changed
- **NEW** public/styles/modal.css
- **M** includes/layout/header.php — `<link>` do modal.css.
- **M** public/dashboard.php — `nvx-modal` em 4 <dialog>.
- **M** public/accounts.php — `nvx-modal` em 2 <dialog>.
- **M** public/credit-cards.php — `nvx-modal` em 2 <dialog>.
- **M** public/categories.php, public/goals.php, public/wallets.php — `nvx-modal` em 1 <dialog>
  cada (unifica sob o mesmo componente; já estavam corretos, agora redundantes mas consistentes).

## Summary
Um componente de modal para o app inteiro. Todos herdam o tema atual (corretos nos 15 temas,
claro e escuro): superfície do tema, inputs com contraste, botão primário accent com canto --cut,
backdrop tema-aware. Nenhuma lógica tocada — só a classe `nvx-modal` no `<dialog>`.

## Risks
- Baixo. Só atributos `class` alterados no PHP; forms/ids/names/CSRF/handlers/confirmações intactos.
- Sobreposição com os mapas de modal por-tela (.cat-modal etc.): mesmos tokens, sem conflito de
  resultado; ficam como dívida para uma limpeza futura.
- history NÃO foi re-mexido (modal de recorrência financeiramente sensível, já tokenizado).

## Validation performed
- modal.css: chaves balanceadas (33/33).
- 11/11 <dialog> com `nvx-modal` (grep).
- `php -l` local indisponível.

## Remaining work (servidor / usuário)
- `php -l` nos 6 .php + header.php.
- Passe visual: abrir cada modal em **ametista** (claro) e **tinta** (escuro) — veste o tema
  (não branco), inputs e botão corretos, cobre a sidebar, Esc/clicar-fora fecham, criar/editar/
  excluir funcionam, confirmação de exclusão intacta. Foco no "Novo lançamento" do dashboard.
- Limpeza futura: remover os mapas de modal por-tela redundantes.

## Confidence
86% — causa raiz isolada e corrigida na especificidade certa; pende `php -l` e o passe visual.
