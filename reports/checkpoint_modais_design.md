# Checkpoint — Modais IDÊNTICOS ao design (composição única)

Data: 2026-07-26

## Task
Deixar todos os modais do app idênticos ao design (não só tokenizados): reproduzir a COMPOSIÇÃO
do design (cabeçalho título+subtítulo+X, campo de valor com símbolo de moeda dinâmico, botão
primário accent com --cut, backdrop tema-aware, bottom-sheet mobile), padrão único em todas as
telas. Sem tocar em lógica. Parar para revisão.

## Referência (Claude Design "NovixFin App.html", cache local)
Seção "Sobreposições — modal, sheet, toasts" + modal de lançamento (mTitle/mSub/mBtn/mSym):
- Overlay `fixed inset:0; background:color-mix(ink 45%); blur; place-items:center`.
- Card `width:min(480px,100%); background:surface; border:card-b; radius:calc(r+10); sombra;
  padding:26px; animation:nvxPop`.
- Cabeçalho: título (f-d 21px) + subtítulo (muted 12.5px) + botão X (34px, border line-soft).
- Valor: cap "VALOR" + símbolo (mSym, f-m 22px muted) + input grande centralizado (42px) +
  sublinhado accent.
- Rodapé: Cancelar (border line-soft) + primário accent com clip-path --cut + ícone check {{mBtn}}.
- Exclusão: ícone circular neg + título + texto + botões (border neg no card).

## Solução (componente único)
`public/styles/modal.css` reescrito: o `<dialog>` nativo + `::backdrop` reproduz o overlay
(Esc/clicar-fora/top-layer sobre a sidebar). Classes de composição reaproveitadas por TODAS as
telas: `.nvx-mhead/.nvx-mtitle/.nvx-msub/.nvx-mclose`, `.nvx-mvalue/.nvx-msym/.nvx-mval-input/
.nvx-mval-line`, `.nvx-mfoot/.nvx-mbtn(+--danger)/.nvx-mcancel/.nvx-mfoot-note`,
`.nvx-mdanger/.nvx-mdanger-icon`; modificadores `nvx-modal--danger` (borda neg) e
`nvx-modal--wide` (540px). Animações `nvxPop` (desktop) e `nvxSheet` (mobile bottom-sheet
`position:fixed; inset:auto 0 0 0`). Mapa Tailwind->token mantido para o miolo dos forms.

Símbolo de moeda dinâmico: `<span data-currency-symbol="#sel">` + wiring genérico no dashboard
(R$/US$/€ conforme o select), cobrindo criar e editar.

## Files changed
- **M** public/styles/modal.css — componente reescrito para a composição do design.
- **M** public/dashboard.php — 4 modais: criar (símbolo + "Lançar"), editar (símbolo + "Salvar
  alterações"), inline-category, excluir (perigo); + JS do símbolo de moeda; título/subtítulo
  estáticos por modal (criar vs editar são <dialog> separados).
- **M** public/accounts.php — conta (cabeçalho + rodapé) + excluir (perigo).
- **M** public/categories.php — cabeçalho + rodapé.
- **M** public/goals.php — cabeçalho (título dinâmico) + rodapé.
- **M** public/scripts/goals.js — fecha por qualquer [data-close-modal]; título Nova/Editar meta.
- **M** public/wallets.php — cabeçalho + rodapé (wide).
- **M** public/credit-cards.php — payBill (wide) + transactionSettings: cabeçalho + rodapé.

## NÃO alterado
Forms, ids, names, campos, CSRF, handlers (create/update/delete/save/pay-bill/save-transaction-
settings), confirmações de exclusão, ids que o JS popula (#modalTitle, #walletModalTitle,
#payBillCardName, #transaction_settings_card_name, #deleteAccountName, todos os campos). Só
composição visual + título/subtítulo/botão.

## Risks
- Médio-baixo. Muitas edições de markup, mas cirúrgicas (cabeçalho/rodapé/valor). Handlers e
  campos intactos. goals.js: closeButton->closeButtons (querySelectorAll) — melhora, não regressão.
- Campo de valor virou input grande sem borda (classe nvx-mval-input) — mantém id/name/type/
  min/step/required; só muda a aparência.
- credit-cards financeiramente sensível: só cabeçalho/rodapé trocados; toda a lógica de
  distribuição/débito e os 3 confirm()/CSRF preservados.

## Validation performed
- `node --check` OK: goals.js.
- modal.css: chaves balanceadas (72/72).
- <dialog> balanceados por arquivo (dashboard 4, accounts 2, cards 2, cat/goals/wallets 1).
- `php -l` local indisponível.

## Remaining work (servidor / usuário)
- `php -l` nos 6 .php.
- Passe visual em ametista (claro) e tinta (escuro): cabeçalho com título/subtítulo, backdrop
  tema-aware, botão accent com --cut, símbolo de moeda dinâmico no de lançamento, título/botão
  trocam entre criar e editar, bottom-sheet no mobile, cobre a sidebar, Esc/clicar-fora fecham,
  criar/editar/excluir OK, confirmação de exclusão intacta. Foco no "Novo lançamento".
- Limpeza futura: remover os mapas de modal por-tela redundantes (.cat-modal/.acc-overlay/etc.).

## Confidence
84% — composição fiel ao design e contratos preservados; pende `php -l` e o passe visual real.
