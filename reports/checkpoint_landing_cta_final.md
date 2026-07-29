# Checkpoint — Seção "cta final" no mobile + varredura anti-recorrência

Data: 2026-07-29

## Task
Corrigir a última seção da landing que não empilhava no mobile (bloco roxo de CTA de fechamento)
e fazer uma varredura confirmando que nenhuma outra seção escapou. Parar para revisão.

## Como a seção está marcada
```html
<section data-screen-label="cta final"
         style="background: var(--stage); position: relative; overflow: hidden;
                clip-path: polygon(0 18px, 100% 0, 100% 100%, 0 100%);">
  <div aria-hidden="true" …>            (4 decorativos absolutos — já ocultos no mobile)
  <div data-reveal="1" style="max-width: 900px; margin: 0 auto; padding: 132px 32px 116px; …">
    <h2 …>  <p …>
    <div style="display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;">  ← 2 botões
    <div style="display: flex; gap: 22px; justify-content: center; flex-wrap: wrap;">  ← 4 selos
```

## CAUSA DO ESCAPE (mesma classe do hero)
A seção **não tem NENHUM grid**: `grid-template-columns` = 0 ocorrências. Ela monta as duas
linhas com **`flex-wrap: wrap`**.
- A regra global de empilhamento mira `[data-screen-label] [style*="grid-template-columns"]`.
- A regra de `flex-wrap` existia, mas **escopada ao hero** (`header[data-screen-label="hero"] …`),
  porque nasceu para consertar os CTAs do hero.

Resultado: botões lado a lado e os 4 selos espremidos em 2 colunas. É o mesmo padrão do bug
anterior (o hero escapava por ser `<header>` e não `<section>`): **a regra mirava a estrutura,
não o mecanismo de layout**.

Observação: o **padding interno JÁ estava correto** — o div de conteúdo é filho direto com
`max-width: 900px`, alcançado pela regra de contêineres (56px topo / 18px lateral / 52px base).
O que dava a sensação de "chapado" era o conteúdo não empilhado.

## Correção
1. **Linhas de CTA** — a regra passou a cobrir hero **e** "cta final":
   `flex-direction: column; align-items: stretch; gap: 10px`, com
   `> a, > button` em `width:100%`, `min-height:52px`, texto centrado.
   (O `--cut` assimétrico desses botões já havia sido normalizado na fatia anterior.)
2. **Os 4 selos** — 1 por linha, centrados, com o gap caindo de 22px para 10px.
3. **Botão fantasma "Escolher meu tema"** — a borda `1.5px solid var(--stage-line)` quase
   desaparecia sobre o roxo e o `inline-flex` sem `justify-content` deixava o rótulo torto.
   Agora: borda de 1px em `color-mix(--stage-ink 55%)`, padding vertical 14px e centralização,
   com o mesmo peso do primário.

**Decisão documentada (não empilhar tudo)**: as outras linhas `flex-wrap` da landing —
chips do "demo" (12px), selos da "gamificação", abas do "tour" — **continuam quebrando
naturalmente**. Empilhar 4 abas ou chips pequenos em largura total pioraria a leitura. Os cards
de "features" já ficam um por linha por causa da guarda de largura (`max-width:100%`).

## VARREDURA ANTI-RECORRÊNCIA — 17/17
Para cada bloco de primeiro nível, verifiquei se existe contêiner alcançado pela regra de padding
(a própria seção com `max-width`, ou um `div` **filho direto** com `max-width`):

| # | seção | self max-w | filho direto max-w | coberta |
|---|---|---|---|---|
| 1 | nav | – | sim | ✔ |
| 2 | hero | – | sim | ✔ |
| 3 | credibilidade | – | – | **full-bleed intencional** |
| 4 | problema-solucao | sim | sim | ✔ |
| 5 | demo 3s | – | sim | ✔ |
| 6 | previsão estrela | sim | – | ✔ |
| 7 | tour de produto | – | sim | ✔ |
| 8 | features | sim | sim | ✔ |
| 9 | antes-depois | – | sim | ✔ |
| 10 | 15 temas switcher | sim | sim | ✔ |
| 11 | gamificação | – | sim | ✔ |
| 12 | comparativo | sim | sim | ✔ (4 colunas por exceção) |
| 13 | segurança | – | sim | ✔ |
| 14 | prova social | sim | – | ✔ |
| 15 | faq | – | sim | ✔ |
| 16 | cta final | – | sim | ✔ (corrigida agora) |
| 17 | footer | – | sim | ✔ |

**A única fora da regra é a esteira (credibilidade) — e está correta**: usa
`padding: 26px 0` (nenhum respiro lateral de desktop para desfazer) e precisa ser full-bleed,
porque é uma marquee que rola de ponta a ponta; seu `width:max-content` já está excluído da
guarda de largura. **Nenhuma seção sobrou para uma próxima rodada.**

Mecanismos de layout cobertos, por categoria:
- `grid-template-columns` → regra global (+ exceção do comparativo);
- `flex-wrap` → CTAs (hero + cta final) empilham; demais quebram por decisão;
- largura fixa > 354px → guarda `max-width:100%` (exceto carrossel e esteira);
- `font-size` inline 30–44px → clamp por tamanho;
- decorativos absolutos → ocultos.

## Files changed
- public/styles/landing.css (só o bloco `@media (max-width:768px)`).

## Preservado
Switcher de tema (View Transitions + `localStorage['novixfin-theme']`), herança pré-auth,
handlers, CSRF. **Desktop acima de 768px inalterado** — nada fora do media query.

## Validation performed
- landing.css: 169/169 chaves; bloco novo na profundidade 1 (dentro do `@media` certo).
- Varredura de cobertura das 17 seções (tabela acima), verificada programaticamente.
- `php -l` local indisponível (nenhum arquivo PHP foi alterado nesta fatia).

## Remaining work (servidor, ~390px)
- Seção roxa: 2 botões full-width empilhados, 4 selos 1 por linha legíveis, respiro igual ao das
  outras seções, botão "Escolher meu tema" com borda visível e rótulo centrado.
- Rolar a landing inteira confirmando que nenhuma seção aparece chapada/desktop.
- **Desktop intacto acima de 768px.**

## Confidence
88% — a causa foi identificada com precisão (flex-wrap fora do escopo do hero) e a varredura
cobre as 17 seções por mecanismo de layout, não por inspeção pontual.
