# Checkpoint — Fatia Shell (sidebar + barra superior tokenizados)

Date: 2026-07-23

## Escopo entregue
Moldura comum a todas as 10 telas do app portada para o design tokenizado: sidebar em
grid (logo cristal, grupos PRINCIPAL/GESTÃO, ícones de linha, item ativo por tema, rodapé)
+ barra superior (eyebrow + título). Afeta todas as telas de uma vez.

## Files changed
- NEW `public/styles/shell.css` — moldura tokenizada, **100% escopada sob
  `#nvx-shell`/`.nvx-*`** (verificado). Grid 264px|1fr; recolhido = `data-side="rail"`
  (72px); mobile (<1020px) esconde a sidebar e mostra a faixa `#nvx-mnav`.
- NEW `public/scripts/shell.js` — recolher/expandir (alterna `data-side="rail"`,
  persiste em `localStorage['sidebarCollapsed']`, mesma chave do shell antigo).
- `includes/layout/header.php` — reescrito o markup do shell (mantendo verbatim o `<head>`
  e toda a lógica de tema/ponte de compatibilidade). Nav em arrays PHP com ícones de linha
  SVG, grupos, detecção de ativo e mapa de título/eyebrow por página.
- `includes/layout/footer.php` — carrega `shell.js`. Os fechadores `</div></main></div>`
  já batiam com a nova estrutura (shell > main > content).

## Decisões e o que foi OMITIDO (sem backend — nada de falso)
- **Sino de notificações: OMITIDO.** Zero backend no projeto. Não fiz sino decorativo.
- **Busca no topo: OMITIDA.** Não há busca livre real; os filtros do Histórico são por
  campo, não full-text. Um campo que não busca seria falso.
- **Seletor de período/mês no topo: OMITIDO.** Não há parâmetro global de mês; o Dashboard
  tem o próprio filtro de período na página. Um seletor global não mapearia para nada.
- **Consequência reportada:** com esses três omitidos, o lado direito da topbar ficaria
  vazio. Então a topbar entrega **eyebrow + título** e o resto do lado direito fica limpo
  (sem controles falsos). O olho de "Ocultar saldos" foi para a sidebar (ver abaixo), que
  é onde a feature está ligada. É honesto e não fica estranho.
- **"Plano Pessoal": NÃO inventado.** O app não tem planos. O rodapé da sidebar mostra
  **nome + email** do usuário.
- **Ocultar saldos:** a feature EXISTE e foi preservada — mesmo `#balance-visibility-btn`
  + `#balance-visibility-icon`/`-text`, mesmo handler
  `/actions/profile/update-balance-visibility.php` (balance-visibility.js intocado). O
  design mostra o olho na sidebar E na topbar; consolidei em **um** controle (sidebar
  footer) para não duplicar id/controle. Mantido o contrato de hoje: renderizado só fora
  do Dashboard (o Dashboard tem o próprio botão no conteúdo — evita id duplicado).
- **Import/Export na nav:** UM item "Import / Export" → `import.php` (que já tem link para
  `export.php`). Fica ativo também em `export.php`. Decisão registrada.
- **Sair:** preservado (form POST para `/public/logout.php` com CSRF), agora no rodapé da
  sidebar com ícone de linha.

## Ícones (nota de fidelidade)
Todos os 15 emojis da nav foram removidos — **nenhum emoji sobra na navegação** (verificado
por grep). Os ícones de linha usam a **família Feather/Lucide**, que é a mesma do design
(confirmada pelos ícones visíveis no trecho capturado: olho, busca, sino, calendário,
chevron). O trecho do script do design com os paths exatos por item (`iconD`) ficou ALÉM
do corte de 256KB do arquivo em cache, então usei os glifos Feather correspondentes a cada
item (dashboard=grid, histórico=relógio, contas=banco, carteiras=wallet, cartões=card,
metas=alvo, categorias=tag, import/export=repeat, moeda=cifrão, perfil=user). São
trocáveis 1:1 se o HTML salvo mostrar escolhas diferentes. (Único "emoji" restante é o do
`#balance-visibility-icon`, que o balance-visibility.js sobrescreve — é um controle, não
nav.)

## Compatibilidade (crítico) — como as 4 telas legadas seguem intactas
- A ponte `data-legacy-theme` / `theme-compat.css` / `NOVIX_THEME_LEGACY` foi **mantida
  verbatim** no `<head>`. Contas, Cartões, Import/Export e Moeda continuam vestidas por
  main.css + themes.css.
- `shell.css` é escopado (`#nvx-shell`/`.nvx-*`) — não vaza para o conteúdo das telas.
- A moldura antiga usava `#sidebar`/`#mainContent` + main.js. O novo shell usa `#nvx-*`;
  o código de sidebar do main.js fica **inerte** (todos os `getElementById` retornam null,
  já guardados por `if`), e as regras `#sidebar` do main.css miram um elemento ausente
  (sem efeito). Verifiquei que nenhuma tela/JS referencia os ids removidos.
- Content wrapper preservado (`.nvx-content-pad`), então as telas legadas seguem com
  padding; as reskinadas trazem seu próprio `.xx-screen`.

## Validation
- `node --check public/scripts/shell.js` → OK.
- header.php: chaves/parênteses PHP balanceados; content wrapper aberto no fim.
- Tags do shell: header deixa 2 `<div>` + 1 `<main>` abertos; footer fecha exatamente
  `</div></main></div>` na ordem certa (content → main → shell).
- Nav sem emoji (grep dos 15 emojis antigos → nenhum). Import/Export e Perfil presentes.
- Contratos JS preservados: `#balance-visibility-btn/-icon/-text` (balance-visibility.js);
  `#nvx-shell`/`#nvx-side-toggle` (shell.js).
- As 10 telas do app incluem header+footer emparelhados; export.php (download CSV) não usa
  o shell — correto.
- **PHP local indisponível** — rodar no servidor: `php -l includes/layout/header.php
  includes/layout/footer.php` e `php tests/smoke.php`.
- Sem migração.

## Riscos / a olhar no teste
- Mudança de MOLDURA global: percorrer as 9 telas e confirmar shell idêntico, item ativo
  correto, recolher funciona, "Ocultar saldos" funciona, e as 4 legadas sem regressão, em
  um tema claro e um escuro.
- Recolhido (rail) esconde rótulos via `.nvx-slab { display:none }`; ícones permanecem.
- Duplo padding leve nas telas reskinadas (wrapper + `.xx-screen`) — igual ao comportamento
  de antes; cosmético.

## Remaining
- Teste manual no servidor.
- Confidence: 83% (pendente `php -l` + verificação visual das 9 telas).
