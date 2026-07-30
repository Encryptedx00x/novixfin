# Checkpoint — Chanfro (--cut) dos CTAs da landing no mobile

Data: 2026-07-30

## Task
Corrigir o canto cortado dos botões da landing no mobile: o recuo fixo em px, calibrado para
desktop, virava um talho grande no CTA full-width e alto. Ajuste cirúrgico, com verificação de
escopo (o token é compartilhado com o app). Parar para revisão.

## CAUSA CONFIRMADA (o valor)
`--cut` está definido para o tema **ametista** em dois lugares (débito conhecido de tokens
duplicados): `public/styles/landing.css:13` e `public/styles/entrada.css:12`:

```css
--cut: polygon(0 0, calc(100% - 14px) 0, 100% 14px, 100% 100%, 0 100%);
```

- Recuo **fixo de 14px**, em **px** (não proporcional).
- **Assimétrico**: corta apenas o **canto superior direito**.
- Os outros **14 temas têm `--cut: none`** — o corte é uma assinatura só do ametista.

Num botão de desktop (~230px de largura) o chanfro de 14px lê como detalhe. Quando o CTA vira
full-width e ganha altura no mobile (`padding: 17px 32px` + `min-height`), o mesmo talho de 14px
num único canto fica desproporcional e com cara de defeito.

## SEGUNDO DEFEITO — por que sobrou depois da fatia anterior
`public/styles/landing.css:120`:
```css
html[data-theme="ametista"] [data-present-btn]{ clip-path: var(--cut) !important; … }
```
O botão **"Apresentar"** recebe o corte por **regra CSS com `!important`**, não por estilo inline.
A correção anterior mirava só `[style*="clip-path: var(--cut)"]` (estilo inline), então esse botão
**nunca foi coberto** e manteve o talho de 14px.
Agora ele é alcançado por `html[data-theme="ametista"] button[data-present-btn]`
(especificidade **0,2,2** > **0,2,1** da regra da linha 120, e ambas `!important`).

Também corrigi um erro meu da fatia anterior: eu havia usado `clip-path: none`, que **removia** a
assinatura do tema em vez de **reduzi-la** — o pedido é chanfro pequeno, não ausência de chanfro.

## CORREÇÃO
Duas regras, ambas em `@media (max-width: 768px)`:

**(a) Altura / respiro / centralização — nos 15 temas**
```css
[data-screen-label] a[style*="clip-path: var(--cut)"],
[data-screen-label] button[style*="clip-path: var(--cut)"],
html[data-theme="ametista"] button[data-present-btn] {
  padding: 14px 20px !important;   /* longhand */
  min-height: 52px;
  display: inline-flex !important; align-items: center; justify-content: center;
}
```
O padding vertical cai de 17px para 14px → botão de ~52px, alvo de toque confortável **sem** a
altura exagerada que amplificava o corte. Texto centrado.

**(b) Chanfro reduzido e simétrico — só no ametista**
```css
html[data-theme="ametista"] [data-screen-label] a[style*="clip-path: var(--cut)"],
html[data-theme="ametista"] [data-screen-label] button[style*="clip-path: var(--cut)"],
html[data-theme="ametista"] button[data-present-btn] {
  clip-path: polygon(0 0, calc(100% - 10px) 0, 100% 10px,
                     100% 100%, 10px 100%, 0 calc(100% - 10px)) !important;
}
```
10px em **dois cantos opostos** (superior direito + inferior esquerdo) = simetria de rotação,
discreto, mantendo o traço do tema.
**O escopo por tema é essencial**: aplicar o polígono nos outros 14 temas *adicionaria* um corte
que eles não pedem (`--cut: none`).

## CTAs cobertos
| Botão | Origem do corte | Coberto por |
|---|---|---|
| "Criar conta grátis" (hero) | inline `clip-path: var(--cut)` | (a) + (b) |
| "Apresentar" (hero) | **regra CSS l.120 `!important`** | (a) + (b) ← era o que faltava |
| "Criar conta" (nav) | inline | (a) + (b) |
| "Criar conta grátis" (cta final) | inline | (a) + (b) |
| "Escolher meu tema" (cta final) | sem clip-path (botão de borda) | regra do cta final (full-width, 52px, centrado) |
| `.lg-btn` (login/registro) | `.lg-btn { clip-path: var(--cut) }` | novo bloco em entrada.css |

## ESCOPO — verificação pedida
`--cut` **é token compartilhado**: `tokens.css` o define e botões do app o usam
(`.nvx-mbtn` nos modais, `.dash-fab` no dashboard, botões de tela).
A correção está inteiramente em:
- `public/styles/landing.css` → carregado **apenas** por `public/index.php`;
- `public/styles/entrada.css` → carregado **apenas** por login/register;

e **toda** dentro de `@media (max-width: 768px)`.
→ **Nenhum botão do app (dashboard, modais, telas internas) é afetado**, e o desktop acima de
768px fica idêntico. `tokens.css` não foi tocado.

## Extensão a login/registro (reportando)
`.lg-btn` (entrada.css:169) usa `clip-path: var(--cut)` e no mobile tem `min-height: 54px` —
**mesmo defeito**. Como é a mesma superfície de entrada e o arquivo só serve login/register,
apliquei o mesmo chanfro de 10px, também restrito ao ametista, para não deixar uma terceira
rodada.

## Files changed
- public/styles/landing.css — regras (a) e (b).
- public/styles/entrada.css — chanfro do `.lg-btn`.

## Preservado
Switcher de tema, `localStorage['novixfin-theme']`, herança pré-auth, handlers, CSRF.
`tokens.css` e os CSS do app intocados. Desktop > 768px idêntico.

## Validation performed
- landing.css 170/170 e entrada.css 141/141 chaves; ambos os blocos na profundidade 1 (dentro do
  `@media` correto).
- Especificidade conferida contra a regra `!important` da linha 120 (0,2,2 > 0,2,1).
- Nenhum arquivo PHP alterado nesta fatia (`php -l` sem alvo novo).

## Remaining work (servidor, ~390px)
- CTAs da landing com chanfro pequeno e simétrico (não triângulo), ~52px de altura, texto
  centrado — conferir os dois botões do hero (incluindo "Apresentar") e os do bloco roxo.
- Login/registro: botão com o mesmo chanfro.
- **Botões do app (dashboard/modais) sem regressão** — devem manter o corte de 14px do tema.
- Desktop intacto acima de 768px. Testar em ametista (tem corte) e em um tema sem corte (tinta).

## Confidence
88% — o valor do token foi confirmado, o botão que escapava foi identificado pela origem do
estilo (regra CSS vs inline) e o escopo por arquivo/tema protege o app e os outros 14 temas.
