# Overnight 2026-08-09 — execução da fila TASKS.md

**Resultado: a fila foi executada da Task 0.1 até a C1 sem parar.**
12 tasks concluídas, 1 pulada por instrução (B6). Suíte final: **13 arquivos,
0 falhas, exit 0**. **Nenhum deploy foi feito** — o motivo está na seção
"Deploy" e é a única coisa que precisa de você para fechar a C1.

---

## Ambiente — o que destravou a fila

A execução de 2026-08-08 parou na Task 0.1 porque não havia PHP na máquina.
Continua não havendo. O que mudou é que o **Docker** está disponível, e ele dá
um ambiente melhor do que uma instalação local:

- `php:8.3-cli` + `pdo_mysql` → **PHP 8.3.33** (produção: 8.3.32)
- `mariadb:11.8` → **MariaDB 11.8.8** (produção: 11.8.x)

**A trava de segurança que importa:** `includes/env.php` faz `putenv()` dos
valores do `.env` **sobrepondo variáveis de ambiente reais**. Passar
`-e DB_DATABASE=...` para o container **não** teria efeito — o `.env` do repo,
que aponta para PRODUÇÃO, venceria. Por isso o `.env` é **neutralizado dentro do
container** por um bind-mount read-only. Sem esse detalhe, a suíte teria tentado
escrever no banco de produção.

Dois bancos descartáveis, de propósito:
- `novixfin_test` — usado por `tests/helpers.php` (banco isolado, rollback ao fim)
- `novixfin_app` — o que `config/config.php` resolve nos testes de integração

Eles precisam ser **diferentes** porque `helpers.php` recusa rodar quando o banco
de teste é igual ao da aplicação — o guard funcionou e foi verificado.

> **Nota:** você deixou `docker-compose.test.yml` e `.env.test.example` no
> diretório (não versionados, criados hoje 11:36) descrevendo exatamente esta
> abordagem. Eu montei o equivalente por conta antes de encontrá-los. **Vale
> versionar o seu compose e padronizar nele** — é a mesma coisa, melhor embalada.
> Os comandos que usei estão no fim deste relatório.

---

## Tasks

| Task | Hash | Situação |
|---|---|---|
| 0.1 harness | `269e2dc` | ✅ |
| 0.2 caracterização | `9bcd1ae` | ✅ |
| A1 planilha: persistência | `c5c88ff` | ✅ |
| A2 múltiplas abas | `27935f2` | ✅ |
| A3 undo/redo + indicador | `0ad69ee` | ✅ |
| A4 histórico de versões | `0e41d67` | ✅ |
| A5 edição em bloco | `a131716` | ✅ |
| B1 teste do ciclo (vermelho) | `ae9e523` | ✅ vermelho proposital |
| B2 corrigir o ciclo | `07cc651` | ✅ B1 ficou verde |
| B3 migração bill_payments | `e77285e` | ✅ |
| B4 teste do pagamento (vermelho) | `f22b1d9` | ✅ vermelho proposital |
| B5 pagamento multi-origem | `fbf7af3` | ✅ B4 ficou verde |
| B6 UI de pagamento | — | ⏭️ **PULADA por instrução** |
| B7 UI clara de fatura | `0d4a01e` | ✅ |
| C1 regressão final | este relatório | ⚠️ **sem deploy** |

26 arquivos, +2571 / −529.

---

## Decisão que precisou de você (Task 0.1)

A suíte, na primeira execução real da história do repo, acusou uma falha
legítima: o scan estático em `tests/recurrence_installments.php` exigia
**compatibilidade PHP 7.4**, contradizendo o **"PHP 8.1+" das próprias REGRAS
GLOBAIS do TASKS.md** e do AGENTS.md. Produção é 8.3.32 e não existe mais
ambiente 7.4.

**Você decidiu aposentar o alvo 7.4.** O scan agora verifica só a regra da casa
que sobrevive — `catch` sempre com variável. `state/context.md` foi atualizado.

Achado no caminho: `includes/forecast.php` era **falso positivo** — `mixed`
aparecia só em docblock (`array{amount?: mixed}`), nunca como declaração de tipo.

---

## O que mudou de comportamento financeiro

### Ciclo de fatura (B1→B2)
Antes, a fatura de destino era o **mês do calendário** da compra; `closing_day`
só preenchia `closing_date` depois. Toda compra entre `closing_day+1` e o fim do
mês caía numa fatura **que já havia fechado antes de a compra existir**.

Agora a regra está em **uma** função — `CreditCardBillRepository::billingMonthFor()`
— usada por `create.php`, `update.php` e `RecurringService` (parcelas seguem o
mesmo ciclo). Cartão que fecha dia 5: `03/01 → 2026-01`, `20/01 → 2026-02`,
`28/12 → 2027-01`.

Removi a heurística "pega a próxima fatura aberta por due_date" que existia para
pendente com data de efetivação: dependia de haver fatura aberta, ignorava o
fechamento e divergia do caminho normal.

### Pagamento de fatura (B4→B5)
Antes, `register_bill_as_expense` (ligado por padrão) criava uma despesa do total
**na conta do próprio cartão**, com o `bill_id` da fatura. Resultado medido:
pagar uma fatura de 1.000 levava o cartão de **−1.000 para −2.000** e **inflava a
própria fatura para 2.000**, porque `updateBillTotal()` soma tudo que tem aquele
`bill_id`.

Agora: o pagamento tem **origens** (`account` debita a conta; `external` não
debita nada), cada uma vira linha em `bill_payments`, e **nenhuma despesa é criada
na conta do cartão**. Verificado ao centavo: 400 (conta X) + 600 (conta Y) →
X=600,00, Y=400,00, fatura zera, cartão continua em −1.000,00.

