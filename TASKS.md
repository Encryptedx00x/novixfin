# TASKS.md — Fila de execução autônoma (overnight · human-at-the-end · NUNCA deploy)

## REGRAS GLOBAIS (leia antes de QUALQUER task)
- Antes de tudo: leia AGENTS.md, state/progress.md, state/context.md e os reports/ recentes.
- Execute as tasks EM ORDEM. Complete e VALIDE uma antes da próxima.
- COMMIT POR TASK: só commite se TODOS os critérios de aceite passarem. Mensagem
  "task <id>: <resumo>". Registre o hash no state/progress.md a cada commit.
- PARE NO PRIMEIRO ERRO: se uma task falhar qualquer critério, NÃO contorne, NÃO avance.
  Deixe o repo no último estado bom (a task falha fica não-commitada ou revertida), escreva
  no progress.md o que falhou e por quê, e ENCERRE a fila.
- NUNCA faça deploy. Proibido tar/scp/rsync/ssh para produção. Só commit local.
- PHP 8.1+, PDO, CSRF, sem build step. Invariantes intocados (saldo, atomicidade, pay/revert,
  recorrência) salvo onde a task manda mudar — e aí com teste cobrindo.
- Migração: sempre idempotente e REVERSÍVEL (com down/rollback). Nunca destrutiva sem dump.
- Toda task de LÓGICA escreve/roda TESTE AUTOMATIZADO como aceite (não "conferir no olho").
  Testes financeiros validam VALORES EXATOS (ao centavo). Se um valor não bate, é falha → pare.
- Toda task de UI: php -l dos arquivos tocados + descrever no progress o que foi feito para
  revisão visual humana (a UI não é auto-aprovável).

---

## FASE 0 — FUNDAÇÃO DE TESTES

