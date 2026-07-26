# Checkpoint — Landing (textos por tema) + fatia Dashboard (tokenizada)

Date: 2026-07-23

## 0) Correção na Landing (bug de fidelidade)

**Diagnóstico honesto:** a causa NÃO era falta de lógica reativa. `landing.js` já
tinha a tabela dos 15 temas (id, name, tag, h1a, h1acc, h1post, sub, fonts) e
`renderTheme()` já atualizava headline, subtítulo, rótulo e o "tema ativo: X" do rodapé,
tudo dentro do `startViewTransition`. O defeito real era **um único hook HTML malformado**
em `public/index.php` (linha 144): a atributo `data-lp="selName"` tinha caído dentro da
tag de FECHAMENTO `</span data-lp="selName">` (artefato do script de transformação da
fatia Landing). Resultado: o texto "Ametista" da hero não estava dentro de um elemento
`[data-lp="selName"]`, então `lpSet('selName')` não achava nada e o rótulo congelava.

**Correção:** troquei por `<span data-lp="selName">Ametista</span>` bem-formado. Verifiquei
que não há nenhum outro hook malformado (`grep '</… data-lp'` → nenhum) e confirmei que a
tabela tem 15 headlines DISTINTOS (ametista "Bruto entra. Lapidado fica.", cafe "Dinheiro
claro, cabeça leve.", etc.). headline/subtítulo/rodapé já funcionavam — só o rótulo estava
quebrado. `node --check` OK.

## 1) Dashboard — estratégia (a maior fatia: 2256 linhas)

`public/dashboard.php` concentra os 3 forms financeiros (create/update/delete), o form de
recorrência completo e o módulo de faturas de cartão. Reescrever todo o markup traria risco
alto e desproporcional para esses forms. Decisão: **tokenizar por override escopado**, não
reescrever o markup.

- NEW `public/styles/dashboard.css` — mapeia as utilitárias Tailwind (slate/violet/
  emerald/rose/etc.) para os tokens dos 15 temas, **100% escopado sob `.dash-screen`**
  (verificado: 0 seletores fora de `.dash-screen`/`.dash-fab`). Zero markup de formulário,
  id, data-attr ou JS tocado.
- **Especificidade:** o `themes.css` legado ainda veste essas mesmas classes para
  dark/black/light com `!important` (via `body[data-theme=...]`, e o `<body>` carrega o
  valor legado). Para vencer de forma escopada usei o prefixo `html .dash-screen` (mesma
  especificidade, e `dashboard.css` carrega depois via `$pageStyles`) + `!important` só nas
  cores. Comentado no arquivo; removível quando as utilitárias saírem do markup.
- Markup tocado (mínimo, sem lógica): `.dash-screen` na `<section>` e nos 3 `<dialog>`;
  `id="dash-create"` no card de registrar transação (âncora do FAB); **FAB** de lançamento
  adicionado (usa `filter: drop-shadow`, não `box-shadow`, porque o `clip-path` do tema
  recortaria a sombra — item 3).
- **Preservado e verificado por script:** os 3 forms + CSRF; todos os `getElementById`
  de dashboard.js e kanban.js presentes no PHP; os 5 canvas de gráfico
  (chartCategories/Monthly/Forecast/ExpensesByAccount/IncomesByAccount); tags balanceadas
  (section 1/1, dialog 3/3, form 6/6).
- Item 3 (qualidade): `[data-balance]` recebe `--f-num` + tabular-nums (dígitos tabulares
  em todo valor monetário de graça, sem editar cada span); `focus-visible` com anel
  `--accent`; kanban vira `overflow-x` com largura mínima de coluna no mobile (≤900px);
  texto pequeno colorido usa `--accent-text`.

## 2) Gráficos e JS seguindo os tokens (ponto crítico)

**Achado (item 2 — verificação de leitura de data-theme):**
- `kanban.js`: **não lê data-theme** e **não tem cores fixas** — é 100% CSS/token. Nenhuma
  mudança necessária.
- `dashboard.js`: a função `getDashboardChartTheme()` lia `data-legacy-theme` (ajuste da
  fatia Perfil) mas tinha **~94 cores fixas** (violeta/emerald/rose/amber/slate) espalhadas
  pelos gráficos Chart.js.
- `forecast-chart.js`: **~41 cores fixas** idem.

**Correção:**
- NEW `public/scripts/chart-tokens.js` — expõe `window.NVXCharts.palette()`, que lê os
  tokens dos 15 temas em runtime (`getComputedStyle` no `<html>`) e devolve cores
  concretas (canvas não resolve `var()`/`color-mix`), com helper de alpha `.a(x)`.
- `dashboard.js` e `forecast-chart.js`: paleta `P` resolvida no build de cada gráfico;
  todas as cores fixas trocadas por `P.accent/.pos/.neg/.gold/.muted/.ink/.surface` com o
  alpha original. Cada tema carrega com as cores certas. Como a troca de tema acontece por
  reload (POST no Perfil), correto-no-load cobre 100%; forecast-chart relê a paleta a cada
  rebuild, então também segue troca em runtime se houver.
- Mapeamentos notáveis: tooltip bg → `ink`, texto do tooltip/pontos → `surface`, borda entre
  fatias da pizza → `surface`, grid → `muted` alpha, rampas de vermelho/verde das pizzas de
  conta → `neg`/`pos` com alphas graduados, categorias → accent/pos/accent2/gold/muted/neg.
- `chart-tokens.js` entra no `$pageScripts` ANTES dos outros.
- **Bug pego e corrigido:** o script de transformação reescreveu o `'#cbd5f5'` que eu havia
  usado como fallback DENTRO do próprio objeto de fallback, criando uma auto-referência
  circular (`muted: { hex: P.muted.hex }`). Troquei por literal concreto `'#8891a8'` nos
  dois arquivos. `node --check` OK em todos.

## Files changed
- `public/index.php` — 1 linha (hook `selName` bem-formado).
- NEW `public/scripts/chart-tokens.js`.
- `public/scripts/dashboard.js` — paleta + ~94 cores → tokens (fallback corrigido).
- `public/scripts/forecast-chart.js` — paleta + ~41 cores → tokens (fallback corrigido).
- NEW `public/styles/dashboard.css` — override escopado `.dash-screen` + FAB.
- `public/dashboard.php` — `$pageStyles`; `.dash-screen` na section e 3 dialogs;
  `id="dash-create"`; FAB; `chart-tokens.js` no `$pageScripts`. Nenhuma lógica/for m tocada.

## Validation
- `node --check`: landing.js, chart-tokens.js, dashboard.js, forecast-chart.js, kanban.js — todos OK.
- 3 forms + CSRF preservados; hooks de dashboard.js e kanban.js completos; 5 canvas presentes.
- dashboard.css 100% escopado; `$pageStyles` antes do header; sem literais de cor fora dos fallbacks.
- **PHP local indisponível** — rodar no servidor: `php -l public/dashboard.php public/index.php`
  e `php tests/smoke.php`.
- Sem migração. Ponte `data-legacy-theme`/`theme-compat.css`/`NOVIX_THEME_LEGACY` intacta.

## Riscos / limites (transparência)
- O reskin do dashboard é por **override de tokens**, não uma re-diagramação estrutural.
  Entrega os 15 temas com cor/tipografia/chips/FAB corretos e risco ~zero para os forms,
  mas o layout dos cards permanece o do PHP atual (não o layout exato do design). Uma
  re-diagramação estrutural pode ser uma fatia futura dedicada, se desejado.
- Override usa `!important` escopado para vencer o `themes.css` legado — dívida ligada à
  mesma ponte de compatibilidade; some quando as telas internas largarem as utilitárias.
- Casos de cor não cobertos pelos mapeamentos podem aparecer no review visual e são
  ajustes pontuais no dashboard.css (escopado, sem risco).

## Remaining
- Teste manual no servidor (ver TESTE no progress).
- Confidence: 84% (pendente `php -l` + verificação visual em um tema claro e um escuro).
