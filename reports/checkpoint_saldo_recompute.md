# Checkpoint — Saldo desnormalizado (accounts.balance) descolado das transações

Data: 2026-07-27

## Task
Corrigir accounts.balance que descolou das transações (ex.: conta 29 Financiamento Carro
balance −1320 com 0 transações; conta 8 balance 12230 vs real 14280). Exclusão não revertia o
saldo. PARTE 1: impedir novo descolamento (handlers atômicos). PARTE 2: recompute idempotente
com dry-run/--apply. NÃO apagar transações. Máximo cuidado (mexe em saldo).

## PARTE 1 — Auditoria dos handlers (causa)
**Modelo de saldo**: 100% dirigido por transações. Contas nascem com `balance = 0.00`
(actions/accounts/save.php insere 0.00 sempre) — NÃO há saldo de abertura fora de transações.
Logo, `balance` deve ser exatamente a soma das transações efetivadas.

**Fórmula canônica** (idêntica em todos os handlers): income → `+amount`; expense → `−amount`;
somente quando `is_effective = 1`. Vale para todos os tipos, inclusive `credit` (as transações
da conta do cartão — fatura como despesa, limite como receita — dirigem o saldo pela mesma regra).

**Handlers auditados:**
| Handler | Reverte/ajusta saldo? | Atômico (BEGIN/COMMIT)? |
|---|---|---|
| TransactionRepository::create | sim (+ se efetivada) | sim (guard inTransaction) |
| TransactionRepository::update | sim (remove antigo, aplica novo) | **NÃO → CORRIGIDO** |
| TransactionRepository::delete | sim (reverte se efetivada) | sim |
| deleteByAccount / deleteAll | sim (soma reversões) | sim |
| pay-bill.php | sim (via repo->create) | sim (wrap próprio) |
| save-transaction-settings.php | sim (flip is_effective + ajuste na mesma convenção) | parcial (não é o alvo) |
| auto-effective.php | sim (marca efetiva + soma) | — |
| accounts/delete.php | conta apagada junto (saldo some) | n/a |

**Conclusão**: os handlers ATUAIS já ajustam o saldo corretamente; a ÚNICA lacuna era o
`update()` não estar envolto em transação (UPDATE + ajuste de saldo podiam ficar parciais numa
falha de PDO). **CORRIGIDO**: `update()` agora abre BEGIN/COMMIT com guard `inTransaction()`,
igual a create/delete.

**O descolamento observado é RESIDUAL HISTÓRICO**: deletes pré-v3 não revertiam o saldo
(documentado nos próprios comentários "Pre-v3 deleted rows and left accounts.balance inflated/
deflated"). O código atual não gera novo descolamento — por isso a PARTE 2 corrige o estado
acumulado.

## PARTE 2 — Recompute (corrige o estado atual)
NEW `scripts/recompute-balances.php` (CLI-only, 403 via HTTP; idempotente):
- Calcula por conta: `balance = SUM(income efetivada) − SUM(expense efetivada)` (LEFT JOIN
  transactions, GROUP BY account) — a fórmula canônica.
- DRY-RUN por padrão: imprime relatório `id | user | conta | tipo | antes | depois | diff` só
  das contas descoladas (tolerância 0,005) e NÃO grava.
- `--apply`: grava os saldos recalculados dentro de UMA transação (rollback em falha).
- NÃO apaga nem altera transações — só `accounts.balance`.

## Files changed
- **M** src/Repositories/TransactionRepository.php — update() envolto em transação atômica.
- **NEW** scripts/recompute-balances.php — recompute dry-run/--apply.

## NÃO alterado
create.php/update.php (handlers de rota), names, CSRF, lógica de sinal (mantida a canônica), e
NENHUMA transação é apagada.

## Risks
- Baixo no código (só atomicidade do update, sem mudar a fórmula).
- O `--apply` MEXE EM SALDO: exige BACKUP antes e revisão do dry-run. Executado no servidor.
- Se alguma conta tiver saldo de abertura legítimo (não é o caso hoje — sempre 0.00), o recompute
  o sobrescreveria; documentado para reavaliar se um dia houver opening balance.

## Validation performed
- Estático: chaves/parênteses/try-catch balanceados em ambos os arquivos.
- `php -l` e DRY-RUN: PENDENTES no servidor (PHP/DB indisponíveis localmente).

## A EXECUTAR NO SERVIDOR (ordem)
1. `php -l scripts/recompute-balances.php src/Repositories/TransactionRepository.php`
2. BACKUP: `mariadb-dump -u <user> -p <db> accounts > backup_accounts_$(date +%F).sql`
3. DRY-RUN: `php scripts/recompute-balances.php`  → colar o relatório antes→depois para revisão.
4. `php scripts/recompute-balances.php --apply`
5. DRY-RUN de novo → confirmar "Nenhuma conta descolada" (diff 0 em todas).
6. App: criar/editar/EXCLUIR uma transação e conferir que o balance da conta e o hero do
   dashboard recalculam corretos. Tema claro e escuro.

## Confidence
82% — auditoria conclusiva e recompute correto pela fórmula canônica; confiança sobe após o
dry-run real no servidor confirmar os números (ex.: conta 8 12230→14280, conta 29 −1320→0).