### Task 0.1 — Harness de testes
- Verifique se existe um runner de testes (tests/). Se não houver um padrão claro, crie
  tests/run.php que descobre e executa tests/*_test.php, conta passes/falhas, sai com código
  ≠0 se qualquer teste falhar (para o harness conseguir detectar falha).
- Crie tests/helpers.php: helper para banco de teste isolado (transação com rollback ao fim,
  OU um schema de teste separado — nunca escreva no banco real de produção nos testes),
  factories mínimas (criar usuário, conta, cartão, transação, fatura).
- ACEITE: `php tests/run.php` roda, mostra "0 falhas" (ou os testes existentes), sai com 0.
  Um teste-canário proposital que falha faz o run.php sair ≠0 (prove que a detecção funciona,
  depois remova o canário).

### Task 0.2 — Testes de caracterização do estado ATUAL (rede de segurança)
- Antes de mudar lógica, capture o comportamento atual em testes (characterization tests), pra
  detectar regressões: saldo de conta após criar/editar/excluir transação; efetivar pendente;
  cálculo do saldo consolidado; previsão determinística (saldo hoje + a receber − a pagar).
- Use valores conhecidos: monte um cenário fixo e asserte os totais. Ex.: previsão deve dar o
  valor esperado do cenário montado.
- ACEITE: todos passam contra o código atual. Estes testes NÃO podem quebrar nas fases
  seguintes, exceto onde uma task explicitamente muda a regra (aí a task atualiza o teste).

---

## FASE A — PLANILHA (fechar feature; backend do Bloco 1 já commitado)

### Task A1 — Front Bloco 1: grade + fórmulas + persistência
- public/scripts/sheet.js (ferramenta de escrever arquivo, NÃO heredoc bash). Aba "Planilha" no
  dashboard.php + <script> HyperFormula v3 (CDN hyperformula@3 full.min.js) + $pageStyles/
  $pageScripts. Instância HyperFormula única, setCellContents por célula. SALVAR A FÓRMULA (não
  o resultado). Autosave debounce 800ms → save.php. Carregar via load.php. Ref: design v2.
- ACEITE (teste): tests/sheets_persist_test.php — simula salvar um content com "=SUM(A1:A3)" via
  save.php e recarregar via load.php; asserta que o content devolvido contém a FÓRMULA, não o
  número. php -l ok. [+ nota no progress para revisão visual do recálculo ao vivo]

### Task A2 — Múltiplas abas
- Barra de abas (criar +, renomear inline, excluir com confirmação, trocar). Cada aba = uma
  sheet na HyperFormula. content passa a ser array de abas.
- ACEITE (teste): tests/sheets_tabs_test.php — save/load de um documento com 3 abas preserva as
  3, seus nomes e conteúdos. php -l ok.

### Task A3 — Undo/redo + autosave (indicador) 
- Undo/redo local (Ctrl+Z/Y/Shift+Z, pilha em memória — client-side, sem banco). Indicador
  Salvando/Salvo/erro.
- ACEITE: php -l ok; node --check em sheet.js. [revisão visual: desfazer/refazer e indicador]

### Task A4 — Histórico de versões
- Snapshots em sheet_versions por MARCO (save manual + intervalo ≥5min se mudou), NUNCA por
  tecla. Dropdown data/hora + restaurar. Poda em 50 por sheet.
- ACEITE (teste): tests/sheets_history_test.php — snapshot.php cria versão; restore.php restaura
  o content exato; a poda mantém no máximo 50 (crie 55, sobram 50, os mais novos). php -l ok.

### Task A5 — Edição em bloco
- Seleção de intervalo, copiar/colar (Ctrl+C/V, colar TSV externo), fill handle (propaga
  valor/fórmula com ajuste de referência relativa).
- ACEITE: node --check; [revisão visual: colar um bloco TSV e arrastar o fill handle]

---

## FASE B — CARTÕES (lógica financeira — TESTE COM VALORES EXATOS obrigatório)

### Task B1 — TESTE do ciclo de fatura (escrever teste que FALHA no bug atual)
- Escreva tests/bill_cycle_test.php cobrindo: cartão fecha dia 5 → compra 03/jan cai na fatura
  jan; compra 20/jan cai na fatura FEV; compra 28/dez cai na fatura JAN do ano seguinte
  (virada). Rode: deve FALHAR contra o código atual (prova que capturou o bug).
- ACEITE: o teste existe e falha pelas razões certas (compra pós-fechamento indo pra fatura
  errada). Commit do teste (ainda vermelho) + nota no progress.

### Task B2 — CORRIGIR o ciclo de fatura
- Em create.php (~290) e em toda duplicação do cálculo compra→fatura: destino pelo closing_day
  (pós-fechamento → mês seguinte; trate virada de ano).
- ACEITE: tests/bill_cycle_test.php agora PASSA. Os testes de caracterização (0.2) continuam
  passando. php -l ok. Se algum caso falhar, PARE.

### Task B3 — Migração bill_payments (multi-origem) + teste de schema
- Migração idempotente e reversível: bill_payments (id, bill_id FK, source_type
  ['account'|'external'], source_account_id NULL, amount decimal, created_at).
- ACEITE (teste): tests/bill_payments_schema_test.php — a tabela existe com as colunas/tipos
  esperados após migrate up; o down remove. Rode migrate up. php -l ok.

### Task B4 — TESTE da nova semântica de pagamento (escrever, deve falhar no atual)
- tests/bill_payment_semantics_test.php: pagar fatura de R$ 1.000 com 400 (conta X) + 600
  (conta Y) → conta X −400, conta Y −600, fatura ZERA, saldo do cartão NÃO fica mais negativo.
  Reverter → contas voltam exatas, fatura volta 1.000. Deve FALHAR no código atual.
- ACEITE: teste existe e falha pelas razões certas. Commit (vermelho) + nota.

### Task B5 — IMPLEMENTAR semântica de pagamento multi-origem
- pay-bill.php: aceitar múltiplas fontes {source_type, source_account_id?, amount}; para cada
  'account' debitar a conta; abater a fatura pela soma; 'external' não debita. NÃO criar despesa
  na conta do cartão (remover register_bill_as_expense / desativar toggle via migração
  reversível, documentar). Gravar linhas em bill_payments. revert-payment.php: reverter por
  bill_payments.id (não por prefixo de descrição — corrigir o bug de rename).
- ACEITE: tests/bill_payment_semantics_test.php PASSA (valores exatos ao centavo). Testes de
  caracterização (0.2) e bill_cycle (B1) continuam passando. php -l ok. Se não bater, PARE.

### Task B6 — [BLOQUEADA POR DESIGN] UI de pagamento multi-origem
- NÃO implemente sem mockup aprovado. Marque no progress como "aguarda design + revisão humana"
  e PULE. Não invente a UI.

### Task B7 — UI clara de fatura + reskin tokenizado de Cartões
- Só se B2 e B5 passaram. credit-cards.php: listar o que compõe a fatura (query WHERE bill_id=…);
  deixar claro cartão↔banco↔fatura; TOKENIZAR a tela nos 15 temas; remover CREATE TABLE em
  runtime (~30-45).
- ACEITE: php -l ok; [revisão visual: fatura lista transações; tela tokenizada claro+escuro]

---

## FASE C — REGRESSÃO FINAL
### Task C1 — Suíte completa + relatório
- Rode `php tests/run.php` inteiro. TODOS devem passar. php -l em todos os arquivos tocados na
  noite. Rode os smoke/recorrência existentes.
- Escreva reports/overnight_<data>.md: tasks concluídas (com hash), a que PAROU a fila se
  houve, o que aguarda revisão humana (B6 design, todas as tasks de UI), e o resultado da suíte.
- NÃO deployar. Fim.

---

## RESUMO DE EXECUÇÃO (o agente preenche ao terminar)
- **Última task concluída: NENHUMA.** A fila parou na primeira task (0.1).
- **Fila parou? SIM — na Task 0.1**, por impossibilidade de ambiente, não por bug:
  1. **Não há PHP nesta máquina** (`which php` vazio; PowerShell: "php NOT on PATH";
     nenhum XAMPP/Laragon/WAMP). Sem PHP não roda `php tests/run.php` (aceite da 0.1)
     nem `php -l` (aceite de toda task de UI) nem os testes de valor exato da Fase B.
  2. **Não há banco de teste isolado.** O único banco configurado (`.env`) é o MariaDB
     remoto de PRODUÇÃO — e sem acesso a partir deste IP. A 0.1 exige banco isolado e as
     regras proíbem testes escreverem no banco real.
  Segui a regra "PARE NO PRIMEIRO ERRO / NÃO contorne": não avancei para B2/B5, que mexem
  em saldo, pagamento e reversão de fatura, porque seriam mudanças financeiras sem um único
  teste executável — exatamente o que a fila proíbe.
- **Produzido (NÃO validado):** `tests/run.php` e `tests/helpers.php`, commitados marcados
  como não validados para não se perderem. A Task 0.1 **não conta como concluída**.
  Destaques: `t_money` compara em centavos inteiros (exato ao centavo) e `helpers.php` tem
  guard que RECUSA rodar se o banco de teste for igual ao da aplicação ou não contiver "test".
- **Aguardando humano:**
  - **Ambiente**: PHP 8.1+ CLI e um MySQL/MariaDB local — destrava a fila inteira.
  - **B6**: aguarda design + revisão humana (pulada conforme instrução, não inventada).
  - Revisão e primeira execução real de `tests/run.php` / `tests/helpers.php`.
  - Decisão sobre `register_bill_as_expense` (ver `reports/auditoria_cartoes_faturas.md`),
    que precede a B5 e afeta faturas já pagas.
- **Suíte de testes: não executada** (sem PHP). 0 passa / 0 falha.
- Relatório completo: `reports/overnight_2026-08-08.md`. **Nenhum deploy.**