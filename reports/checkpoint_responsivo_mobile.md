# Checkpoint — Responsividade mobile (todas as telas)

Data: 2026-07-28

## Task
Tornar o NovixFin responsivo (<=768px) em todas as telas existentes, seguindo o mockup mobile
aprovado. Aplicar comportamento via CSS/JS sem redesenhar desktop. Parar para revisão.

## Fonte visual
Claude Design MCP, projeto cc9d061b, arquivo **"Mobile NovixFin.dc.html"** (lido via DesignSync,
803 linhas). Padrões globais extraídos da seção "PADRÕES GLOBAIS" + telas:
- **Drawer**: sidebar vira gaveta; hambúrguer na topbar; overlay `color-mix(in srgb, var(--ink)
  52%, transparent)`; gaveta 300px; X para fechar; nav Principal/Gestão + "Ocultar saldos".
- **Bottom sheet**: "todo modal sobe de baixo, largura total, com puxador, scroll interno e ações
  fixas no rodapé" (`.sheet` + `.grab`).
- **Tabela densa**: "**Nenhuma tabela rola de lado**: cada linha do extrato vira um cartão" —
  portanto linha→cartão, NÃO scroll horizontal com coluna fixa.
- **Kanban**: "as 4 colunas viram carrossel com snap: uma coluna por vez a 300px" (`.kcol`,
  `scroll-snap-type:x mandatory`).
- **FAB**: pílula 56px, `right:16px; bottom:18px`. **Alvos de toque**: `.tap` = 44px.
- **Breakpoint**: "como cada tela existente se comporta abaixo de **768px**".

## DADOS SÃO EXEMPLO — respeitado
Nenhum valor do mockup (R$ 12.450,80, "Marina", etc.) foi hardcoded. Esta fatia é **reflow de
layout**: cada tela continua lendo os dados reais do mesmo data-prep/handlers do desktop.
As únicas mudanças em PHP são atributos `data-label`/classe para o CSS ancorar — nenhum dado,
handler, name, id ou CSRF tocado.

## Entregas
### 1. Shell — drawer (global, afeta as 10 telas internas)
- `includes/layout/header.php`: novo `#nvx-drawer-toggle` (hambúrguer, aria-expanded/controls),
  `#nvx-drawer-ov` (overlay), `#nvx-drawer-close` (X) numa `.nvx-sbrand-row`. **`#nvx-mnav`
  (faixa horizontal) REMOVIDA** — o drawer é o padrão único do mockup.
- `shell.css`: <=1020px a `#nvx-side` vira `position:fixed`, `translateX(-100%)`, 300px/86vw,
  z-index 90, com `#nvx-shell[data-drawer="open"]` deslizando; overlay z-89 tema-aware; CSS da
  `#nvx-mnav` removido; alvos 46px na gaveta.
- `shell.js`: `wireDrawer()` — abre/fecha, overlay, X, Esc, fecha ao navegar, limpa ao voltar
  para desktop, `body.nvx-drawer-open` trava a rolagem do fundo.

### 2. Empilhamento global (<=768px, shell.css)
Grades multi-coluna do conteúdo viram 1 coluna. Cobre as grades **inline** das telas (contas,
carteiras, metas, moeda, import/export, perfil) e as utilitárias fixas do Tailwind
(`grid-cols-2/3/4`). Usa `!important` porque estilo inline vence qualquer seletor. Exceção
explícita: `.dashboard-kanban-board` (carrossel). Alvos >=44px; inputs 16px (evita zoom iOS),
excluindo checkbox/radio para não deformá-los.

### 3. Modais → bottom sheet (<=768px, era 560px)
Largura total, sobem de baixo, puxador via `::before`, **cabeçalho sticky**, **rodapé de ações
sticky**, scroll interno com `overscroll-behavior:contain`, `env(safe-area-inset-bottom)`.
Os seletores repetem `.nvx-modal--wide`/`.nvx-tx-modal` para vencer as larguras específicas
(especificidade 0,2,1) — sem isso o modal de lançamento continuaria com 600px fixos.

### 4. Tabelas → cartão (padrão do mockup)
- **Histórico** (`.hist-sheet`, 10 colunas) e **Cartões** (faturas, 7 colunas): cada `<td>`
  recebeu `data-label`; no CSS o `thead` some (acessível via clip), `tr` vira cartão
  (surface/card-b/--cut) e cada `td` vira linha rótulo→valor. Valor em `--f-num` destacado.
- **Carteiras**: a "tabela" de contas é um grid de divs — ganhou `.wal-thead`/`.wal-trow` +
  `data-label` nos 3 valores; no mobile o cabeçalho some e a linha vira cartão rotulado.

### 5. Dashboard
KPIs (`.dash-stats`) empilham em 768; kanban vira carrossel `scroll-snap-type:x mandatory` com
coluna `min(300px,82vw)` e `snap-align:center`; FAB pílula 56px com safe-area (mantém o rótulo
"Lançar", como o mockup).

### 6. Entrada / Landing (fora do shell)
- `entrada.css`: já empilhava em 900px; +bloco 768px de conforto de toque e respiro.
- `landing.css`: só tinha regra em 1280px. Novo bloco <=768px empilha as grades inline das
  `<section data-screen-label>`, reduz padding lateral, 44px em links/botões, seletor de tema
  rolável.

## Files changed
- includes/layout/header.php; public/scripts/shell.js
- public/styles/: shell.css, modal.css, dashboard.css, history.css, cards.css, wallets.css,
  landing.css, entrada.css
- public/history.php, public/credit-cards.php, public/wallets.php (apenas `data-label`/classe)

## Risks
- **Remoção da `#nvx-mnav`**: navegação mobile passa a depender do drawer. Se o JS falhar, o
  hambúrguer não abre — mitigado por o drawer ser CSS-driven via atributo e o JS ser mínimo.
- **Regra de empilhamento com `!important`**: ampla por necessidade (inline styles). Escopada a
  `.nvx-content` e só <=768px; desktop intocado. Exceção do kanban explícita.
- `min-height:44px` em botões pode aumentar a altura de botões-ícone pequenos no mobile — é o
  alvo de toque pretendido.

## Validation performed
- 16 arquivos CSS com chaves balanceadas (0 mismatches).
- `node --check` OK: shell.js, kanban.js, tx-modal.js, dashboard.js, goals.js.
- PHP/`<tr>`/`<td>` balanceados: header.php, history.php (10/10 td), credit-cards.php (7/7 td),
  wallets.php.
- `php -l` local indisponível.

## Remaining work (servidor, viewport ~390px)
- `php -l` nos arquivos PHP tocados.
- Por tela: drawer abre/fecha (tocar fora, Esc, X), cards empilham, Histórico e Cartões como
  cartões (sem scroll lateral), Carteiras rotulada, kanban em carrossel com snap, modais como
  bottom sheet (cabeçalho/rodapé fixos), FAB acessível.
- **Dados reais** (não os do mockup) em todas as telas.
- **Desktop intacto acima de 768px** (e sidebar normal acima de 1020px).
- Tema claro (ametista) e escuro (tinta).

## Confidence
80% — padrões extraídos direto do mockup e aplicados de forma consistente e escopada; a
confiança sobe após o passe visual real em viewport mobile, principalmente Histórico/Cartões
(linha→cartão) e o drawer.
