# Checkpoint — Auditoria completa + correção da landing no mobile

Data: 2026-07-29

## Task
Parar de consertar seção por seção: percorrer a landing inteira em ~390px, catalogar TODOS os
defeitos (Fase 1) e corrigir num diff só (Fase 2), sem reescrever a landing. Verificar também
login/registro. Parar para revisão.

---

# FASE 1 — AUDITORIA

Varredura programática do `public/index.php` (17 blocos de primeiro nível), assumindo viewport de
390px = **354px úteis** com 18px de respiro lateral. **81 ocorrências** em 6 categorias.

| Categoria | O que é | Ocorrências |
|---|---|---|
| `padding-desktop` | `padding: …32px…` (respiro lateral/vertical de desktop) | 20 |
| `grid-fixo` | `grid-template-columns` com fr/px fixos | 23 |
| `fonte-grande` | `font-size` inline de 30–44px, sem clamp | 13 |
| `width-fixa` | `width:` fixo maior que 354px | 9 |
| `abs-negativo` | `position:absolute` com offsets negativos | 16 |
| `clip-path-px` | `clip-path: polygon(...)` com px | 1 |

## Por seção (defeitos concretos)

1. **nav** — grid `1fr auto 1fr`, padding `0 32px`. → **já corrigido** (vira `1fr auto`, 16px).
2. **hero** — abstratos de 560/480px com offsets negativos; `h1` com `clamp(46px,…)` (mínimo alto
   demais); **`font-size:44px` no número do mock**; grid `1.06fr 1fr`; paddings `92px 32px`,
   `17px 32px` (CTA), `30px 32px` (card do mock); **`[data-mockwrap]` com 98px de padding
   inferior** (o "vão grande" relatado). Parcialmente corrigido antes; **restavam** o 44px, o
   padding do card e o vão.
3. **problema-solucao** — grid `repeat(3,1fr)`, padding `112px 32px 96px`. → já corrigido.
4. **demo 3s** — **4× `font-size:30px`**; **card com `width:420px`** (estoura); grid `1fr 1fr`;
   padding `108px 32px`; abs `bottom:-18px`.
