# Checkpoint — Dupla contagem na "Previsão de saldo · julho"

Data: 2026-07-30

## 1. Arquivo e função onde o cálculo é feito

`public/dashboard.php`, bloco "Previsão de saldo" (antes nas linhas **953-961**). Não é uma
função nomeada — é código de preparação de dados no topo da página. Variáveis envolvidas:

| Variável | Onde | O que era |
|---|---|---|
| `$consolidatedBalance` | dashboard.php:891-898 | Σ `accounts.balance` de todas as contas **exceto `credit`** |
| `$extendedHistoryData` | dashboard.php:334 | `TransactionRepository::monthlyHistory($userId, 12, $onlyEffective)` |
| `$onlyEffective` | dashboard.php:45 | `= $filterIsEffective` → **`null` por padrão** (todas as transações) |
| `$monthIncome/$monthExpense` | dashboard.php:905-910 | último ponto de `$extendedHistoryData` (mês corrente) |
| `$projNet` | dashboard.php:960 | **média móvel** `$avgIncomeF - $avgExpenseF` |
| `$previstoSaldo` | dashboard.php:961 | `$consolidatedBalance + $projNet` |

## 2. Causa — a hipótese estava certa no efeito, errada no mecanismo

O enunciado supôs que o "+ R$ 249,72 projetado" fosse, por design, o *líquido total do mês*.
**Não é.** O código real era:

```php
$fSlice     = array_slice($extendedHistoryData, -min(3, count($extendedHistoryData)));
$avgExpenseF= array_sum(array_column($fSlice,'expense')) / $fN;
$avgIncomeF = array_sum(array_column($fSlice,'income'))  / $fN;
$projNet       = $avgIncomeF - $avgExpenseF;   // média móvel de até 3 meses
$previstoSaldo = $consolidatedBalance + $projNet;
```

`$projNet` era a **média móvel dos últimos até 3 meses**. A dupla contagem acontece porque:

1. `$onlyEffective` é **`null`** por padrão (dashboard.php:45), então `monthlyHistory` **não
   filtra `is_effective`** — inclui efetivados **e** pendentes.
2. `monthlyHistory` agrupa por `DATE_FORMAT(t.transaction_date, "%Y-%m")` nos últimos 12 meses,
   **incluindo o mês corrente**.
3. Logo o último elemento da janela é julho **inteiro** (efetivados + pendentes).
4. `$consolidatedBalance` é a soma de `accounts.balance`, que por contrato contém **somente
   efetivados** (ver `scripts/recompute-balances.php`).
5. Somar os dois conta a parte efetivada de julho **duas vezes**.

**Com apenas 1 mês de histórico** (o caso do arquivo exportado), a média sobre 1 elemento
**degenera no líquido do mês inteiro**: `$projNet = 5.167,80 − 4.918,08 = 249,72`, e
`$previstoSaldo = 196,90 + 249,72 = 446,62` — exatamente o valor visto na interface.
Confirmado, não assumido.

### Respostas às verificações pedidas
- **O que a variável de 249,72 significava**: média móvel de até 3 meses de receitas menos
  despesas — que, com um mês de dados, é o **líquido total do mês** (efetivados + pendentes).
  Não era "só pendentes".
- **A previsão somava o saldo a um valor que já inclui os efetivados?** Sim.
- **`transaction_date` × `effective_date` × `is_effective` × mês do filtro**: a agregação usa
  **`transaction_date`** (`monthlyHistory`); `effective_date` **não participa** deste cálculo
  (serve para a efetivação automática via cron); `is_effective` só era aplicado se o usuário
  filtrasse na URL (`?is_effective=`), o que **não é o padrão**. O mês vem de `CURDATE()`.
- **Agregação de contas**: `$consolidatedBalance` soma **todos os tipos exceto `credit`** —
  `bank`, `cash` (dinheiro em espécie), `investment` e `other` entram corretamente
  (dashboard.php:893-898). Cartão de crédito é passivo e vive na tela Cartões.

## 3. Valores intermediários encontrados

| Grandeza | Valor |
|---|---|
| Receitas efetivadas | 264,80 |
| Despesas efetivadas | 67,90 |
| **Saldo realizado** (`$consolidatedBalance`) | **196,90** |
| Receitas pendentes | 4.903,00 |
| Despesas pendentes | 4.850,18 |
| **Líquido pendente** | **52,82** |
| Receitas totais do mês | 5.167,80 |
| Despesas totais do mês | 4.918,08 |
| Líquido total do mês (`$projNet` antigo) | 249,72 |
| `$previstoSaldo` antigo (bug) | **446,62** |
| `$previstoSaldo` corrigido | **249,72** |

