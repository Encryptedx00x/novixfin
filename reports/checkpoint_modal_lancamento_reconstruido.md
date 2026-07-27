# Checkpoint — Modal de lançamento reconstruído (estrutura do design)

Data: 2026-07-26

## Task
Reconstruir o modal de lançamento (criar/editar transação) para ficar IDÊNTICO ao design em
ESTRUTURA (não só estilo): toggle Despesa/Receita no lugar do select Tipo, valor em destaque no
topo, chips de categoria no lugar do select Categoria, toggle Efetivada/Pendente no lugar do
checkbox, ordem do design. Preservar 100% a fiação de dados. Handlers create.php/update.php
intocados. Parar para revisão.

## Contrato de dados (levantado antes de reescrever)
Names postados (criar E editar, idênticos): _token, id (editar), account_id, type, category_id,
amount, currency, description, transaction_date, is_effective, effective_date, is_recurring,
recurrence_type, recurrence_day, recurrence_limit_mode, recurrence_end_date,
recurrence_total_occurrences.
dashboard.js depende dos controles como <select>/<checkbox>: filtro de categoria por tipo
(renderCategorySelect reconstrói as options de <select name=category_id> por <select name=type>),
criação inline (append + refreshCategorySelects), e pré-preenchimento na edição (editModal.query
Selector por name + renderCategorySelect). NÃO pode quebrar.

## Arquitetura da solução
A UI muda, os DADOS não. Os controles nativos viram FONTE DE VERDADE OCULTA (class="hidden") e a
UI do design é uma camada que os reflete/dirige:
- **NEW public/scripts/tx-modal.js**:
  - Toggle segmentado -> <select> (`data-seg-target`): clique seta value + dispara 'change'
    (dashboard.js re-filtra categoria) + reflete ativo.
  - Toggle segmentado -> checkbox (`data-segcheck-target`): Efetivada/Pendente seta .checked +
    dispara 'change' (dispara toggleEffectiveDate) + reflete.
  - Chips -> <select name=category_id> (`data-chips-target`): renderiza chips das options,
    clique seta value + 'change'; MutationObserver(childList) re-renderiza quando dashboard.js
    reconstrói o select (filtro por tipo / criação inline).
  - Re-sincroniza tudo no atributo `open` de cada <dialog.nvx-tx-modal> (a edição pré-preenche
    os controles ocultos ANTES de showModal).
- Símbolo de moeda dinâmico permanece no wiring inline do dashboard (cobre criar/editar).
- modal.css: `.nvx-seg/.nvx-seg-btn`, `.nvx-chips/.nvx-chip(--add)`, `.nvx-dashed`,
  `.nvx-mgrid(--status)`, `.nvx-mfield/.nvx-mlabel`.

## Estrutura reconstruída (ordem do design), criar e editar
1. Cabeçalho título/subtítulo/X (criar: "Novo lançamento"/"Registre em segundos."; editar:
   "Editar lançamento"/"Ajuste e salve — mesmos campos do novo.").
2. Toggle Despesa/Receita (dirige <select name=type> oculto).
3. Valor em destaque com símbolo de moeda dinâmico (R$/US$/€).
4. Descrição com placeholder "Descrição — ex.: iFood, salário, aluguel…".
5. Chips de categoria (dirigem <select name=category_id> oculto) + chip "+ Nova" (reusa
   data-inline-category-trigger — criação inline intacta).
6. Conta / carteira + Data (2 colunas).
7. Toggle Efetivada/Pendente (dirige checkbox is_effective) + Moeda (2 colunas).
8. Recorrente (caixa tracejada) — checkbox is_recurring; campos de recorrência preservados
   integralmente (o backend precisa de recurrence_type/day/limit_mode/end_date/total).
9. Rodapé Cancelar + "Lançar"/"Salvar alterações" (accent, --cut, ícone) + nota "Enter salva…".

## Files changed
- **NEW** public/scripts/tx-modal.js
- **M** public/styles/modal.css — componentes seg/chips/dashed/grid/field.
- **M** public/dashboard.php — modais criar e editar reconstruídos; $pageScripts += tx-modal.js;
  classe nvx-tx-modal nos 2 <dialog>.

## NÃO alterado
create.php / update.php; todos os names/ids postados; CSRF; dashboard.js (engine de categoria,
inline, edição); funções inline toggleEffectiveDate/toggleRecurrenceFields (e variantes Edit).
`required` removido apenas dos selects que ficaram OCULTOS (type/category) para evitar o erro
"invalid form control is not focusable"; a validação server-side permanece. account_id (visível)
mantém required.

## Risks
- Médio (financeiro). A camada visual dirige os controles reais; se um 'change' não disparasse,
  a categoria não re-filtraria — mitigado por dispatch explícito + MutationObserver + re-sync no
  open. Editar pré-preenche os ocultos e o re-sync no `open` reflete toggles/chips.
- Toggle de tipo não re-reflete sozinho após criação inline de categoria de outro tipo (edge);
  reabrir corrige. Sem impacto em dados.

## Validation performed
- `node --check` OK: tx-modal.js.
- dashboard.php balanceado: <div> 150/150, <dialog> 4/4, <form> 5/5, <?php/?> 158/158,
  <select>/</select> 15/15 (menções em comentário à parte).
- `php -l` local indisponível.

## Remaining work (servidor — TESTE FINANCEIRO)
- `php -l public/dashboard.php`.
- Criar DESPESA e RECEITA pelo toggle -> grava tipo certo, saldo do hero recalcula.
- Categoria por chip -> salva a categoria certa; "+ Nova" cria inline e o chip aparece/seleciona.
- Efetivada vs Pendente -> status certo (Pendente pede data de efetivação).
- Recorrente -> grava recorrência.
- EDITAR uma transação -> toggles/chips/campos pré-preenchidos corretamente; salva sem trocar
  nada indevido. Tema claro (ametista) e escuro (tinta). Bottom-sheet no mobile.

## Confidence
80% — arquitetura preserva a fiação e reproduz a estrutura do design; a confiança sobe após o
teste financeiro real (criar/editar) no servidor.
