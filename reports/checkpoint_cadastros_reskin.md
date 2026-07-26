# Checkpoint — Fatia CADASTROS (Categorias, Metas, Carteiras) reskin

Data: 2026-07-26

## Tarefa
Refazer o reskin tokenizado de Categorias, Metas e Carteiras (o trabalho anterior não
chegou ao repo). Parar para revisão. Manter o andaime (fatia seguinte).

Fonte visual: Claude Design "NovixFin App.html" (cache): CATEGORIAS (l.1192-1280),
METAS (l.916-996), CARTEIRAS (l.1084-1190).

## Files (gravados em disco no source tree)
- NEW public/styles/categories.css, goals.css, wallets.css (escopo .cat-screen/.goals-screen/
  .wallets-screen + mapa de modal .cat-modal/.goals-modal/.wallets-modal).
- public/categories.php, public/goals.php, public/wallets.php (só visual + $pageStyles).

## Padrão aplicado (igual às 7 telas já tokenizadas)
Section reescrita com estilo inline var(--token) (fidelidade nativa aos 15 temas); CSS
escopado por tela veste também o modal (mapa Tailwind->tokens). Modais já eram <dialog>
(convertidos na fatia de modais) — só ganharam a classe de escopo. Handlers, campos,
validações, CSRF, ids e JS preservados; nenhum handler alterado.

## Por tela
- **Categorias** (categories/save.php, delete.php): 2 colunas Receitas/Despesas com quadrado
  de inicial + nome + excluir (SVG, confirm mantido). Create via modal (#categoryModal, JS
  openModal/closeModal intactos). Emojis 💰/💸 do select -> texto. NÃO inventei edit/cor/
  contagem de transações por categoria (backend não tem) — reportado.
- **Metas** (goals/save.php, delete.php): cards com distinção Meta x Limite (chip kind +
  status por token: meta "Em progresso"/"Concluída"; limite "Dentro"/"Perto"/"Estourado"),
  barra de progresso, prazo/conta/auto. **Bloco falso "+200 pontos / recompensa" REMOVIDO**
  (goal_completed não está ligado no backend — não exibir como ativo). **Delete ganhou
  confirm()** (antes não tinha; ação destrutiva agora confirmada). Modal #modal-goal +
  goals.js (data-open-modal/data-edit-goal/data-close-modal) + goalsData + $pageScripts
  intactos.
- **Carteiras** (wallets/save.php, delete.php): lista (cards com chips de conta + Ver
  detalhes) e detalhe (voltar + tabela por conta com receitas/despesas/saldo + totais por
  moeda) reskinadas. editWallet(json)/openWalletModal()/closeWalletModal()/get-accounts.php/
  account_ids[] e o delete com confirm intactos.

## Qualidade
--f-num em valores (data-balance); --accent-text/tokens no texto pequeno; barras/chips por
tokens (limite estourado -> --neg, perto -> --gold); focus-visible; SVG no lugar de emoji
decorativo (ícones de conta/carteira são dados do usuário, mantidos); sem travessões.

## Validação
- Estático OK: tags PHP balanceadas (categories 31/31, goals 46/46, wallets 68/68);
  <section>/<dialog> balanceados; handlers save/delete presentes; confirmações presentes;
  `node --check` OK em categories/wallets (goals tem JSON PHP-embed no <script>, esperado).
- **10/10 telas com classe escopada** (grep: dash/hist/accounts/cards/io/moeda/pf +
  cat/goals/wallets).
- Servidor (pendente): `php -l public/{categories,goals,wallets}.php` + smoke.

## BLOQUEIO DE VERSIONAMENTO (crítico)
O projeto NÃO está sob git funcional: `Desktop/NovixFin/.git` é pasta VAZIA (sem HEAD/
objects/refs); git sobe para o repo do home (branch gsd/phase-1) que NÃO rastreia nada do
NovixFin. `git status` -> `??`; `git diff --stat` vazio. **Não commitei** — não há repo
válido, e criar repo/commitar no repo errado (risco de vazar .env, árvore enorme) é decisão
do usuário. Esta é a causa provável do "sumiço" anterior: os arquivos ficam só em disco.
Arquivos verificados em disco (ls + wc). Decidir versionamento com o usuário.

## TESTE (servidor)
Criar/editar/excluir nas três; modais <dialog> cobrem a sidebar (recolher não clica por
cima), Esc e clicar-fora fecham, confirmação de exclusão intacta; tema claro (ametista) e
escuro (tinta); as outras 7 telas sem regressão.

## Confiança
78% — reskin consistente e contratos preservados; pende `php -l`/passe visual E,
sobretudo, resolver o versionamento antes que a fatia se perca de novo.
