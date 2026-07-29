# Checkpoint — Correção da landing mobile (seções + hambúrguer)

Data: 2026-07-29

## Task
A fatia anterior (0b52c50) consertou só o hero da landing: outras seções seguiam com layout
desktop espremido no mobile e o hambúrguer não abria. Corrigir de forma consistente (não seção
por seção) e diagnosticar o bug do menu. Parar para revisão.

## LEVANTAMENTO (pedido: listar TODOS os blocos de primeiro nível)
17 blocos, todos com `data-screen-label`:

| # | tag | label | padrão |
|---|-----|-------|--------|
| 1 | nav | nav | B (inner `#lp-navinner`) |
| 2 | header | hero | B |
| 3 | section | credibilidade | B |
| 4 | section | problema-solucao | **A** |
| 5 | section | demo 3s (#demo) | B |
| 6 | section | previsão estrela (#previsao) | **A** |
| 7 | section | tour de produto | B |
| 8 | section | features | **A** |
| 9 | section | antes-depois | B |
| 10 | section | 15 temas switcher (#temas) | **A** |
| 11 | section | gamificação | B |
| 12 | section | comparativo | **A** |
| 13 | section | segurança (#seguranca) | B |
| 14 | section | prova social | B |
| 15 | section | faq (#faq) | B |
| 16 | section | cta final | B |
| 17 | footer | footer | B |

- **Padrão A** (5 blocos): a própria seção tem `max-width:1220px; margin:0 auto; padding:112px 32px`.
- **Padrão B** (12 blocos): a seção é full-bleed (fundo/bordas) e o padding está num **DIV
  INTERNO** com `max-width` + `margin:0 auto`.

**Causa do problema**: a regra da fatia anterior era `section[data-screen-label] { padding... }`
— só acertava o padrão A, e nem cobria `nav`/`header`/`footer`. Todo o padrão B (12 blocos,
incluindo "cta final", footer, demo, comparativo, segurança, faq) manteve `padding: 112–132px
32px` no mobile → conteúdo espremido dentro de um bloco alto de cor sólida. O **"Escolher meu
tema"** que o usuário citou está na seção **cta final** (fundo `var(--stage)`, padding 132px no
div interno) — não na seção de temas, que já estava tratada.

## CORREÇÃO 1 — empilhamento consistente de TODAS as seções
Regra única cobrindo os dois padrões (em `@media (max-width:768px)`):
```css
[data-screen-label][style*="max-width"],
[data-screen-label] > div[style*="max-width"]:not(#lp-navinner) {
  max-width: 100% !important;
  padding: 56px 18px 52px !important;   /* longhand */
}
```
(`#lp-navinner` excluído porque o nav tem tratamento próprio.) Somado a isso:
- `h2 { font-size: clamp(26px, 7.4vw, 38px) }` e parágrafos 15.5px — títulos de desktop
  (clamp até 72px) não dominam mais a tela.
- Blocos decorativos absolutos (`[aria-hidden][position:absolute]` e `[data-parallax]
  [position:absolute]`) ocultos: são formas calibradas para telas largas (ex.: `right:-140px;
  width:520px`) que geravam rolagem lateral.
- A regra genérica de multi-coluna → 1 coluna (`[data-screen-label] [style*="grid-template-columns"]`)
  já existia e cobre todos os blocos.

**Exceção documentada**: a tabela **comparativo** (RECURSO | NOVIXFIN | PLANILHA | APPS
GENÉRICOS) NÃO empilha — as colunas são a própria comparação e as células não têm rótulo, então
empilhar produziria "✓ / manual e lento / 3–6 toques" sem contexto. Mantém 4 colunas com
`1.45fr 1fr 1fr 1fr`, fonte 11.5px e padding 10/7px.

## CORREÇÃO 2 — hambúrguer não abria
**Diagnóstico (estático, sem browser aqui):** o código estava correto —
- o listener era registrado em `landing.js` (função presente, `node --check` OK);
- o CSS de abrir (`nav[data-navopen="1"] #lp-navlinks`, especificidade 0-1-1-1) vence o de
  fechar (`#lp-navlinks`, 0-1-0-0), ambos com `!important`;
- a ordem das regras e a profundidade do `@media` estavam certas.

**Causas prováveis, ambas eliminadas:**
1. **`landing.js` em cache** — nem o CSS nem o JS tinham versionamento; o CSS pode ter sido
   revalidado (o hero mudou) e o JS não. Agora ambos carregam com `?v=<filemtime()>`.
2. **Fragilidade de depender de um append no fim de um arquivo grande** (ordem/execução).
   O toggle virou **script inline logo após o `<nav>`**, com **delegação no `document`**
   (`e.target.closest('#lp-navtoggle')`), então funciona independentemente de `landing.js`.
   A IIFE duplicada foi removida do `landing.js` para não haver dois listeners.

Comportamento: tocar abre/fecha; Esc fecha; tocar num link do nav fecha; voltar ao desktop
(>768px) reseta. `aria-expanded`/`aria-label` atualizados. Alvo de 44px (já no CSS).

## Files changed
- public/index.php — cache-busting em landing.css/landing.js + `<script>` inline do toggle.
- public/scripts/landing.js — IIFE do nav removida (com nota apontando para o inline).
- public/styles/landing.css — regra única das seções, h2/p, decorativos, exceção do comparativo.

## Preservado
Switcher de tema (View Transitions + `localStorage['novixfin-theme']`), herança pré-auth,
handlers, CSRF, `.lp-track`/carrossel. Nenhuma mudança de comportamento.

## Risks
- A regra das seções usa `!important` (necessário: os estilos são inline). Toda ela está dentro
  de `@media (max-width:768px)` — desktop intacto.
- Ocultar decorativos remove enfeite no mobile (ganho: zero rolagem lateral). Reversível.
- Não pude validar em browser: se o hambúrguer ainda falhar, o próximo passo é o console
  (o inline agora é independente do landing.js, o que isola a causa).

## Validation performed
- landing.css: 157/157 chaves; bloco novo na profundidade 1 (dentro do @media correto).
- `node --check` OK: landing.js.
- index.php balanceado: php 13/13, div 421/421, button 12/12, script 3/3, nav 1/1.
- `php -l` local indisponível.

## Remaining work (servidor, ~390px)
- `php -l public/index.php`.
- Rolar a landing inteira: NENHUMA seção como bloco desktop espremido; "cta final" e footer com
  respiro; comparativo legível em 4 colunas.
- Hambúrguer abre/fecha (toque, Esc, link). Seletor de tema (setas + carrossel) troca o tema no
  toque. Tema claro e escuro. **Desktop intacto acima de 768px.**
- **Login/registro não foram verificados nesta fatia** — checagem separada.

## Confidence
82% nas seções (causa estrutural identificada e tratada na raiz); 70% no hambúrguer — o código
estava correto estaticamente, então a correção ataca cache/fragilidade; confirmação depende do
teste real.
