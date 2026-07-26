# Checkpoint — Fatia Perfil (theming aditivo do shell + reskin do Perfil)

Date: 2026-07-22

## Task
Estabelecer o theming do shell de forma ADITIVA e re-skinar a tela de Perfil com o
design tokenizado, incluindo o seletor de 15 temas. Nenhum arquivo de core financeiro
tocado (saldo, cartão, recorrência, atomicidade intactos).

## 1) DIAGNÓSTICO (feito antes de qualquer edição)

**O que as telas internas renderizam hoje:**
- `public/styles/themes.css` (819 linhas) só tem regras para os valores legados:
  `dark` (10 regras), `black` (144), `light` (186). Não conhece nenhum dos 15 slugs.
- Depois da migração da fatia Entrada, `users.theme` guarda slugs novos. Como
  `data-theme="ametista"` não casa com nenhuma regra, **todas as 9 telas internas caem
  no visual base do Tailwind (slate-950 escuro)**, independente do tema escolhido.
- **Regressão já em produção detectada:** a migração mapeou `light -> ametista`. Quem
  usava o tema claro perdeu o tema claro nas telas internas desde aquela fatia — hoje
  vê escuro. Não era intencional.

**Forma dos seletores legados (o que viabiliza a ponte):**
- `body[data-theme="light"]`: 88 ocorrências
- `[data-theme="light"] .classe` (descendentes do body): 96 ocorrências
- `html[data-theme="light"]`: apenas 2 (ambas só definem o fundo do `<html>`)

**Consumidores JS de data-theme:** só `public/scripts/dashboard.js` (cores da legenda
dos gráficos, via `theme === 'light'`).

## 2) ESTRATÉGIA DE COMPATIBILIDADE ESCOLHIDA (uma só, documentada)

**Ponte de atributo duplo**, porque 184 das 186 regras legadas casam por `<body>` ou
por descendência:

```
<html data-theme="<slug>"  data-legacy-theme="<dark|light>">
<body data-theme="<legado>" data-novix-theme="<slug>">
```

- `<html>` carrega o slug real → consumido por `tokens.css` e pelo Perfil.
- `<body>` carrega o valor legado → `themes.css` continua vestindo as 9 telas.
- As 2 regras ancoradas no `<html>` (fundo da página) são repostas por
  `public/styles/theme-compat.css` via o atributo novo `data-legacy-theme`.

**Mapeamento (NOVIX_THEME_LEGACY em includes/themes.php):**
- Slugs claros -> `light`: ametista, cafe, banca, suica, pulso, concreto, vitrola, sumie
- Slugs escuros -> `dark`: sereno2, sereno1, cofre, tinta, adega, cabine, deco

Escolhi `dark` (e não `black`) para os escuros de propósito: `dark` é o default
histórico, então **usuários de tema escuro não veem nenhuma mudança**. Só os 8 slugs
claros mudam, e mudam para o tema claro que o usuário de fato escolheu — restaurando a
regressão descrita acima com CSS já testado e polido (ver
`reports/checkpoint_p1_light_theme_polish.md`).

## 3) Files changed

- NEW `public/styles/tokens.css` — tokens dos 15 temas, extraídos do bloco canônico do
  design do App (inclui `--f-num` e `--accent-text`, que entrada/landing não têm).
  **Garantia aditiva verificada mecanicamente:** 0 seletores fora de
  `:root`/`html[data-theme=...]` e 0 declarações que não sejam custom properties.
- NEW `public/styles/theme-compat.css` — repõe só o fundo do `<html>` do tema legado.
- NEW `public/styles/profile.css` — estilos do Perfil, **100% escopados sob `.pf-screen`**.
- `includes/themes.php` — adicionados `NOVIX_THEME_META` (nome + 3 cores do swatch),
  `NOVIX_THEME_FONTS` + `novix_theme_fonts_url()`, `NOVIX_THEME_LEGACY` +
  `novix_legacy_theme()`. Whitelist e `novix_valid_theme()` reusados, não recriados.
- `includes/layout/header.php` — fallback `'dark'` -> `NOVIX_THEME_DEFAULT` validado por
  `novix_valid_theme()`; tema calculado antes do `<html>` (server-side, sem flash);
  atributos da ponte; carrega tokens.css + theme-compat.css + fontes do tema; novo hook
  `$pageStyles` (espelha o `$pageScripts` que o footer já usava); o script inline agora
  sincroniza `localStorage['novixfin-theme']` em vez de setar data-theme.
