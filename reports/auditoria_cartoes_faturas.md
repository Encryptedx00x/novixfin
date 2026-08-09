# Auditoria — Modelo de Cartões e Faturas (2026-08-08)

**Escopo:** somente leitura. Nenhum arquivo de código foi alterado.
**Pergunta de fundo:** como o app amarra hoje *compra → fatura → cartão → banco*,
o que fica obscuro na tela, e o que precisaria mudar para o usuário controlar
"em qual fatura/cartão/banco cai cada gasto" e navegar isso no tempo.

**Resumo em uma frase:** o vínculo compra→fatura **existe e é persistido**
(`transactions.bill_id`), mas a fatura é escolhida pelo **mês-calendário da
data da compra** — `closing_day` **não participa dessa decisão** —, faturas
futuras praticamente não existem, e a tela de Cartões nunca mostra *quais*
transações compõem cada fatura. O "obscuro" que você percebeu é, em boa parte,
UI; mas há dois pontos que são de **modelo**.

---

## 1. Modelo de dados

### Tabelas
Fonte: `migrations/20240101000000_baseline_v3.sql`.

**Não existe tabela `cards`.** Um cartão **é** uma linha de `accounts` com
`type = 'credit'`:

| `accounts` (colunas relevantes) | Papel |
|---|---|
| `type ENUM('bank','cash','investment','credit','other')` | `'credit'` marca o cartão |
| `credit_limit DECIMAL(15,2) NULL` | limite |
| `closing_day TINYINT NULL` | dia de fechamento |
| `due_day TINYINT NULL` | dia de vencimento |
| `interest_rate DECIMAL(5,2) NULL` | juros de atraso |
| `balance DECIMAL(15,2)` | saldo — no cartão, fica **negativo** conforme os gastos |

`credit_card_bills`:

| Coluna | Observação |
|---|---|
| `account_id` → `accounts.id` | o cartão |
| `billing_month VARCHAR(7)` | `'YYYY-MM'` — **a chave lógica da fatura** |
| `closing_date DATE`, `due_date DATE` | calculadas na criação |
| `total_amount`, `paid_amount`, `interest_amount`, `fine_amount` | |
| `status ENUM('open','paid','overdue','cancelled')` | |
| `UNIQUE (account_id, billing_month)` | **uma fatura por cartão por mês-calendário** |

`transactions.bill_id INT UNSIGNED NULL` → FK para `credit_card_bills(id)`,
`ON DELETE SET NULL`, com índice `idx_bill_id`.

`credit_card_transaction_settings`: por cartão, dois toggles —
`register_bill_as_expense` (padrão **true**) e `register_limit_as_income`
(padrão false). Governam o que o pagamento de fatura gera de transações.

### Como a transação é associada à fatura
**É persistido, não derivado na exibição.** `transactions.bill_id` é gravado no
momento da criação (`actions/transactions/create.php:233-300`) e recalculado na
edição (`actions/transactions/update.php:255-315`). Quem resolve é
`CreditCardBillRepository::findOrCreateBill($accountId, $userId, $billingMonth)`
— *find or create*: a fatura passa a existir no primeiro gasto daquele mês.

`credit_card_bills.total_amount` **não é digitado**: é sempre
`SUM(amount) WHERE bill_id = X AND type = 'expense'`, recalculado por
`updateBillTotal()` a cada criação/edição/exclusão de transação
(`CreditCardBillRepository.php:120-200`). Isso é bom — o total da fatura é
derivado das transações, então não há como divergir.

### ⚠️ Achado central: `closing_day` NÃO decide em qual fatura o gasto cai
No caminho normal (compra efetivada), `create.php:290-292` faz:

```php
$billingMonth = $dateForBilling->format('Y-m');   // $dateForBilling = data da compra
$bill = $billRepository->findOrCreateBill($accountId, $userId, $billingMonth);
```

Ou seja: **a fatura é o mês-calendário da data da compra**. `closing_day` é
usado *depois*, e só para preencher `closing_date`/`due_date` da fatura recém-criada
(`CreditCardBillRepository.php:57-75`).

Consequência concreta: cartão que fecha dia 24; compra em **28/jan**. O sistema
põe a compra na fatura `2026-01`, cuja `closing_date` é **24/jan** — a compra
fica registrada numa fatura que **já havia fechado antes dela acontecer**. No
mundo real ela cairia na fatura de fevereiro. O usuário vê o gasto na fatura
errada e o total de janeiro estufado.

Isso vale para todo gasto entre `closing_day+1` e o fim do mês — numa fatura que
fecha dia 5 ou 10, é a *maioria* dos dias do mês.

