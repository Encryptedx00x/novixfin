# Checkpoint — Polimento do modal de lançamento

Data: 2026-07-26

## Task
Polimento visual/UX do modal de lançamento (criar/editar) + 1 bug de botão. Baixo risco, sem
tocar em lógica financeira (create.php/update.php e names intactos). Parar para revisão.

## 1) BUG — "Cancelar" do sub-modal "Nova categoria" não fechava — CORRIGIDO
- **Causa**: o sub-modal (#inline-category-modal) tem DOIS fechadores com
  `data-close-inline-category` (o X do cabeçalho + o botão "Cancelar" do rodapé, ambos
  adicionados no reskin). dashboard.js ligava só `modal.querySelector('[data-close-inline-
  category]')` = o PRIMEIRO (o X). O "Cancelar" ficava sem handler.
- **Fix**: `querySelectorAll(...).forEach` liga todos. `closeModal` faz apenas
  `inlineModal.close()` — fecha SÓ o sub-modal e volta ao lançamento (dialogs irmãos, não
  aninhados), com os campos do lançamento intactos. Fluxo de criação (fetch inline + append +
  refreshCategorySelects) preservado.

## 2) Backdrop inconsistente — CORRIGIDO
- Os dialogs traziam a classe Tailwind `backdrop:bg-slate-950/70` (slate fixo, ignora tema),
  que vencia o `::backdrop` do componente. Removida de dashboard.php e também categories/goals/
  wallets.php (mesma inconsistência). Agora só vale `.nvx-modal::backdrop { background:
  color-mix(in srgb, var(--ink) 45%, transparent) }` — tema-aware, igual aos demais.

## 3) Largura / respiro — AJUSTADO
- `dialog.nvx-modal.nvx-tx-modal { width: min(600px,100%); max-width: min(600px,100%) }`
  (antes ~512px via max-w-lg). Menos "coluna comprida". Rolagem interna própria
  (`max-height:90vh; overflow-y:auto`, do ajuste anterior) e centralização (dialog nativo)
  mantidas.

## 4) Categorias com "Ver mais" — IMPLEMENTADO
- tx-modal.js `renderChips`: renderiza todos os chips, colapsa os além de 8 (mantendo o ativo
  sempre visível) com `.nvx-chip--collapsed` e insere `Ver mais (N)` que revela o restante e
  se remove. "+ Nova" (data-chip-keep) permanece ao final. Nenhuma categoria perdida — só
  colapso visual. Re-render em filtro por tipo / criação inline preserva o comportamento.

## 5) Sublinha do valor — ALINHADA
- O traço solto (`.nvx-mval-line`, 180px centralizado) ficava curto e deslocado porque o símbolo
  de moeda à esquerda empurrava o input. Agora a sublinha é `border-bottom: 2px solid
  var(--accent)` no PRÓPRIO input: acompanha exatamente a largura do campo e alinha com o número.
  `.nvx-mval-line` ocultada; input alargado p/ 200px.

## Files changed
- **M** public/scripts/dashboard.js — querySelectorAll nos fechadores do sub-modal.
- **M** public/scripts/tx-modal.js — "Ver mais" nos chips.
- **M** public/styles/modal.css — largura nvx-tx-modal; sublinha via border-bottom; chips
  collapsed/more.
- **M** public/dashboard.php, categories.php, goals.php, wallets.php — remoção da classe
  `backdrop:bg-slate-950/70`.

## NÃO alterado
create.php/update.php; names/ids; CSRF; engine de categoria (filtro/inline/edição); fiação do
modal reconstruído.

## Risks
- Baixo. Só UI/CSS/wiring de fechar. O Cancelar agora chama o mesmo closeModal já usado pelo X.

## Validation performed
- `node --check` OK: tx-modal.js, dashboard.js.
- modal.css: chaves 92/92. `backdrop:bg-slate` = 0 ocorrências restantes.
- `php -l` local indisponível.

## Remaining work (servidor)
- `php -l public/{dashboard,categories,goals,wallets}.php`.
- Abrir lançamento: backdrop igual aos outros (claro e escuro), modal mais largo com scroll
  interno, chips colapsados com "Ver mais", sublinha do valor alinhada. "+ Nova" -> Cancelar
  fecha só o sub-modal e volta com campos intactos. Criar/editar ainda grava certo.

## Confidence
88% — bug de fechar isolado e corrigido; ajustes visuais diretos. Pende o passe visual no servidor.
