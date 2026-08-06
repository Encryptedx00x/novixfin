# Checkpoint — Correção: "Carregar mais" sem revelar + olho sem trocar de ícone (2026-08-06)

## Tarefa
Dois bugs client-side na fatia anterior (`190c255`), ambos sem banco:
- **(A)** "Carregar mais" decrementava o contador mas não revelava linha nenhuma.
- **(B)** o olho recalculava a previsão certo, mas o ícone não mudava.

## Causa raiz

### Bug B — causa exata e definitiva
`applyRowState()` fazia `svgEl.hidden = true`. **`SVGElement` não implementa a
propriedade IDL `hidden`** — ela existe em `HTMLElement`, não em elementos SVG.
A atribuição criava apenas uma propriedade solta no objeto JS: nenhum atributo
mudava no DOM e nenhum estilo era recalculado. O ícone `dash-sim-eye-off`
nascia com o atributo `hidden` no HTML (isso funcionava, porque aí quem agia era
o seletor de atributo do CSS), mas **nunca voltava**. Por isso a lógica do
recálculo funcionava e só o visual ficava parado.

### Bug A — o atributo `hidden` perdia para o `display:flex` inline
As linhas têm `style="display:flex; …"` **inline**. O atributo `hidden` só
conseguia escondê-las por causa da regra
`.dash-recent-item[hidden] { display:none !important; }` — uma declaração de
autor `!important` vence o inline, então a regra estava correta **desde que
chegasse ao navegador**.

Ela não chegou: `$pageStyles`/`$pageScripts` eram carregados **sem
versionamento**, e o navegador seguia servindo o `dashboard.css` antigo. Sem a
regra, as 13 linhas apareciam todas de saída, e o botão — que calculava
"restantes" a partir de um número do servidor, não do DOM — apenas decrementava
o contador. Bate exatamente com o sintoma relatado (13 visíveis, "5 restantes",
clique sem efeito visual).

É a **terceira** ocorrência do mesmo problema de cache neste repo (landing,
`entrada.css` em `b6fc882`, agora aqui).

## Correções
1. **Sem depender de CSS.** As linhas e os cabeçalhos de dia nascem com
   `display:none` ou `display:flex` no próprio `style` inline, e o "Carregar
   mais" alterna esse `display` diretamente. O estado passa a ser marcado por
   `data-recent-hidden="1|0"`. Nada nesse caminho depende mais de folha de
   estilo alguma.
2. **A contagem sai do DOM**, como pedido: `stillHiddenRows()` conta
   `.dash-recent-row[data-recent-hidden="1"]`. Se o servidor e a página
   discordarem, quem manda é o DOM — inclusive na carga inicial (`syncButton()`
   roda no init). Zerou, o botão desabilita com "Nada mais a carregar".
3. **Ícone por `style.display`**, nunca por `.hidden`: `on.style.display` /
   `off.style.display`. O ícone "cortado" passa a nascer com
   `style="display:none;"` em vez do atributo `hidden`.
4. **Cache-busting em `$pageStyles` e `$pageScripts`** (`?v=filemtime`), o mesmo
   padrão já aplicado a `entrada.css`. Sem isso, qualquer CSS/JS novo destas
   telas continuaria refém do cache do navegador.

A revelação continua sendo por `seq`, então o cabeçalho do dia aparece junto com
a primeira linha daquele dia.

## Arquivos alterados
- `public/dashboard.php` — display inline + `data-recent-hidden`; ícone off com
  `display:none`; atributos `data-total`/`data-shown` removidos (o DOM é a fonte).
- `public/scripts/forecast-sim.js` — troca de ícone por `style.display`;
  `stillHiddenRows()`; `syncButton()`.
- `public/styles/dashboard.css` — comentário corrigido sobre o que a regra
  `[hidden]` faz e por que as linhas não a usam mais.
- `includes/layout/header.php`, `includes/layout/footer.php` — `?v=filemtime`.
- `tests/probe_forecast_sim.py` — +5 probes de regressão.

## Preservado
Recálculo da previsão e o estorno-por-tipo (efetivada mexe no saldo hoje,
pendente no a receber/a pagar) **não foram tocados** — os 4 testes de aritmética
seguem passando. Efetivar pendente, os 3 forms e o resto do dashboard, intactos.
Continua simulação client-side: nenhuma escrita, nenhum endpoint.

## Riscos
- **Baixo.** As mudanças de comportamento são todas de apresentação.
- `?v=filemtime` é global para as telas com layout compartilhado: muda a URL de
  todo CSS/JS de página. Efeito colateral esperado e desejado — um recarregamento
  a mais na primeira visita após cada deploy.
- `@filemtime` em arquivo ausente devolve `false` → `(int) 0`; o link continua
  válido, só sem versão. Não quebra.

## Validação executada
- `node --check public/scripts/forecast-sim.js` → passou.
- `python -m pytest tests/probe_forecast_sim.py` → **16 passed**, sendo 5 novos
  de regressão: display inline nas linhas ocultas, contagem vinda do DOM, ícone
  por `style.display` (com asserção de que `.hidden = isHidden` **não** volta ao
  arquivo), ícone off nascendo com `display:none`, e cache-busting presente.
- `php -l` **não rodou**: não há PHP nesta máquina.

## Falta rodar (servidor)
```bash
php -l public/dashboard.php
php -l includes/layout/header.php
php -l includes/layout/footer.php
php tests/smoke.php
```

## Confiança
**Bug B: 97%** — causa identificada com precisão e corrigida na raiz.
**Bug A: 88%** — a correção elimina a dependência de CSS que causava o sintoma,
e a contagem agora vem do DOM; o que resta é a confirmação visual no servidor.
