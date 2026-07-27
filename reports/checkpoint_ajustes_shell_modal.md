# Checkpoint — Ajustes finos: sidebar sticky + datas + scroll do modal

Data: 2026-07-26

## Task
Ajustes de baixo risco (layout/UI, sem lógica financeira): (1) sidebar não acompanha o scroll
(gap embaixo, todas as telas); (2) verificar campo de data "duplicado" no modal de lançamento;
(3) conferir comprimento/scroll interno do modal. Parar para revisão.

## 1) Sidebar não acompanhava o scroll — CORRIGIDO
- **Diagnóstico**: `#nvx-side` já tinha `position: sticky; top:0; height:100vh` (padrão canônico
  e correto). O quebrador estava GLOBAL, em `public/styles/main.css`:
  `html, body { overflow-x: hidden !important }` + `body { overflow-y: auto }`.
  Com `overflow-x != visible`, o navegador computa `overflow-y` como `auto` -> html/body viram
  CONTÊINER DE ROLAGEM. Isso muda o scroller do sticky e faz a sidebar rolar junto (some,
  sobrando um gap na coluna).
- **Fix**: `overflow-x: clip` (clipa o horizontal SEM criar contêiner de rolagem, e não força o
  eixo Y a `auto`) e removido o `overflow-y: auto` do body. O viewport volta a ser o scroller;
  `position: sticky` da sidebar e do `#nvx-top` volta a funcionar nas 10 telas, zero gap.
- `overflow: clip` é suportado nos navegadores-alvo (mesma faixa que já usa `<dialog>.showModal`).
- shell.css NÃO precisou de mudança.

## 2) Campo de data "duplicado" — NÃO era duplicação (2 campos distintos)
- `transaction_date` = data do lançamento (sempre visível). `effective_date` = data em que uma
  transação PENDENTE será efetivada automaticamente (só aparece com status Pendente, via
  toggleEffectiveDate/toggleEffectiveDateEditDashboard).
- **Ação**: apenas clarificar rótulos — topo "Data" -> "Data do lançamento"; a condicional
  permanece "Data de efetivação" + texto de ajuda, exibida só no Pendente. Aplicado em criar e
  editar. NADA no handler; names (transaction_date / effective_date) preservados.

## 3) Comprimento / scroll interno do modal — CORRIGIDO
- Adicionado `max-height: 90vh; overflow-y: auto` a `dialog.nvx-modal` (desktop). Modais longos
  (recorrência ligada) rolam DENTRO do card, centralizados, sem esticar a página. (O bottom-sheet
  mobile já tinha `max-height: 92vh; overflow-y: auto`.)
- Progressive disclosure já correto: seções de recorrência só com "Recorrente" marcado;
  "Data de efetivação" só com Pendente. Confirmado.

## Files changed
- **M** public/styles/main.css — overflow-x: clip; remove overflow-y: auto do body.
- **M** public/styles/modal.css — max-height/overflow-y no dialog.nvx-modal.
- **M** public/dashboard.php — rótulos de data ("Data do lançamento") em criar e editar + comentário.

## NÃO alterado
create.php/update.php; names/ids; CSRF; dashboard.js; toggles de efetivação/recorrência.

## Risks
- Baixo. `overflow-x: clip` é global (afeta landing/login também): comportamento = clipa
  horizontal sem rolagem, igual ao objetivo do antigo `hidden`, sem o efeito colateral no sticky.
- `max-height: 90vh` no dialog: em telas muito baixas o modal rola internamente (desejado).

## Validation performed
- Chaves balanceadas: main.css 117/117, modal.css 88/88.
- Rótulos aplicados (criar + editar); comentário corrigido.
- `php -l` local indisponível.

## Remaining work (servidor)
- `php -l public/dashboard.php`.
- Rolar dashboard/histórico (páginas longas): sidebar cobre a altura e acompanha, sem gap, nas
  10 telas. Modal: abrir criar/editar, alternar Efetivada/Pendente (aparece "Data de efetivação")
  e Recorrente on/off (revela recorrência), confirmar scroll interno e centralização. Tema claro
  (ametista) e escuro (tinta). Criar/editar transação ainda grava certo.

## Confidence
88% — causa raiz do sticky isolada e corrigida na origem certa; datas esclarecidas; scroll do
modal garantido. Pende o passe visual no servidor.