- `public/scripts/dashboard.js` — `isLight` passa a ler `data-legacy-theme` (o `<html>`
  agora carrega slug). Previne legenda escura sobre fundo claro. 1 linha de lógica.
- `src/Services/GamificationService.php` — `LEVEL_THRESHOLDS`, `EVENT_POINTS` e
  `BADGE_MILESTONES` de `private` para `public`. **Só visibilidade, zero comportamento** —
  para o Perfil ler a mesma fonte da verdade em vez de duplicar limiares na view.
- `public/profile.php` — reskin completo (ver abaixo).

## 4) Perfil: o que é real vs. o que foi deliberadamente omitido

**Os 3 forms e handlers preservados, sem alteração:**
- `/actions/profile/update.php` (nome + email)
- `/actions/profile/update-theme.php` (15 swatches, `onchange`->submit substituído por
  submit do próprio botão; handler NÃO tocado, já validava os 15)
- `/actions/profile/update-balance-visibility.php` (toggle, `onchange="this.form.submit()"`
  preservado, incluindo o hidden `hide_balances=0` e o `redirect_to`)
- CSRF em todos os 3.

**Gamificação com dados REAIS** (`users.points` / `users.level` / `users.badges`),
sem o mock do design (Marina/480):
- Barra de progresso calculada a partir de `GamificationService::LEVEL_THRESHOLDS`.
- Conquistas lidas do JSON de `users.badges` + estado bloqueado/conquistado.
  Critério exibido honestamente como limiar de **pontos**, porque
  `recomputeBadges()` concede por pontos (inclusive a badge chamada `goal_complete`).
- "Como os pontos entram" lista **só os eventos com caller real**:
  +10 transação efetivada, +15 fatura paga, +2 transação pendente.
  **`goal_completed` (+50) e `spending_limit_held` (+20) NÃO aparecem** — existem no
  service mas nenhum caller os dispara (verificado por grep em actions/, src/, public/).
- "Próximos passos" aponta só para ações que creditam pontos hoje.

**Seletor de 15 temas:** swatches com as 3 cores do design, `aria-pressed` no ativo
(reflete `users.theme`), submit direto pro handler existente, e sync de
`localStorage['novixfin-theme']` no clique para landing/login abrirem igual.

**Detalhes corrigidos durante a implementação:**
- Removido `data-balance` dos pontos (pontos não são valor monetário; seriam mascarados
  pelo modo privacidade).
- Removido input hidden `theme` duplicado (evita enviar o campo duas vezes e depender
  do "último vence" do PHP).
- `mb_*` guardado por `function_exists()` — mbstring não é usado em nenhum outro ponto
  do projeto e não é garantido no shared hosting.

## 5) Validation
- Varredura PHP 7.4 (match, `?->`, catch sem variável, union types, promoção no
  construtor, `mixed`, str_contains/str_starts_with): **limpo** nos 4 PHP tocados.
  Os 2 `catch` sinalizados eram JavaScript (`catch (err)`, `catch (e)`).
- Chaves/parênteses balanceados nos arquivos tocados.
- `node --check public/scripts/dashboard.js` -> OK.
- Garantia aditiva do tokens.css verificada por script (0 regras de componente).
- **PHP local indisponível** — rodar no servidor:
  `php -l public/profile.php includes/layout/header.php includes/themes.php src/Services/GamificationService.php`
  e `php tests/smoke.php`.
- Sem migração. Sem mudança de schema.

## 6) Riscos
- As 8 slugs claras passam a renderizar o tema claro legado nas 9 telas internas. É CSS
  já shipado e polido, mas é mudança visível — **confirmar no teste manual** (é o item
  central da revisão).
- `tokens.css` duplica os tokens de entrada.css/landing.css (ambos em produção, não
  tocados nesta fatia). Dívida registrada no progress para consolidação futura.
- Perfil mistura sidebar legada + conteúdo tokenizado: estado transitório esperado
  enquanto as outras telas não são reskinadas.

## 7) Remaining
- Teste manual no servidor (ver TESTE no progress).
- Confidence: 86% (pendente php -l + verificação visual das 9 telas).