Há um único caminho que tenta ser mais esperto, e só para transação
**não efetivada com data de efetivação** (`create.php:238-286`): pega a fatura
aberta mais próxima e, se a data de efetivação for depois do vencimento dela,
joga para `billing_month + 1 mês`. É uma heurística paralela, baseada em
`due_date` (não em `closing_date`), que não cobre o caso comum.

### Ciclo e faturas futuras
- `closing_date` = `min(closing_day, 28)` do mês da fatura. O `min(...,28)`
  significa que **cartão que fecha dia 30/31 é tratado como dia 28**.
- `due_date`: se `due_day > closing_day`, vence no mesmo mês; senão, no mês
  seguinte. Regra razoável.
- **Não há geração antecipada de faturas.** Uma fatura só nasce quando (a) uma
  transação daquele mês é criada, ou (b) alguém abre a tela de Cartões — porque
  `getCurrentBill()` chama `findOrCreateBill(date('Y-m'))`, criando a fatura do
  mês corrente como efeito colateral da visita. `listByAccount()` lista
  `ORDER BY billing_month DESC LIMIT 12`, ou seja, **olha para trás**.

---

## 2. Fluxo atual (compra → fatura → pagamento)

### Ao lançar uma despesa num cartão
1. Valida o limite (`create.php:206-231`) — soma só despesas **efetivadas** do
   cartão, sem recortar por fatura, e compara com `credit_limit`.
2. Resolve a fatura pelo mês-calendário (seção 1) e grava `bill_id`.
3. `TransactionRepository::create()` insere e, **se efetivada**, faz
   `accounts.balance = balance - amount` **na conta do cartão**
   (`TransactionRepository.php:200-206`). O saldo do cartão fica negativo.

**O usuário não escolhe a fatura.** O formulário não tem esse campo (seção 3).

### `pay-bill.php`
Tudo dentro de **uma transação PDO** (commit/rollback atômico, `pay-bill.php:198+`)
— invariante respeitada. O que ele faz:

1. **Débito na(s) conta(s) bancária(s)** — opcional, via `account_debits[]`:
   cria uma despesa efetivada por conta, descrição
   `"Pagamento de fatura - <cartão>"`, **`bill_id = NULL`**. É aqui que o
   dinheiro sai do banco. Se o usuário não marcar essa opção, **nenhuma conta é
   debitada** — a fatura é marcada como paga sem contrapartida bancária.
2. **`register_bill_as_expense`** (padrão **ligado**): cria uma despesa de
   `total_with_interest` **na conta do cartão**, descrição
   `"Fatura do cartão - <cartão>"`, `bill_id = <fatura>`.
3. **`register_limit_as_income`** (padrão desligado): cria uma receita do valor
   do `credit_limit` **na conta do cartão**.
4. `markAsPaid()` grava `paid_amount`, `status`, `paid_at`.

⚠️ **Observação sobre o passo 2.** Essa despesa entra na conta do **cartão** e
tem `bill_id` da própria fatura. Dois efeitos:
- ela **volta a somar em `updateBillTotal()`** (que soma tudo com aquele
  `bill_id` e `type='expense'`), então o total da fatura cresce depois de paga;
- o `balance` do cartão fica *mais* negativo ao pagar, quando a intuição diz o
  contrário. O toggle "registrar limite como receita" parece existir justamente
  para compensar isso à mão. **É a área mais confusa do modelo hoje** e merece
  uma decisão explícita antes de qualquer mudança de UI.

### `revert-payment.php`
Desfaz o pagamento identificando as transações auxiliares por **prefixo de
descrição + data (`paid_at`) + presença/ausência de `bill_id`**
(`revert-payment.php:100-160`) e chamando `TransactionRepository::delete()` em
cada uma, o que reverte saldo e total da fatura.

⚠️ **Fragilidade real:** o vínculo pagamento→transações auxiliares é
**por string de descrição**, não por chave. Renomear o cartão entre o pagamento
e a reversão quebra o match — as transações ficam órfãs e o saldo não volta. O
código trata o caso legado (`paid_at` nulo) avisando o usuário, mas não o caso
do rename. Faltam colunas explícitas de vínculo.

### Parcelamento
Modelo (migração `20240101000006_add_recurrence_installments_v3.php`):
`transactions.parent_transaction_id`, `installment_index`,
`recurrence_total_occurrences`. **Parcela 1/N é a transação-pai**, criada na
compra; **2/N…N/N são transações reais**, materializadas por
`RecurringService::generateForParent()` — cada uma com seu próprio `bill_id`,
resolvido por `findOrCreateBill(..., $cursor->format('Y-m'))`
(`RecurringService.php:197-208`). Então sim, **caem em faturas de meses
diferentes**, uma linha por parcela.