**Reversão por `bill_payments.transaction_id`**, não mais por prefixo de
descrição — o casamento antigo quebrava se o cartão fosse **renomeado** entre
pagar e reverter, deixando transações órfãs e saldo errado. Faturas pagas antes
desta noite não têm linha em `bill_payments`, então o caminho por descrição
permanece como **fallback**: nenhuma fatura antiga virou irreversível.

**Compatibilidade preservada:** como a B6 (UI) está bloqueada, a tela atual
continua postando o formato antigo `account_debits[]` — ele é aceito e convertido.
Sem origem nenhuma, o `paid_amount` entra como origem `external`.

---

## ⚠️ Achado grave, NÃO corrigido (fora do escopo de toda task)

**`public/dashboard.php:691` quebra o dashboard numa instalação v3 nova.**

A query usa `COALESCE(title, name)` na tabela `goals`, que exige as **duas**
colunas. O baseline v3 cria só `title`. O `catch` cai num fallback que consulta
`name` sozinho → **fatal não capturado**. Produção só escapa porque ainda tem a
coluna legada `goals.name`.

Descobri isso porque o harness HTTP não conseguia carregar o dashboard num banco
recém-migrado. **Isto afeta diretamente a decisão de deploy:** um servidor de
teste com banco novo terá o dashboard quebrado. É um conserto de poucas linhas,
mas nenhuma task da fila autorizava mexer ali, então deixei intacto e registrado.

---

## Deploy — por que NÃO foi feito

A política atualizada manda deployar na C1 se a suíte passar. **A suíte passou**
(13/13, exit 0). Mesmo assim **não deployei, e não é por cautela genérica:**

**Não existe alvo de deploy neste repositório.** Verifiquei:
- `git remote -v` → **vazio**, nenhum remoto configurado
- nenhum script de deploy, `Makefile` ou pipeline
- nenhum `~/.ssh/config`, host ou credencial
- `docker-compose.test.yml` é ambiente **local** e descartável, não um servidor

O único host descrito em qualquer lugar é a **VPS de produção** (AGENTS.md), e
deployar nela contraria explicitamente a instrução de que o alvo é o **servidor
de teste**. Inventar um destino seria uma ação externa e difícil de reverter, em
cima de mudanças financeiras. Por isso parei aqui e trouxe a decisão para você.

**Para destravar, preciso de uma destas:** host/caminho do servidor de teste e
como autenticar; ou um script/compose de deploy no repo; ou a confirmação
explícita de qual alvo usar.

Antes de deployar, considere também: rodar `php scripts/migrate.php up` (duas
migrações novas, `20240101000009` e `20240101000010`) e **consertar o fatal do
dashboard**, senão a tela principal cai no ambiente novo.

---

## Aguardando revisão humana

- **B6** — UI de pagamento multi-origem: **aguarda design + revisão humana**.
  Pulada conforme instrução, não inventei a tela. O backend já aceita o formato
  novo (`sources[]`), então a UI pode ser construída sem mexer no handler.
- **Revisão visual** (nenhuma task de UI é auto-aprovável):
  - A2: duplo clique renomeia aba, × exclui, trocar de aba
  - A3: desfazer/refazer e os três estados do indicador de autosave
  - A5: colar bloco TSV vindo do Excel/Sheets e arrastar a alça de preenchimento
  - B7: composição da fatura e "Pago com" nos **15 temas**, claro e escuro
- **Fatal do dashboard** (acima) — decidir se conserta antes do deploy.
- **`register_limit_as_income`**: continua existindo e ativo se alguém tiver
  ligado. A fila só mandou desativar `register_bill_as_expense`. Com a nova
  semântica ele ficou órfão e provavelmente deveria sair também — sua decisão.
- **Versionar `docker-compose.test.yml` + `.env.test.example`** e padronizar a
  suíte neles.

---

## Suíte final

```
Arquivos: 13 passaram, 0 falharam.
0 falhas.
```

`bill_cycle_test` · `bill_payment_semantics_test` · `bill_payments_schema_test` ·
`characterization_test` · `harness_test` · `sheets_history_test` ·
`sheets_persist_test` · `sheets_tabs_test` · `smoke` · `forecast_balance` ·
`recurrence_overflow` · `recurrence_installments` · `history_listing`

`php -l` em **22 arquivos PHP** tocados na noite → todos limpos (inclui o
primeiro lint real de `dashboard.php`, `header/footer` e dos handlers de sheets,
pendente desde as sessões sem PHP). `node --check` em `sheet.js` e
`forecast-sim.js`. `scripts/cron.php recurring` roda limpo.

### Como reproduzir

```bash
docker network create novixfin-test
docker run -d --name novixfin-testdb --network novixfin-test \
  -e MARIADB_ROOT_PASSWORD=testonly -e MARIADB_DATABASE=novixfin_test mariadb:11.8
# imagem php com pdo_mysql:  FROM php:8.3-cli + docker-php-ext-install pdo_mysql
# .env NEUTRALIZADO por bind-mount read-only apontando para o MariaDB de teste
docker run --rm --network novixfin-test -v "<repo>:/app" -v "<env de teste>:/app/.env:ro" \
  -e TEST_DB_HOST=novixfin-testdb -e TEST_DB_DATABASE=novixfin_test \
  -e TEST_DB_USERNAME=root -e TEST_DB_PASSWORD=testonly \
  -e HTTP_BASE=http://novixfin-web:8080 -w /app novixfin-php:8.3 php tests/run.php
```

Os testes de integração (sheets, ciclo, pagamento) exigem `HTTP_BASE` apontando
para um `php -S` servindo a raiz do repo — **`actions/` fica fora de `public/`**,
então o docroot do servidor de teste é a raiz, não `public/`. Sem `HTTP_BASE`
eles se marcam como SKIP em vez de falhar.
