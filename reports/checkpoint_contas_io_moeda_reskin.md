# Checkpoint — Fatia CONTAS + IMPORT/EXPORT + MOEDA (reskin tokenizado)

Data: 2026-07-25

## Tarefa
Re-skinar as três telas restantes de risco baixo/médio com o design tokenizado.
Cartões (credit-cards.php) fica de fora — fatia própria depois. A ponte de
compatibilidade (data-legacy-theme / theme-compat.css / NOVIX_THEME_LEGACY)
CONTINUA valendo (Cartões ainda usa o CSS legado). Parar para revisão.

Fonte visual: Claude Design "NovixFin App.html" (cache scratchpad/app-src.html):
CONTAS (l.998-1082), IMPORT/EXPORT (l.1282-1425), MOEDA (l.1428-1527).

## Files changed
- NEW public/styles/accounts.css (escopo .accounts-screen + .acc-overlay p/ modais)
- NEW public/styles/io.css (escopo .io-screen)
- NEW public/styles/moeda.css (escopo .moeda-screen)
- public/accounts.php — cards no layout do design; charts removidos; modais themados
- public/import.php — tela IO (Import + Export lado a lado); upload/preview/commit preservados
- public/currency-settings.php — layout Moeda; todos os names/ids + toggle JS preservados
- public/export.php — NÃO tocado (é endpoint de download puro, sem UI)

## CONTAS (accounts.php)
- Grid de cards fiel ao design (inline var(--token)): ícone (emoji do usuário), nome,
  badge Inativa, tipo em PT, editar/excluir, saldo (--f-num, cor --neg se negativo),
  moedas secundárias, ↑receitas/↓despesas, bloco de cartão (limite/fecha/vence/juros/
  Ver fatura) quando type=credit, rodapé com os DOIS toggles.
- **DOIS TOGGLES preservados** como forms POST com CSRF, handlers intactos:
  - toggle-dashboard.php ("Fixar no dashboard" / "No dashboard") — controla a quebra
    por conta do HERO do dashboard. Visível e funcional.
  - toggle.php (Ativar/Desativar).
- **Modais (criar/editar #accountModal, excluir #deleteModal) preservados**: mesmos
  ids/names/handlers (save.php/delete.php) + todo o `<script>` (openModal/editAccount/
  confirmDelete/toggleCreditFields/close-outside). Continuam com markup Tailwind,
  themados por accounts.css (mapa utilitárias->tokens escopado sob .acc-overlay).
  Confirmação de exclusão intacta (texto + checkbox "excluir transações").
- Emojis decorativos 📌/🗑️ trocados por SVG (olho / lixeira). Ícone da conta é dado
  do usuário (mantido).
- **REMOVIDO**: os gráficos doughnut "Despesas por Conta / Receitas por Conta" — NÃO
  existem na tela CONTAS do design e usavam cores fixas (violam o invariante de
  gráficos lendo tokens). Removidos com o script inline deles. Registrado como
  candidato a fatia futura se quiserem de volta (tokenizados).

## IMPORT / EXPORT (import.php + export.php)
- **export.php é endpoint de download puro (sem HTML)** — não há o que reskinar nele;
  ficou intocado. As OPÇÕES de exportação do design viram um form GET em import.php
  (Formato CSV/JSON + Escopo transações/contas/metas/tudo) que aponta para export.php.
  Assim a tela IO fica como o design (Import + Export lado a lado) e o download segue
  pelo mesmo endpoint.
- Import preservado: upload multipart (csv_file), action=preview, action=commit,
  confirm=1, CSRF — nomes idênticos, fluxo de 2 estágios intacto. Preview com
  overflow-x preservado (auditoria confirmou). Estado idle e preview reskinados.
- ADICIONADO (dado real, read-only): "Seus IDs de referência" lista contas e
  categorias do usuário (id+nome) para montar o CSV. Duas queries SELECT simples no
  markup (não tocam saldo/atomicidade).
- OMITIDO (honesto): botão "Baixar modelo CSV" do design — não há endpoint de modelo;
  não inventei um. Export CSV de transações já serve de referência de formato.

## MOEDA (currency-settings.php)
- Layout do design: coluna esquerda (Moedas: principal + habilitadas; Converter tudo)
  + coluna direita (Cotações manuais em 3 pares).
- **Todos os campos preservados** (names/ids): primary_currency, enabled_currencies[],
  convert_all_to_one (#convert-all-toggle), conversion_target_currency
  (#conversion-target), os 6 conversion_rate_* e o submit para save.php. O JS legado
  do toggle (#convert-all-toggle -> mostra/esconde #conversion-target via .hidden) foi
  preservado; moeda.css define .hidden{display:none}.
- O checkbox "Converter tudo" virou um switch (o input real #convert-all-toggle segue
  lá, só estilizado como trilho+bolinha) — o JS continua observando o mesmo input.
- As 6 taxas alimentam os chips de câmbio USD/EUR do hero do dashboard — lógica
  intocada, só o visual mudou.

## Ponte de compatibilidade
Mantida. theme-compat.css / data-legacy-theme / NOVIX_THEME_LEGACY inalterados —
Cartões (única tela não reskinada) ainda depende do CSS legado.

## Qualidade
--f-num nos valores; --accent-text no texto pequeno colorido; toggles/switch por
tokens; focus-visible em cada tela; SVG no lugar de emoji decorativo; CSS 100%
escopado (.accounts-screen/.acc-overlay, .io-screen, .moeda-screen); sem travessões.
Nenhum JS destas telas lê data-theme legado (verificado por grep).

## Validação
- Local: PHP indisponível. Estático OK:
  - accounts.php tags 62/62, chaves 49/49; markup inserido balanceado; os 2 <script>
    passam `node --check`; modais com acc-overlay; 0 resquício dos charts.
  - import.php: meu markup inserido parens 38/38, chaves 2/2 (o "desbalanço" global é
    ruído do stripper sobre a lógica ORIGINAL intocada 1-175, PHP válido de produção).
  - currency-settings.php tags 47/47; todos os 12 names/ids conferidos presentes.
- Servidor (pendente): `php -l public/accounts.php public/import.php public/currency-settings.php`
  e `php tests/smoke.php`.

## Estado das 9 telas do app
Reskinadas/tokenizadas: Entrada, Landing, Perfil, Shell, Dashboard, Histórico,
**Contas, Import/Export, Moeda**. Falta só **CARTÕES (credit-cards.php)**.
Quando Cartões for reskinada, a ponte de compatibilidade poderá ser removida.

## TESTE (servidor)
Contas: criar/editar/excluir; os dois toggles; toggle-dashboard reflete no hero.
Import: subir CSV e ver preview; confirmar importa. Export: baixar (CSV/JSON, escopos).
Moeda: salvar uma taxa e ver refletir nos chips do dashboard. Um tema escuro e um
claro. Cartões (legada) e demais sem regressão.

## Confiança
80% — reskin estático consistente e contratos preservados; pende `php -l` + smoke no
servidor e o passe visual.