⚠️ **Mas elas só existem depois que o cron passa.** `generateDue()` usa
`$upTo ??= new DateTimeImmutable('today')` (`RecurringService.php:70`) e o cron
roda diariamente. Uma compra em 6x feita hoje cria **1 linha**; as outras 5
**não existem no banco** até o mês delas chegar. É por isso que a previsão de
saldo do dashboard diz, corretamente, que "não projeta parcelas que ainda não
foram lançadas".

---

## 3. O que está obscuro na visualização

### No formulário de lançamento
Os dois modais (`dashboard.php:1646-1656` e `2009-2019`) montam o select de
conta assim:

```sql
SELECT id, name, icon FROM accounts WHERE user_id = :user_id AND is_active = 1 ORDER BY name ASC
```

e renderizam **apenas `ícone + nome`**. Portanto:

- ❌ **`type` não é lido nem exibido** — um cartão de crédito é visualmente
  **indistinguível** de uma conta corrente na lista. Só o nome que o usuário deu
  ("Nubank") diferencia, e "Nubank" pode ser conta *e* cartão.
- ❌ **Não existe campo de fatura.** Nenhum `bill_id` no formulário.
- ❌ Nenhum aviso de "isso vai para a fatura de fev/2026" antes ou depois de salvar.
- ❌ O select ignora `type='credit'` mesmo para receitas, onde cartão raramente faz sentido.

**O usuário lança um gasto e não recebe nenhum sinal de em qual fatura ele caiu.**

### Na tela de Cartões (`public/credit-cards.php`)
Mostra por cartão: nome/ícone, `Limite: R$ X`, `Fechamento: dia N • Vencimento:
dia M`, o bloco "Fatura Atual" (fechamento, vencimento, total, juros, já pago,
restante, status) e um "Histórico de Faturas" com até 12 meses **para trás**.

O que **não** mostra:

- ❌ **Quais transações compõem a fatura.** Não há uma única query de
  `transactions … WHERE bill_id = …` na página inteira. A fatura é um total
  opaco — impossível auditar "por que estou devendo isso".
- ❌ **Limite usado / disponível.** Exibe só o limite nominal.
- ❌ **Qualquer vínculo com banco.** Não há como saber de qual conta a fatura
  costuma ser paga (ver seção 4).
- ❌ Parcela como parcela: não há "2/6" em lugar nenhum, embora
  `installment_index` e `recurrence_total_occurrences` existam na tabela.

### No histórico (`public/history.php`)
Mostra `ícone + nome da conta` (linhas 381 e 459) e **nada sobre fatura**. Um
gasto no cartão e um gasto no débito são indistinguíveis na listagem.

### Dois pontos de manutenção que apareceram no caminho
- `public/credit-cards.php:30-45` ainda faz **`CREATE TABLE` em runtime** de
  `credit_card_transaction_settings`. O AGENTS.md registra que esses CREATEs
  defensivos foram removidos em favor da migração canônica — o de
  `pay-bill.php` foi, este ficou. Cria uma tabela **sem as FKs** da definição
  canônica se rodar antes da migração.
- `credit-cards.php` está inteiro no Tailwind antigo (`bg-slate-900`,
  `text-violet-500`), **fora do reskin tokenizado**. Qualquer trabalho de UI
  aqui é também retrabalho de tema.

---

## 4. Lacunas: o que é UI e o que é MODELO

### (a) Ver a fatura de meses futuros → **MODELO** (parcialmente)
Faltam duas coisas distintas:
1. **A fatura futura não existe como linha.** Só nasce por gasto naquele mês ou
   por visita à tela. Não há geração antecipada nem cálculo "fatura de N meses à
   frente". *(Contornável por UI: dá para projetar em memória sem gravar.)*
2. **As parcelas futuras não existem como transação** até o cron materializá-las
   (`$upTo = today`). Esta é a lacuna dura: nenhuma tela consegue mostrar
   "quanto já está comprometido em março" porque **o dado não existe**. O
   compromisso está implícito em `recurrence_total_occurrences` do pai.

### (b) Escolher / mover em qual fatura um gasto cai → **MODELO + UI**
- O **dado existe** (`bill_id` é uma coluna real e editável) e `update.php` já
  sabe re-vincular e recalcular os dois totais afetados. Mover um gasto de
  fatura é, tecnicamente, barato.
- Mas **não há como expressar a escolha**: o formulário não tem o campo, e a
  resolução automática é sempre recalculada a partir da data na edição — ou
  seja, uma escolha manual do usuário seria **sobrescrita** no próximo save.
  Falta um "não recalcule, o usuário decidiu" (uma flag tipo `bill_locked`, ou
  tratar `bill_id` vindo do form como autoritativo).
