# Checkpoint — Olhinho de simulação da previsão + "Carregar mais" (2026-08-06)

## Tarefa
Duas melhorias nas "Movimentações recentes" do dashboard, sem migração e sem
tocar em dados:
- **(A)** olho por transação que a exclui do cálculo da previsão de saldo em
  modo SIMULAÇÃO (temporário, client-side, zero banco);
- **(B)** "Carregar mais" incremental na própria tela.

## Arquivos alterados
- `public/dashboard.php` — ids na previsão, aviso de simulação, data-attributes
  por linha, botão "Carregar mais", base numérica exposta ao JS, correção do
  slice do JSON do modal de edição.
- `public/scripts/forecast-sim.js` — **novo**. Toda a interação.
- `public/styles/dashboard.css` — bloco tokenizado (olho, aviso, botão) + a
  regra que faz `[hidden]` vencer o `display:flex`.
- `tests/probe_forecast_sim.py` — **novo**, 11 probes estáticos.

Nenhum arquivo do núcleo financeiro foi tocado. Sem migração.

## Resumo

### (A) Simulação
Cada linha ganhou um botão de olho em SVG. Clicar tira a transação **apenas do
cálculo da previsão** — a linha continua na lista, marcada com opacidade menor
e o valor riscado.

O recálculo respeita onde a transação realmente entra na decomposição:
- **pendente** → sai de "a receber" (income) ou "a pagar" (expense);
- **efetivada** → já está dentro do "saldo hoje", então o efeito dela é
  **estornado** dali (receita de +500 oculta ⇒ saldo hoje −500 ⇒ saldo final
  −500; despesa de 200 oculta ⇒ saldo hoje +200).

Com ≥1 oculta aparece o aviso "Simulação · N ocultas · valor não é o saldo
real" com botão **Restaurar**, mais o selo `simulação` ao lado do título; o
número-herói, o "saldo hoje" e o "saldo final" passam a `--gold` para não serem
confundidos com o saldo real.

**Elegibilidade** (o ponto que exigiu cuidado): o olho fica desabilitado quando
a transação não compõe a previsão, senão a simulação mexeria num número que ela
não formou. São dois casos:
- moeda diferente da primária — a consulta dos pendentes filtra
  `currency = primária OR NULL`;
- efetivada em conta de **crédito** — cartão é passivo e fica fora de
  `$consolidatedBalance`.

### (B) Carregar mais
Escolhi **renderizar no servidor e revelar no cliente**, não paginar por
requisição: `$recentTransactions` já vem inteiro em memória (é o mesmo array que
alimenta o JSON do modal de edição), então um endpoint novo não economizaria
consulta nenhuma. Lotes de 8, com contador de restantes; ao acabar, o botão fica
desabilitado com "Nada mais a carregar". Teto de **200 linhas** renderizadas
para não montar um DOM gigante em conta antiga — acima disso o rodapé informa
"Mostrando as N mais recentes de M" e o caminho segue sendo o histórico
completo. O link "Ver histórico completo →" do topo permanece intacto.

Como as linhas já existem no DOM com todos os `data-sim-*`, e o clique do olho é
capturado por delegação, a Parte A funciona nas linhas reveladas sem religar
nada.

## Correção colateral encontrada
`dashboardTransactionsData` (fonte do modal de edição) era um
`array_slice(..., 0, 8)` **independente** do slice da lista. Com o "Carregar
mais", editar uma linha revelada depois não encontraria os dados. Os dois agora
compartilham o mesmo array renderizado.

## Riscos
- **Baixo.** Camada de apresentação. Nenhuma escrita, nenhum endpoint novo,
  nenhuma consulta alterada. A previsão determinística do servidor continua
  sendo a fonte da verdade — o JS parte dela e "Restaurar" apenas a reescreve.
- Ponto de atenção real: as linhas têm `display:flex` **inline**, que venceria o
  atributo `hidden`. Resolvido com `.dash-recent-item[hidden] { display: none
  !important; }` — se essa regra sumir, todas as linhas aparecem de uma vez.
- Conta com >200 lançamentos não exibe tudo na lista do dashboard (por decisão).

## Validação executada
- `node --check public/scripts/forecast-sim.js` → passou.
- `python -m pytest tests/probe_forecast_sim.py` → **11 passed** (contrato de
  ids/data-attributes, ausência de qualquer chamada de rede no script, e os 4
  casos de aritmética do enunciado).
- `python -m pytest tests/probe_batch2_features.py` → 10 passed, **3 failed
  PRÉ-EXISTENTES** — as três asseguram conteúdo de `includes/forecast.php` e
  `includes/layout/header.php`, arquivos que esta tarefa não tocou (probes
  ficaram defasados em refatorações anteriores).
- `php -l` **não rodou**: não há PHP nesta máquina.

## Falta rodar (servidor)
```bash
php -l public/dashboard.php
php tests/smoke.php
```

## Confiança
**88%.** A aritmética e o contrato de ids estão cobertos por probe; o que resta
é verificação visual nos temas claro/escuro e o `php -l`, que só o servidor faz.
