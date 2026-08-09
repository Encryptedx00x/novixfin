# Execução overnight — 2026-08-08

**Resultado: a fila PAROU na Task 0.1.** Nenhuma task foi concluída. Nenhuma
mudança de lógica financeira foi feita. Nenhum deploy foi feito.

---

## Por que parou

A fila inteira é construída sobre um critério de aceite que **não é executável
nesta máquina**: toda task exige `php -l` e/ou `php tests/run.php`, e a Fase B
exige testes financeiros conferindo **valores exatos ao centavo**.

### Bloqueio 1 — não existe PHP nesta máquina
Verificado:

| Checagem | Resultado |
|---|---|
| `which php php8 php8.1 php8.3` (Git Bash) | nada |
| `Get-Command php` (PowerShell) | `php NOT on PATH` |
| `/c/php`, `/c/xampp/php`, `/c/laragon/bin/php`, `/c/wamp64/bin/php`, `C:\Program Files\php` | não existem |

Isto é consistente com o histórico do repo: o `state/progress.md` registra
"local PHP may be unavailable on the dev machine" e **todos** os checkpoints
anteriores terminam com "falta rodar `php -l` no servidor". O AGENTS.md prevê o
caso na seção "Validation Rules".

Sem PHP: não dá para rodar `php tests/run.php` (aceite da 0.1), nem `php -l`
(aceite de toda task de UI), nem os testes de valor exato das Tasks B1–B5.

### Bloqueio 2 — não existe banco de teste isolado
A Task 0.1 pede "banco de teste isolado — nunca escreva no banco real de
produção nos testes". Nesta máquina:

- o único banco configurado é o do `.env`, que aponta para o **MariaDB remoto de
  produção** (evidência desta sessão: `tests/probe_currency_save.py` falhou com
  `pymysql.err.OperationalError (1045) Access denied for user
  'u545165688_NovixADM'` — ou seja, os testes tentam o banco real e nem acesso
  têm a partir deste IP);
- não há MySQL/MariaDB local para receber `novixfin_test`.

Mesmo com PHP instalado, as factories e os testes de saldo/fatura das Fases 0.2
e B **não teriam onde rodar** sem um banco de teste.

### Por que não avancei mesmo assim
As REGRAS GLOBAIS do TASKS.md mandam parar no primeiro erro e **não contornar**.
Avançar significaria escrever as Tasks B2 e B5 — que alteram invariantes de
saldo, pagamento e reversão de fatura — **sem conseguir rodar um único teste**.
É exatamente o cenário que a própria fila proíbe ("se um valor não bate, é falha
→ pare"). Entregar mudança financeira não testada seria pior que não entregar.

---

## O que foi produzido (NÃO validado)

Escrevi os dois arquivos que a Task 0.1 pede. Eles estão commitados **marcados
como não validados**, para não se perderem — mas a Task 0.1 **não está concluída**,
porque seu critério de aceite não pôde ser executado.

### `tests/run.php`
Runner no estilo do repo (sem Composer/PHPUnit). Descobre `tests/*_test.php`
(convenção nova) e também os standalone que já existiam (`smoke.php`,
`forecast_balance.php`, `recurrence_overflow.php`, `recurrence_installments.php`,
`history_listing.php`). Roda **cada teste em processo separado** — um fatal error
num arquivo não derruba a suíte — agrega os códigos de saída, imprime o resumo e
sai com ≠0 se qualquer arquivo falhar. Aceita `--only=<filtro>`.

Isso já satisfaz, por construção, a parte do aceite sobre o canário: como o
runner só propaga o exit code de cada arquivo, qualquer teste que saia ≠0 faz o
runner sair ≠0. **Falta comprovar rodando.**

### `tests/helpers.php`
Asserções (`t_ok`, `t_same`, `t_money`, `t_finish`, `t_skip`) e factories
(`make_user`, `make_account`, `make_card`, `make_category`, `make_bill`,
`make_transaction`, `account_balance`).

Dois pontos que resolvi com cuidado, porque são os que evitam estrago:

1. **`t_money` compara em centavos inteiros** (`(int) round($v * 100)`), não com
   tolerância de float. É o que a fila exige: "valores exatos ao centavo".
2. **Guard anti-produção.** O banco de teste é opt-in explícito por
   `TEST_DB_*`. Sem essas variáveis, os testes de banco fazem SKIP em vez de
   cair no banco do `config/config.php`. Além disso o helper **recusa e sai 1**
   se `TEST_DB_DATABASE` for igual ao banco da aplicação, ou se o nome não
   contiver "test". `test_with_rollback()` envolve cada teste numa transação
   sempre revertida.

Conferi as factories contra o schema real (`migrations/20240101000000_baseline_v3.sql`)
— inclusive corrigindo `users.password_hash` → `users.password`, que é o nome
verdadeiro da coluna. Mas **conferência por leitura não é execução**: pode haver
erro de sintaxe que só `php -l` pegaria.

---

## Como retomar

1. Instalar PHP 8.1+ CLI (produção é 8.3.32).
2. Subir um MariaDB/MySQL local e criar o banco de teste:
   ```bash
   DB_DATABASE=novixfin_test php scripts/migrate.php up
   export TEST_DB_HOST=127.0.0.1 TEST_DB_DATABASE=novixfin_test \
          TEST_DB_USERNAME=root TEST_DB_PASSWORD=
   ```
3. Fechar a Task 0.1 de verdade:
   ```bash
   php -l tests/run.php && php -l tests/helpers.php
   php tests/run.php            # precisa sair 0
   ```
   Depois o canário: criar um `tests/canario_test.php` com `exit(1)`, confirmar
   que `php tests/run.php` sai ≠0, e remover.
4. Só então seguir para a 0.2.

**Alternativa:** a Fase B (cartões) é a de maior valor e a de maior risco. Se a
prioridade for destravar hoje, o caminho seguro é rodar a fila num ambiente com
PHP — não afrouxar os critérios de aceite.

---

## Estado das tasks

| Task | Estado |
|---|---|
| 0.1 Harness | **PAROU** — código escrito, aceite não executável (sem PHP) |
| 0.2 Caracterização | não iniciada (depende da 0.1) |
| A1–A5 Planilha | não iniciadas |
| B1–B5 Cartões | não iniciadas |
| B6 UI multi-origem | **BLOQUEADA POR DESIGN** — aguarda mockup + revisão humana (não inventada, conforme instrução) |
| B7 UI de fatura | não iniciada (depende de B2 e B5) |
| C1 Regressão | não executável |

## Aguardando humano
- **Ambiente**: PHP 8.1+ e banco de teste local — sem isso a fila inteira trava.
- **B6**: design/mockup da UI de pagamento multi-origem.
- **`tests/run.php` e `tests/helpers.php`**: revisão + primeira execução real.
- **Decisão pendente da auditoria** (`reports/auditoria_cartoes_faturas.md`): a
  semântica de `register_bill_as_expense` precisa ser definida antes da B5 —
  hoje pagar a fatura cria uma despesa na própria conta do cartão que volta a
  somar no total da fatura. A Task B5 já manda remover isso, mas a decisão
  sobre o dado histórico existente (faturas já pagas com esse efeito) não está
  na fila e vai aparecer na migração reversível.

## Suíte de testes
`php tests/run.php` — **não executado** (sem PHP nesta máquina). 0 passa / 0 falha.

## Deploy
Nenhum. Nenhum comando de rede, tar, scp, rsync ou ssh foi executado.
