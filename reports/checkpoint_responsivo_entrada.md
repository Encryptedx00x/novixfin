# Checkpoint — Responsividade mobile das telas de ENTRADA

Data: 2026-07-29

## Task
Completar a responsividade mobile nas telas de entrada (Landing, Login, Registro), que têm
estrutura própria (sem shell/sidebar) e ficaram de fora da fatia mobile das 9 telas internas.
Manter consistência com o que já está em produção (breakpoint 768px, alvos >=44px). Parar para
revisão.

## Fonte visual
Mockup "Mobile NovixFin.dc.html" (Design MCP), telas **10 · Entrada · Login** e **10b · Landing
com seletor de setas**. Diretrizes lidas:
- Login: "a cena de cristal vira **faixa superior de 240px** e o formulário ocupa o resto.
  Registro segue igual, só com mais campos."
- Landing: "sem espaço lateral livre, **as setas descem para uma barra sob os CTAs**, flanqueando
  o nome do tema. O carrossel de cards segue rolando de lado." Topbar = logo + hambúrguer.
- Nota geral: "tudo aqui é **o mesmo HTML do desktop com CSS a partir de 768px**. Nenhuma tela
  ganha markup próprio de mobile." — respeitado; a única adição de markup é o botão do
  hambúrguer (um menu exige um controle).

## Entregas

### LANDING (public/index.php, landing.css, landing.js)
- **Nav → hambúrguer**: adicionados ids `#lp-navinner`, `#lp-brandgroup`, `#lp-navlinks`,
  `#lp-navcta` aos grupos que já existiam e um `#lp-navtoggle` como 4º filho do grid
  (`display:none` no desktop, então o grid `1fr auto 1fr` fica intacto lá). Em <=768px o grid
  vira `1fr auto` (logo | hambúrguer) e, com `data-navopen="1"`, links e CTAs aparecem
  empilhados em largura total (46px cada). A etiqueta ao lado do logo some (não cabe).
- **landing.js**: IIFE novo só para o toggle (aria-expanded/label, fecha por Esc, ao clicar em
  âncora e ao voltar para desktop). Não encosta no switcher de tema.
- **Hero empilha**: o hero é `<header data-screen-label="hero">`, não `<section>` — a regra
  genérica da fatia anterior (`section[data-screen-label] ...`) **não o cobria**. Agora a regra
  usa `[data-screen-label]`, então hero e seções empilham. Headline em
  `clamp(30px, 8.6vw, 42px)`, subtítulo 15.5px, CTAs em coluna com 52px de altura, mock sem
  `perspective/tilt` para não estourar.
- **Seletor de tema (hero)**: as setas já viravam `position:static` em 1280px (caem na linha do
  `#lp-hero-label`, flanqueando o nome — exatamente o padrão do mockup). Aqui elas passam a
  48px com halo 44px e ícone 26px, e a barra ganha respiro.
- **Seção "15 temas"**: setas de 64px → 48px; o bloco central perde o `min-width: min(430px,52vw)`
  (vira 0) e o nome do tema cai para 26px, para não comprimir. O carrossel `.lp-track` continua
  dirigido pelas setas (transform + JS), como no desktop — nada alterado nele.
- **Cena de cristal**: `[data-crys]`, `[data-crys-abstract]`, `[data-marble]` e as regmarks são
  **ocultadas em <=768px**. São backgrounds com `position:absolute` e offsets negativos
  calibrados para telas largas (ex.: `left:-102px; width:448px`) — no estreito vazariam. O
  mockup mobile também não mostra a cena na hero. **Decisão reportada**: no mobile a hero fica
  tipográfica; nenhum arquivo de imagem foi removido, só não é exibido.

### LOGIN e REGISTRO (public/styles/entrada.css)
Os dois compartilham `#lg-shell`, o partial `novix_entrada_brand()` e as classes `.lg-*`, então
um único bloco cobre ambos (registro só tem mais campos).
- `#lg-brand` (faixa de cristal): `height: 240px` fixo com `justify-content:flex-end`, conforme
  o mockup — antes tinha `min-height:340px` (em 900px), que empurrava o formulário para fora da
  dobra.
- `#lg-gemwrap`: contido em 210x230 no canto inferior, `transform:none` — não vaza.
- `#lg-formcol`: alinhado ao topo, padding 26/18 com `env(safe-area-inset-bottom)`; `.lg-form`
  em largura total; `.lg-title` 26px.
- Toque: `.lg-input` 50px e **16px de fonte** (evita zoom no iOS), `.lg-btn` 54px, demais
  botões/links >=44px. O foco visível já existia (`#lg-formcol input:focus`).

## Preservado (nada de lógica tocada)
Switcher de tema (View Transitions + `localStorage['novixfin-theme']`), herança de tema pré-auth,
handlers de login/registro, CSRF, `.lp-track`/JS do carrossel, todos os names/ids existentes.

## Files changed
- public/index.php (só ids nos grupos do nav + botão do hambúrguer)
- public/scripts/landing.js (toggle do menu, apenso)
- public/styles/landing.css, public/styles/entrada.css

## Risks
- **Cristal oculto no mobile**: decisão de layout, reversível numa linha se você preferir
  mantê-lo reduzido em vez de oculto.
- Overrides com `!important`: necessários porque a landing declara grades/paddings em estilo
  inline. Todos dentro de `@media (max-width:768px)` — desktop intacto.
- Nav: o painel depende do JS; sem ele o hambúrguer não abre (links seguem acessíveis pelo
  rodapé/âncoras da página).

## Validation performed
- CSS: landing.css 152/152 e entrada.css 135/135 chaves; blocos novos verificados em
  **profundidade 0** (não aninhados dentro de outro @media).
- `node --check` OK: landing.js, shell.js.
- Estrutura PHP balanceada: index.php (div 421/421, button 12/12, php 11/11), login.php,
  register.php.
- `php -l` local indisponível.

## Remaining work (servidor, ~390px)
- `php -l public/index.php public/login.php public/register.php`.
- Landing: hero empilha, headline não estoura, CTAs full-width, nav vira hambúrguer (abre/fecha,
  Esc, âncora), setas do tema flanqueiam o nome e **trocam o tema no toque**, "15 temas"
  utilizável, cristal não vaza.
- Login/Registro: faixa de cristal 240px no topo + formulário completo abaixo (visível sem
  rolar demais), campos e botão confortáveis, foco visível.
- Tema claro (ametista) e escuro (tinta). **Desktop intacto acima de 768px.**

## Confidence
82% — padrões tirados direto do mockup e aplicados só em media queries; a confiança sobe após o
passe visual, principalmente no nav-hambúrguer e nas setas do seletor no toque.
