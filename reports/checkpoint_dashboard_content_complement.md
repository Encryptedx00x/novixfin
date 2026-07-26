# Checkpoint — Complemento da fatia DASHBOARD (Movimentações + Conquistas)

Data: 2026-07-25

## Tarefa
Aditivo à fatia anterior: re-adicionar as duas seções que o brief anterior mandou
remover por engano (o design da tela DASHBOARD as inclui como RESUMO/atalho):
Movimentações recentes e Conquistas recentes. Sem desmontar hero, 3 KPIs, abas,
previsão, metas. Parar para revisão.

Referência: Claude Design "NovixFin App.html", bloco TELA: DASHBOARD, linhas
483-534 do cache `scratchpad/app-src.html` (via DesignSync).

## Files changed
- `public/dashboard.php` — duas seções adicionadas DENTRO da aba "Visão geral",
  após o grid previsão+metas (posição do design). Nada mais tocado.

## O que foi adicionado
### 1) Movimentações recentes (resumo)
- Reaproveita `$recentTransactions` (o MESMO array já usado pelo bloco JSON do
  rodapé; guardado por `isset` -> SEM nova query no data-prep). Slice de 8.
- Agrupado por dia com subtotal assinado (income +, expense -), cor --pos/--neg.
- Linha: quadrado rotacionado com a inicial da categoria, descrição + categoria,
  conta, pill de status, valor (--f-num), ação.
- Pill de status pelos tokens corrigidos: Efetivada = --pos; Pendente = --gold
  (o gold que a auditoria pegou), fundo via color-mix.
- Link "Ver histórico completo ->" (history.php). É resumo: sem filtros nem
  paginação.
- **Sem coluna de horário**: `transaction_date` é DATE e `listByUser` não seleciona
  hora; o horário do mock foi omitido em vez de inventado.

### 2) Efetivar direto na linha (reuso de handler)
- NÃO existe endpoint de efetivar-1-clique no repo (só o cron auto-effective.php).
  A tela Histórico efetiva um pendente pela edição -> `update.php`, que já reverte/
  aplica o efeito no saldo na transição de `is_effective`.
- Cada linha PENDENTE (com `account_id`) traz um botão "Efetivar" que envia um form
  oculto para `/actions/transactions/update.php` com TODOS os campos atuais da
  transação + `is_effective=1` + `_token` (CSRF) + `redirect_to=dashboard.php`.
  Campos de recorrência/parcela são repassados quando existem. Reuso puro do
  handler; nenhum novo endpoint. Saldo do hero recalcula no reload.
- Botão só aparece quando há `account_id` (update.php exige conta válida).
- Linhas efetivadas mostram o botão de editar (`data-edit`), que já é ligado por
  dashboard.js ao modal `#modal-edit-dashboard`.

### 3) Conquistas recentes (resumo) — dados REAIS
- Fonte: `$user['points']` + `$user['badges']` (JSON) +
  `\Novix\Services\GamificationService::BADGE_MILESTONES` — a MESMA do Perfil.
  NÃO usa o mock `$achievements` (Primeira Meta/Economista) do data-prep.
- Mostra só emblemas conquistados (`in badges` OU `points >= threshold`), limiar
  maior primeiro, até 4. Cada card: ícone SVG (sem emoji), rótulo "Emblema", nome
  e critério ("Concedida ao alcançar X pontos").
- Cores por token (--accent/--pos/--gold/--accent-2). Estado vazio honesto quando
  não há emblema.
- Link "Ver perfil ->" (profile.php).

## Preservado
- Hero, 3 KPIs, abas Visão geral/Kanban, Previsão (#chartForecast lendo tokens),
  Metas em andamento, FAB — intactos.
- data-prep (1-872) e os 3 forms (create no modal do FAB, update, delete) com CSRF
  intactos. Nenhuma migração.
- `$achievements` (mock) segue calculado no data-prep mas não é mais consumido pela
  view — candidato a limpeza numa passada futura (não removido agora para manter o
  diff cirúrgico).

## Qualidade
--f-num em todos os valores; --accent-text/tokens no texto pequeno colorido; chip
de pendente corrigido para --gold; ícones de conquista em SVG (sem emoji); focus-
visible herdado do escopo .dash-screen; sem travessões nos textos novos.

## Validação
- Local: PHP indisponível. Estático OK:
  - `<?php`/`?>` 158/158; chaves 146/146; parênteses 1077/1077.
  - Placeholder quebrado removido; loops alternativos (grupos/linhas/badges) com
    endforeach correspondentes; `GamificationService::BADGE_MILESTONES` é `public
    const`; FQN autoloada via bootstrap.
  - 1 novo form de efetivar (POST update.php, is_effective=1, CSRF).
- Servidor (pendente): `php -l public/dashboard.php` + `php tests/smoke.php`.

## TESTE (servidor)
1. Movimentações aparecem agrupadas por dia com subtotal.
2. "Efetivar" numa pendente funciona (via update.php) e o saldo do hero recalcula
   no reload.
3. Conquistas reais aparecem com "Ver perfil"; usuário sem emblema vê o estado
   vazio.
4. Hero/KPIs/previsão/metas intactos; um tema escuro e um claro; demais telas sem
   regressão.

## Confiança
82% — inserção aditiva consistente e contratos verificados; pende `php -l` + smoke
no servidor e o passe visual.
