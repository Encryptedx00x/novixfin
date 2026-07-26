# Checkpoint — Fatia CARTÕES (reskin tokenizado) — última tela

Data: 2026-07-25

## Tarefa
Re-skinar public/credit-cards.php com o design tokenizado. Última tela e a mais
sensível (fatura / pagamento / reversão / saldo). Reskin puro — nenhuma mudança de
lógica financeira. Manter a ponte de compatibilidade. Parar para revisão.

Fonte visual: Claude Design "NovixFin App.html" (cache scratchpad/app-src.html),
TELA: CARTÕES + FATURA (l.749-914).

## Files changed
- NEW public/styles/cards.css — escopo .cards-screen (conteúdo) + .cards-modal (os
  2 <dialog>). Mapa utilitárias Tailwind -> tokens dos 15 temas.
- public/credit-cards.php — só visual: classe .cards-screen na section, $pageStyles,
  2 modais `<div>` -> `<dialog>`, 4 chamadas de abrir/fechar modal -> showModal/close,
  2 emojis de aviso dinâmico trocados por texto. NADA de lógica/markup de form.

## Estratégia (por que override de tokens, não reescrita)
É a tela financeira mais sensível: os forms de pagamento têm lógica de distribuição
multi-conta em JS fortemente acoplada ao markup (ids/classes: account-item,
account-checkbox, account-amount, accountsSection, distributionSummary,
distributionWarning, submitPayBill...). Reescrever o markup arriscaria dropar um hook
e corromper o pagamento. Então tokenizei por OVERRIDE escopado (mesma estratégia já
usada no dashboard.css/accounts.css), preservando o markup byte a byte.

## Preservado (verificado por grep)
- **4 handlers financeiros**: pay-bill.php, revert-payment.php (2 forms), delete-bill.php,
  save-transaction-settings.php — forms, campos, CSRF intactos (5 tokens `_token`).
- **3 confirm()** de revert (x2) e delete — mesmo texto de aviso, intactos.
- **JS de distribuição multi-conta**: as 12 chamadas `classList('hidden')` da lógica de
  contas/resumo/aviso NÃO foram tocadas. Só as 4 de abrir/fechar modal viraram
  showModal()/close(). A validação de submit (monta account_debits[] hidden) intacta.
- Ícones de cartão/conta (dados do usuário) mantidos.

## Modais -> <dialog> nativo
payBillModal e transactionSettingsModal: `<div overlay><div card>` -> `<dialog card>`.
Abrir por showModal() (sobe pra top layer do browser -> resolve o empilhamento com o
shell, mesmo bug corrigido nas outras telas); fechar por .close(); Esc e clicar-fora
(e.target===dialog) nativos; ::backdrop tema-aware via cards.css. overflow-y no próprio
dialog (a lista de contas mantém seu max-h-64).

## Qualidade
- Valores em --f-num via [data-balance] (mapeado em cards.css).
- Status da fatura pelos tokens: Aberta -> --accent-2, Paga -> --pos, Vencida -> --neg,
  Cancelada -> --muted (o "gold/vencida" da auditoria: vencida é vermelha/--neg, correto).
- CTAs primários com clip-path var(--cut) (o notch/canto do design) + var(--r-btn).
- Inputs por tokens; focus-visible com anel --accent; sem travessões; 2 emojis de aviso
  dinâmico (⚠️/ℹ️) trocados por texto (SVG/texto, sem emoji).

## LIMITE (transparência)
Reskin por override de tokens, NÃO re-diagramação estrutural. Entrega os 15 temas
(cor/tipografia/status/notch/modais nativos) com risco ~zero aos forms e ao financeiro,
mas o layout segue a estrutura do PHP atual (cards empilhados), não o grid 2-colunas
exato do design (cartão em gradiente com número mascarado à esquerda + coluna de
lançamentos da fatura à direita). Sem gráfico de cor fixa nesta tela (não há canvas).
Re-diagramação estrutural = fatia futura possível, DEPOIS de validar o financeiro.

## Ponte de compatibilidade
Mantida (theme-compat.css / data-legacy-theme / NOVIX_THEME_LEGACY / themes.css legado
inalterados). Com Cartões tokenizada as 9 telas consomem tokens, mas a remoção do andaime
é fatia SEPARADA — só depois de Cartões validada em produção.

## Validação
- Local: PHP indisponível. Estático OK:
  - tags 83/83; <dialog> 2/2; <form> 5/5; <section> 1/1; <div> 41/41; 0 overlay residual.
  - 4 handlers presentes; 3 confirm(); 5 CSRF; 12 classList de distribuição preservados;
    4 modal-toggles -> showModal/close; 0 emoji de aviso.
  - `node --check` do bloco <script> -> OK.
- Servidor (pendente): `php -l public/credit-cards.php` + `php tests/smoke.php`.

## TESTE (servidor — financeiro, capriche)
1. Registrar pagamento de fatura -> saldo muda corretamente; cruzar com o hero do dashboard.
2. Reverter o pagamento (confirmação aparece) -> saldo VOLTA ao valor exato de antes.
3. Excluir fatura vazia (confirmação) -> sem corromper saldo.
4. Configurações de transações salvam.
5. Débito multi-conta: distribuição, aviso de "maior que o valor pago", submit bloqueado.
6. Modal cobre a sidebar e o botão de recolher não fica clicável; Esc/fora fecham.
7. Um tema escuro e um claro; demais telas sem regressão.

## Estado do app: 9/9 telas tokenizadas
Entrada, Landing, Perfil, Shell, Dashboard, Histórico, Contas, Import/Export, Moeda,
**Cartões**. Próxima fatia (separada): remover a ponte de compatibilidade.

## Confiança
80% — override de tokens consistente e financeiro preservado byte a byte; pende `php -l`
+ o teste financeiro no servidor e o passe visual.