5. **previsão estrela** — **`font-size:40px`**; grid `1fr 1.15fr`; 5 offsets `left:-34px`.
6. **tour de produto** — **`font-size:34px` e `32px`**; 4 grids fixos; padding `108px 32px`.
7. **features** — padding `112px 32px`. → já corrigido.
8. **antes-depois** — **`font-size:32px`**; grid `1fr 1fr`; padding `108px 32px`.
9. **15 temas switcher** — padding `112px 32px`; seletor/carrossel já tratados. (O "bloco chapado"
   relatado era, na verdade, o **cta final** — ver #15.)
10. **gamificação** — **2 cards com `width:380px`** (estouram); grid `1fr 1fr`.
11. **comparativo** — 8 grids `1.6fr 1fr 1fr 1fr`. **Exceção assumida**: mantém 4 colunas
    comprimidas (as colunas SÃO a comparação; sem rótulo por célula, empilhar perde o sentido).
12. **segurança** — **`font-size:34px`**; grid `1.1fr 1fr`.
13. **prova social** — **4× `font-size:44px`** (números de destaque); grids `repeat(4,1fr)` e
    `repeat(3,1fr)`.
14. **faq** — padding `108px 32px`. → já corrigido.
15. **cta final** — decorativos de 520/460px com offsets negativos; **`clip-path: polygon(0 46px,…)`**
    (diagonal íngreme no estreito); padding interno `132px 32px 116px` → era o **"bloco de cor
    chapado"**; CTAs com o mesmo corte assimétrico.
16. **footer** — grid `1.4fr 1fr 1fr 1fr`; paddings `56px 32px 40px` e `0 32px 32px`.
17. **esteira (credibilidade)** — `width:max-content` com marquee: **correto**, rola de propósito.

## Defeitos conhecidos — confirmados
- **Botão "Criar conta grátis"**: `clip-path: var(--cut)` = `polygon(0 0, calc(100% - 14px) 0,
  100% 14px, 100% 100%, 0 100%)` — recorta **só o canto superior direito**. Em botão de largura
  total isso lê como assimetria/defeito. **CONFIRMADO.**
- **Espaçamento hero → CTAs → mock**: `[data-mockwrap] { padding: 18px 18px 98px }`. **CONFIRMADO.**
- **"Escolher meu tema" como bloco chapado**: é a seção **cta final** (não a de temas).
  **CONFIRMADO** (corrigido na fatia anterior).
- **Hambúrguer**: corrigido na fatia anterior (script inline após o `<nav>` + cache-busting
  `?v=filemtime()`); presença confirmada neste diff.

---

# FASE 2 — CORREÇÃO (um diff, regras genéricas)

1. **Guarda geral de largura** — em vez de consertar card a card:
   `[data-screen-label] *:not(.lp-track):not([data-mq-track]) { max-width: 100% }`
   + `width:100% !important` nos dois tamanhos fixos (420px/380px). As duas faixas que rolam de
   propósito (carrossel de temas e esteira) ficam de fora.
2. **Tipografia** — um clamp por tamanho inline, cobrindo as 13 ocorrências de 7 seções:
   44px→clamp(26,7.4vw,32) · 40px→clamp(25,7vw,30) · 34px→clamp(23,6.4vw,28) ·
   32px→clamp(22,6.2vw,27) · 30px→clamp(21,6vw,26). (`h1`/`h2` já tinham clamp próprio.)
3. **CTAs** — todos os `a`/`button` com `clip-path: var(--cut)` recebem `clip-path:none`
   (simétrico, fica o `border-radius` do tema), padding lateral 20px e 52px de altura.
4. **Ritmo vertical** — `[data-mockwrap]` 98px → 22px; `[data-card="1"]` com padding 18px.
   As seções já tinham ritmo único (56px topo / 52px base) da fatia anterior.
5. **Diagonal do cta final** — `polygon(0 46px,…)` → `polygon(0 18px,…)`.

## LOGIN / REGISTRO — verificado
A estrutura da fatia anterior estava certa (faixa 240px + form abaixo), **mas havia um defeito
real**: `.lg-headline` usa `clamp(34px, 3.9vw, 52px)` — mínimo de 34px. Em 3 linhas (ex.: "De
volta ao seu mês lapidado.") a copy estouraria a faixa curta de 240px.
**Corrigido**: headline `clamp(22px, 6.6vw, 27px)` e subline 13px (tamanhos do mockup), com os
estilhaços decorativos (`.lg-shard`) ocultos para não poluir a faixa.
Conferência de altura: faixa 240 + form ≈ 504px (título 26 + 4 campos de 50 + gaps + botão 54 +
rodapé + paddings) ≈ **744px**, cabe em 390×844 sem rolagem; em telas menores rola normalmente.
Registro usa o mesmo shell/partial/classes — vale para os dois.

## Preservado (inegociável)
Switcher de tema (View Transitions + `localStorage['novixfin-theme']`), herança pré-auth,
handlers, CSRF, carrossel `.lp-track` e a esteira. Nenhuma reescrita: só CSS em
`@media (max-width:768px)`. **Desktop acima de 768px inalterado.**

## Files changed
- public/styles/landing.css — guarda de largura, clamps, CTAs, ritmo, diagonal.
- public/styles/entrada.css — headline/subline da faixa, shards ocultos.

## Validation performed
- landing.css 167/167 e entrada.css 139/139 chaves; bloco novo na profundidade 1 (dentro do
  `@media` correto).
- `node --check` OK: landing.js. index.php balanceado (php 13/13, div 421/421, script 3/3).
- `php -l` local indisponível.

## Remaining work (servidor, ~390px)
- `php -l public/index.php public/login.php public/register.php`.
- Rolar a landing inteira: nenhuma seção quebrada, ritmo uniforme, números/títulos legíveis,
  cards sem estourar, CTA "Criar conta grátis" com canto simétrico, hambúrguer e seletor de tema
  funcionais. Login/registro utilizáveis. Tema claro e escuro. **Desktop intacto acima de 768px.**

## Confidence
85% — a auditoria foi exaustiva (81 ocorrências catalogadas) e as correções são genéricas por
categoria, não pontuais; o que resta é a confirmação visual real.