- E, antes disso, **falta a regra de ciclo correta** (seção 1): sem usar
  `closing_day`, o automático continuará colocando gastos na fatura errada e o
  usuário vai ter de corrigir na mão o tempo todo.

### (c) Ver cartão ↔ banco ↔ fatura → **MODELO** (o dado não existe)
**Não há nenhuma ligação entre um cartão e um banco no schema.** `accounts` não
tem `parent_account_id`, `issuer_id` nem `default_payment_account_id`. Cartão e
conta são linhas irmãs, sem parentesco. `wallets`/`wallet_accounts` é um
agrupamento genérico N:N criado pelo usuário — não modela emissor.

Hoje, a única pista de qual banco pagou uma fatura é a transação
`"Pagamento de fatura - <cartão>"` na conta debitada — **descoberta por texto**,
com `bill_id = NULL`, isto é, sem vínculo consultável com a fatura.

### Caminho recomendado (não implementado — só o mapa)

**Etapa 1 — UI pura, dado já existe, risco baixo.** Não toca em invariante nenhuma:
1. Marcar o tipo no select de conta ("💳 Nubank — cartão") e, ao escolher um
   cartão, exibir *"vai para a fatura de <mês>, vence em <data>"*.
2. **Listar as transações de cada fatura** na tela de Cartões
   (`WHERE bill_id = :id`) — provavelmente o maior ganho de clareza por
   unidade de esforço, e é uma query nova numa página só.
3. Mostrar limite usado/disponível e exibir `installment_index/total` como "2/6".
4. Marcar cartão/fatura no histórico.

**Etapa 2 — corrigir o ciclo. MODELO, mas sem schema.** Fazer a escolha da
fatura respeitar `closing_day`: `data > closing_date` ⇒ próxima fatura. É
mudança de **lógica** (`create.php`, `update.php`, `RecurringService`), sem
`ALTER TABLE`. **Cuidado:** altera o `bill_id` que *novas* transações recebem, e
qualquer backfill em transações existentes mexeria em `total_amount` de faturas
possivelmente já pagas. Recomendo aplicar só a partir da data da mudança e
deixar o passado intacto, com teste dedicado antes (o repo tem precedente:
`tests/recurrence_overflow.php`).

**Etapa 3 — schema, mais caro, faseável.** Cada item é independente:
- `accounts.default_payment_account_id` (cartão → banco): resolve (c) e permite
  pré-selecionar a conta no pagamento. Migração aditiva, nullable, sem risco
  para dado existente.
- `transactions.bill_locked BOOLEAN` (ou equivalente): torna a escolha manual de
  fatura durável contra o recálculo. Necessário para (b).
- Vínculo explícito pagamento→auxiliares (ex.: `transactions.paid_bill_id`),
  substituindo o match por descrição em `revert-payment.php` — corrige a
  fragilidade do rename de cartão.
- Materializar parcelas futuras (gerar as N no ato da compra, ou estender o
  horizonte do cron). **É a de maior impacto e maior risco**: mexe em
  `RecurringService`, que o AGENTS.md protege explicitamente, e muda o que a
  previsão de saldo enxerga. Só com reprodução escrita e plano de validação.

**Decisão que precede tudo:** o que "registrar fatura como despesa" e "registrar
limite como receita" deveriam significar. Enquanto o pagamento de uma fatura
gerar uma despesa na própria conta do cartão que volta a somar no total da
fatura, qualquer visualização mais detalhada vai expor essa contabilidade em vez
de esclarecê-la.

---

## Referências
- `migrations/20240101000000_baseline_v3.sql` — `accounts`, `credit_card_bills`,
  `credit_card_transaction_settings`, `transactions`
- `migrations/20240101000006_add_recurrence_installments_v3.php` — parcelas
- `src/Repositories/CreditCardBillRepository.php` — ciclo, totais, juros, pagamento
- `src/Repositories/TransactionRepository.php:110-232` — create + mutação de saldo
- `src/Services/RecurringService.php:144-240` — parcelas e vínculo com fatura
- `actions/transactions/create.php:200-300` — resolução da fatura na compra
- `actions/transactions/update.php:255-315` — re-vínculo na edição
- `actions/credit-cards/pay-bill.php` — pagamento atômico
- `actions/credit-cards/revert-payment.php:100-160` — reversão por descrição
- `public/credit-cards.php` — tela de cartões
- `public/dashboard.php:1646-1656`, `2009-2019` — selects de conta dos formulários

**Confiança: 90%.** Tudo acima veio de leitura direta do código. Os 10% são
comportamento em produção que não consigo verificar daqui (sem PHP nem banco
nesta máquina): em particular, o efeito acumulado dos toggles
"fatura como despesa"/"limite como receita" sobre `accounts.balance` de um
cartão real merece uma conferência com dados antes de qualquer mudança.