## 4. Correção proposta e aplicada (mínima, Abordagem A)

`previsão = saldo realizado + (receitas pendentes − despesas pendentes)`

- `$projNet` passa a ser o líquido **somente dos pendentes** do mês corrente, via SQL com
  `is_effective = 0`, mês de `transaction_date` = mês de `CURDATE()`, moeda principal e contas
  ativas (mesmos critérios das outras consultas do dashboard).
- A **média móvel foi mantida** onde continua legítima: o cálculo do nível de confiança
  (`$devF = abs($avgExpenseF - $monthExpense)`) — nenhuma regra não relacionada foi alterada.
- Escolhi a **Abordagem A** e não a B porque B (`receitas do mês − despesas do mês`) só coincide
  quando o saldo das contas começa em zero e todos os efetivados são do mês corrente; fora
  disso ela ignora o saldo trazido de meses anteriores. Neste arquivo as duas dão 249,72.

### Efeito na interface
O selo passa a exibir **+ R$ 52,82 projetado** (o que ainda falta acontecer) e a
**Previsão de saldo = R$ 249,72**. A UI não mostra mais 446,62.

## 5. Patch

- **NEW `includes/forecast.php`** — helpers puros: `novix_sum_transactions()`,
  `novix_pending_net()`, `novix_pending_net_totals()`, `novix_forecast_balance()`.
- **M `public/dashboard.php`** — `require_once` do helper; `$projNet` vem do SQL de pendentes;
  `$previstoSaldo = novix_forecast_balance(...)`; comentário explicando a dupla contagem.

## 6. Testes automatizados

**NEW `tests/forecast_balance.php`** (CLI, sem Composer/PHPUnit, no estilo do repo):

1. **Somente efetivados** — realizado 196,90; previsão = realizado.
2. **Somente pendentes** — líquido 1.690,00 sobre saldo 0.
3. **Mistura** (o arquivo do usuário) — realizado 196,90, pendente 52,82, totais 5.167,80 /
   4.918,08, **previsão 249,72**.
4. **Prevenção de dupla contagem** — reproduz o bug (446,62), afirma que a previsão **não** pode
   ser esse valor, confirma que A e B coincidem neste dataset e verifica a invariante
   `previsão − realizado == líquido pendente`.
5. **Receitas e despesas** isoladas.
6. **Saldo bancário negativo** — inclusive negativo agravado por pendentes.
7. **Dinheiro em espécie** agregado com `bank`/`other`/`investment`.
8. **Ausência de pendências** — previsão == saldo realizado.
9. **Caminho de totais agregados** (o que o SQL do dashboard devolve).

## Validation performed
- Aritmética dos 9 blocos simulada e conferida contra os critérios de aceite: realizado
  **196,90**, pendente **52,82**, previsto **249,72**, bug **446,62**. Todos batem.
- Sintaxe estática OK: `includes/forecast.php` (11/11 chaves, 22/22 parênteses),
  `tests/forecast_balance.php` (9/9, 121/121), `public/dashboard.php` (157/157, 1126/1126,
  php 158/158).
- **PHP indisponível localmente** → `php -l` e a execução do teste ficam para o servidor.

## Remaining work (servidor)
```bash
php -l includes/forecast.php public/dashboard.php tests/forecast_balance.php
php tests/forecast_balance.php     # deve terminar com "OK: N verificações passaram."
```
Depois, no dashboard: conferir **Saldo consolidado R$ 196,90**, selo **+ R$ 52,82 projetado** e
**Previsão de saldo R$ 249,72** — e que **446,62 não aparece**.

## Risks
- O selo muda de significado (média móvel → líquido pendente): é a correção pedida, mas é uma
  mudança visível de número na UI.
- A previsão passa a considerar apenas pendentes **do mês corrente**; pendentes datados em meses
  futuros não entram na previsão de julho (coerente com o rótulo "· julho").
- Nenhuma alteração em handlers, saldo, CSRF ou regras não relacionadas.

## Confidence
92% — a causa foi rastreada no código real (não assumida), reproduz exatamente o 446,62
relatado, e a correção é aritmeticamente verificada contra os critérios de aceite. Falta apenas
a execução real no servidor.
