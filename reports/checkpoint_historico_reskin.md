# Checkpoint — Fatia Histórico (reskin tokenizado) + housekeeping do AGENTS.md

Date: 2026-07-23

## 0) Housekeeping (item 0 do escopo)

**AGENTS.md** — seção "Production Environment Facts" reescrita:
- Produção é **VPS com Docker + Caddy + MariaDB, PHP 8.3.32**. Hostinger removido.
- **Alvo de linguagem: PHP 8.1+ para código NOVO** (match, str_contains, nullsafe,
  union types, promoção de construtor, enums liberados). Registrado explicitamente:
  **não retrofitar** o código 7.4 existente — roda bem no 8.3 e reescrever é churn com
  risco de regressão. Modernizar só o arquivo que já está sendo mexido por outro motivo.
  `catch` continua sempre com variável (agora como estilo da casa, não requisito).
- Nova subseção **"Deployment layout and the `actions/` routing trap"**: docroot é
  `/srv/sites/novixfin/public`, `actions/` fica FORA dele e exige a rota `@actions` em
  `/srv/stack/Caddyfile`. **Modo de falha documentado:** sem a rota, todo `POST /actions/*`
  cai no fallback e devolve a landing com **HTTP 200** — sem 404, sem 500, sem log. Se um
  form "não faz nada" em produção mas funciona local, checar `@actions` PRIMEIRO.
- Referências obsoletas a Hostinger corrigidas em outros 3 pontos (overview, validação,
  cron). **state/context.md** recebeu o mesmo bloco em português no topo.

## 1) Diagnóstico de JS (item 3 do escopo)

`public/scripts/history.js` **não lê `data-theme` em nenhum ponto** — diferente do
`dashboard.js`, que precisou de ajuste na fatia Perfil. Nenhuma alteração foi necessária.
O que o arquivo consome é estritamente estrutura do DOM, e isso foi preservado.

## 2) Reskin de public/history.php

Fonte visual: Claude Design "NovixFin App.html", TELA: HISTÓRICO (linhas 575-746 do
fonte, já em cache local da fatia anterior — não foi preciso reimportar).

**Estrutura portada:** cabeçalho, card de filtros, três cards de resumo (resumo por
conta / saldo efetivado / a receber-a pagar), lista agrupada por dia com subtotal por
grupo, linhas no grid do design com ícone facetado + descrição + categoria + conta +
chips + valor + ações, estado vazio, e os breakpoints do design (1279 / 1020 / 640).

**Preservado integralmente:**
- Os 5 filtros reais (`from`, `to`, `account_id`, `is_effective`, `is_recurring`) e o
  hidden `view`, com o mesmo comportamento de query string. Verificado por script.
- As duas visões (`table` / `spreadsheet`) e o `changeView()`.
- O modal de edição postando para `/actions/transactions/update.php`, com CSRF,
  `redirect_to` e todos os campos/validações.
- **Contrato do history.js verificado mecanicamente:** 21 IDs, 8 `data-*` e 16 campos
  `name=` exigidos pelo JS — **nenhum faltando**.
- `data-balance` mantido em todos os 8 pontos monetários (mascaramento de saldos).

**AÇÕES DESTRUTIVAS — cuidado especial:** os três fluxos (excluir transação, excluir
todas da conta, excluir todas) continuam passando por `<dialog>` de confirmação
explícita, com o **mesmo texto de aviso** ("Esta ação não pode ser desfeita"), o mesmo
CSRF e o mesmo `redirect_to`. Os gatilhos mantêm os mesmos `data-delete`,
`data-delete-account`, `data-delete-all` e a **mesma condição de exibição** (o botão por
conta só aparece quando há filtro de conta ativo). Handlers não tocados.

**Desvio deliberado do design (reportado):** o design mostra um ícone de lixeira em cada
linha do "Resumo por conta", o que criaria um novo ponto de entrada destrutivo por conta.
Não portei — manter a superfície destrutiva atual é mais importante que a fidelidade
visual nesse detalhe. O botão segue onde estava.

**Não inventado:** o design tem filtros de Categoria e Moeda, paginação, botão "Efetivar"
inline e chips de "filtros ativos". Nada disso existe no backend real, então nada foi
renderizado como se existisse.

## 3) Qualidade em tela densa (item 2)
- `--f-num` + `font-variant-numeric: tabular-nums` em todos os valores monetários,
  subtotais, datas da planilha e campos numéricos do modal (12 ocorrências).
- `--accent-text` no texto pequeno colorido (link "Gerenciar", chip de fixa) — nunca o
  `--accent` de botão.
- Chips de status usam só tokens (`--pos`, `--neg`, `--gold`, `--accent`, `--accent-2`).
  **Zero cores locais** e zero classes Tailwind de cor remanescentes (verificado: 0).
- `focus-visible` com anel `--accent` em inputs, selects e botões, escopado.
- Em telas estreitas os chips colapsam para o ponto colorido (padrão do design).

## 4) Files changed
- `AGENTS.md` — ambiente/linguagem/rota `@actions`.
- `state/context.md` — mesmo bloco + mantidos os invariantes anteriores.
- NEW `public/styles/history.css` — **100% escopado sob `.hist-screen` / `.hist-modal`**
  (verificado por script: 0 seletores fora do escopo).
- `public/history.php` — reskin; lógica de filtros/consultas intocada; adicionado só
  agrupamento por dia e totais do período (apresentação, somente leitura).

## 5) Validation
- Contrato JS: 21 IDs / 8 data-attrs / 16 names — nenhum faltando.
- Balanceamento de tags: div 55/55, span 51/51, form 5/5, dialog 4/4, section 1/1,
  table/thead/tbody/tr/td/th, label 20/20, select 10/10, button 17/17 — todos pares.
- Chaves e parênteses PHP balanceados; nenhum `catch` sem variável.
- `$pageStyles` antes do include do header e `$pageScripts` antes do footer — OK.
- CSS sem vazamento de escopo.
- **PHP local indisponível** — rodar no servidor:
  `php -l public/history.php` e `php tests/smoke.php`.
- Sem migração. Sem mudança de schema. Nenhum handler alterado.

## 6) Riscos
- Tela de leitura: não toca saldo, atomicidade, fatura nem recorrência.
- A visão "Lista" passou de tabela plana para agrupada por dia. É só apresentação sobre
  as transações já carregadas, mas é a mudança visual mais perceptível — foco do teste.
- O subtotal por dia e os cards de resumo somam valores do período **sem conversão de
  moeda** (usam a moeda principal na formatação). Em contas multi-moeda no mesmo dia o
  subtotal mistura moedas — mesma limitação que a soma anterior da tela tinha; não
  introduzi conversão nova para não inventar regra financeira. Vale um follow-up se o
  uso multi-moeda for real.

## 7) Remaining
- Teste manual no servidor (ver TESTE no progress).
- Confidence: 87% (pendente `php -l` + verificação visual).
