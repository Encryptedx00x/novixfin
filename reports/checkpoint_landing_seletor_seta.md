# Checkpoint — Landing "Seletor Seta" (15 temas tokenizados)

Date: 2026-07-22

## Task
Substituir a landing antiga (Tailwind/Poppins/slate) pela landing nova tokenizada,
variante "Seletor Seta" (design aprovado no Claude Design). Página pública; nenhum
arquivo do core financeiro ou de auth tocado (apenas leitura de isAuthenticated()).

## Assets (decisão: imagens commitadas, não CSS puro)
- `public/assets/quartzo.png` (391 KB) — cluster de pontas de cristal (hero, esquerda)
- `public/assets/cristaldireito.png` (478 KB) — cristal facetado (hero, direita)
- `public/assets/marmore-trim.png` (505 KB) — tira de mármore (base do mock)
  extraída byte a byte do base64 do arquivo de referência do design.
- `public/assets/marmore.png` (196 KB) — movido junto na preparação, mas NÃO é
  referenciado pela landing (o design usa a tira "trim"). Mantido por ora.
- Verificação: quartzo/cristaldireito idênticos (Buffer.equals) aos base64 embutidos
  na referência. Otimização de PNG não aplicada (sem ferramenta local; não trivial).

## Files changed
- `public/index.php` — reescrito: porte fiel do markup do design (nav, hero com
  setas grandes sem fundo, esteira de credibilidade, problema→solução, demo 3s,
  previsão-estrela com scrub, tour de produto (5 tabs), features, antes/depois,
  seção "15 jeitos" (seletor + carrossel acoplado clicável), gamificação,
  comparativo, segurança (olhinho), prova social, FAQ, CTA final, footer).
  Tailwind CDN e main.css REMOVIDOS da landing. Pre-paint theme snippet com
  whitelist de includes/themes.php (mesma chave localStorage['novixfin-theme']).
  CTAs: se isAuthenticated() → "Abrir app" → dashboard.php (item opcional feito).
- NEW `public/styles/landing.css` — tokens dos 15 temas (variante landing, com
  --prism/--disp-filete/--disp-regmarks e escopo "Ametista v5 Cristal & Mármore"
  apontando para /public/assets/), keyframes, CSS do seletor/View Transitions e
  classes de hover geradas do design ([data-hv]).
- NEW `public/scripts/landing.js` — porte da lógica do design para JS vanilla:
  fonte única de estado do tema (setas hero + seletor da seção + carrossel em
  sincronia), goTheme com document.startViewTransition (fallback direto;
  prefers-reduced-motion sem morph), validação contra os 15 slugs, gravação em
  localStorage['novixfin-theme'], carrossel com 3 cópias e wrap invisível,
  pré-carga de fontes por tema, tabs do tour, demo automática, olhinho de
  privacidade, contadores, reveals, parallax, tilt, shimmer, kinetic, scrub da
  previsão e modo "Apresentar" completo.
- `public/assets/` — ver acima. `index.php` da raiz (redirect) INTOCADO.

## Decisões
- CSS da landing em arquivo próprio (não fundido com entrada.css): os tokens da
  landing têm variáveis extras (--prism/--disp-*) e o escopo ametista v5; fundir
  agora arriscaria regressão na Entrada já entregue. Unificação em tokens.css
  comum fica como follow-up de refactor consciente.
- Hold-to-repeat das setas (prop segurarSeta do design) não portado: o default do
  design é false/desligado.
- Arquivos de referência `land_seta_clean.html` e `NovixFin - Landing Seletor
  Seta.html` continuam na raiz do repo (anexados pelo usuário) — decidir se
  entram no commit ou vão para /archive.

## Validation
- `node --check public/scripts/landing.js` → OK.
- index.php: 0 placeholders `{{ }}` restantes; tags PHP balanceadas (11/11);
  hooks presentes (setas hero ×2, present, lp-track, 5 tab panels, âncoras
  temas/faq, data-marble/data-crys, marquee).
- PHP local indisponível — rodar no servidor:
  `php -l public/index.php` e teste manual do fluxo de tema
  (landing → login/registro herdando localStorage['novixfin-theme']).
- Sem migração. Sem mudança de schema.

## Riscos
- Página usa color-mix()/clip-path/View Transitions — degrada com fallback em
  navegadores antigos (VT: troca direta).
- PNGs somam ~1.4 MB; otimização (pngquant/squoosh) recomendada depois.

## Remaining
- Revisão manual no servidor (tema sincronizado setas↔carrossel, mobile).
- Confidence: 84% (pendente php -l no servidor + browser pass).
