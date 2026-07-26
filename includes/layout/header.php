<?php

declare(strict_types=1);

require_once __DIR__ . '/../themes.php';

if (!isset($title)) {
    $title = 'NovixFin';
}

// O usuário deve ser passado pela página que inclui o header
$user = $user ?? null;
if (!$user && function_exists('currentUserId')) {
    $headerUserId = currentUserId();
    if ($headerUserId) {
        try {
            $headerUserRepository = new \Novix\Repositories\UserRepository(\Novix\Database\Connection::get());
            $user = $headerUserRepository->findById((int) $headerUserId);
        } catch (Throwable $e) {
            $user = null;
        }
    }
}

// Tema do usuário: slug canônico dos 15 (includes/themes.php).
$userTheme = NOVIX_THEME_DEFAULT;
if (isset($user) && isset($user['theme'])) {
    $userTheme = $user['theme'];
} elseif (isset($_SESSION['user']['theme'])) {
    $userTheme = $_SESSION['user']['theme'];
}
$userTheme = novix_valid_theme($userTheme);

// Ponte de compatibilidade (TRANSITÓRIA): as telas ainda não reskinadas leem
// public/styles/themes.css, que só conhece dark/black/light. O <html> carrega
// o slug real (consumido por tokens.css e pelo shell) e o <body> carrega o
// valor legado, que é o que aqueles seletores casam. Ver includes/themes.php.
$legacyTheme = novix_legacy_theme($userTheme);

?><!DOCTYPE html>
<html lang="pt-BR" class="h-full" data-theme="<?php echo htmlspecialchars($userTheme, ENT_QUOTES, 'UTF-8'); ?>" data-legacy-theme="<?php echo htmlspecialchars($legacyTheme, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($title); ?></title>
    <!-- v3 Phase 9: removed the CDN console.warn-suppression hack (it just
         hid a legitimate warning instead of fixing the root cause). A future
         hardening can swap the CDN for a locally built, purged tailwind.css
         using `npx tailwindcss-cli` — see docs/SETUP.md. For now we keep the
         CDN with the warning visible, which is honest. -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/styles/main.css">
    <link rel="stylesheet" href="/public/styles/themes.css">
    <!-- Aditivo: tokens.css tem SÓ custom properties e theme-compat.css só
         repõe o fundo do <html> para o tema legado. shell.css é escopado sob
         #nvx-shell e não vaza para o conteúdo das telas. -->
    <link rel="stylesheet" href="/public/styles/tokens.css">
    <link rel="stylesheet" href="/public/styles/theme-compat.css">
    <link rel="stylesheet" href="/public/styles/shell.css">
    <link href="<?php echo htmlspecialchars(novix_theme_fonts_url($userTheme), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
    <?php if (isset($pageStyles) && is_array($pageStyles)) : ?>
        <?php foreach ($pageStyles as $pageStyle) : ?>
            <link rel="stylesheet" href="/public/<?php echo sanitize($pageStyle); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <?php

    // v3.1 Task 5: render the privacy class server-side so monetary values
    // are masked on first paint, before deferred JS runs.
    if (isset($user['hide_balances'])) {
        $hideBalances = (bool) $user['hide_balances'];
        $_SESSION['user']['hide_balances'] = $hideBalances;
    } elseif (isset($_SESSION['user']['hide_balances'])) {
        $hideBalances = (bool) $_SESSION['user']['hide_balances'];
    } else {
        $hideBalances = true;
    }
    $bodyClasses = ['min-h-full', 'bg-slate-950', 'text-slate-100', 'font-[Poppins]'];
    if ($hideBalances) {
        $bodyClasses[] = 'hide-balances';
    }
    ?>
</head>
<body class="<?php echo htmlspecialchars(implode(' ', $bodyClasses), ENT_QUOTES, 'UTF-8'); ?>" data-theme="<?php echo htmlspecialchars($legacyTheme, ENT_QUOTES, 'UTF-8'); ?>" data-novix-theme="<?php echo htmlspecialchars($userTheme, ENT_QUOTES, 'UTF-8'); ?>" data-hide-balances-default="<?php echo $hideBalances ? 'true' : 'false'; ?>">
<script>
// O tema (slug) já vem no <html> renderizado pelo servidor — sem flash.
// Mantém localStorage em sincronia para landing/login abrirem no mesmo tema.
try { localStorage.setItem('novixfin-theme', '<?php echo htmlspecialchars($userTheme, ENT_QUOTES, 'UTF-8'); ?>'); } catch (e) {}
// v3.1 Task 5: expose the global CSRF token for AJAX UI helpers
// (balance-visibility.js etc.) so they can post authenticated form actions
// without searching for a nearby hidden input on every page.
window.NOVIX_CSRF_TOKEN = '<?php echo csrfToken(); ?>';
</script>
<?php
// ===== Shell do app: navegação, ícones de linha e título por tela =====
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

// Ícones de linha (família Feather — mesma do design: eye/search/bell). Um
// `d` por ícone (pode conter subpaths separados por espaço).
$novixNavIcons = [
    'dashboard'  => 'M3 3h7v7H3z M14 3h7v7h-7z M14 14h7v7h-7z M3 14h7v7H3z',
    'history'    => 'M12 7v5l3 2 M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z',
    'accounts'   => 'M3 21h18 M4 21V10 M20 21V10 M4 10l8-6 8 6 M9 21v-6h6v6',
    'wallets'    => 'M3 6h15a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a1 1 0 0 1-1-1V6z M3 6V5a1 1 0 0 1 1-1h12 M17 12h.01',
    'cards'      => 'M2 6h20v12H2z M2 10h20 M6 15h4',
    'goals'      => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10z M12 11a1 1 0 1 0 0 2 1 1 0 0 0 0-2z',
    'categories' => 'M20.5 13.5 11 4a2 2 0 0 0-1.4-.6H5a2 2 0 0 0-2 2v4.6A2 2 0 0 0 3.6 11l9.5 9.5a2 2 0 0 0 2.8 0l4.6-4.6a2 2 0 0 0 0-2.8z M7.5 7.5h.01',
    'import'     => 'M17 2l4 4-4 4 M3 11V9a4 4 0 0 1 4-4h14 M7 22l-4-4 4-4 M21 13v2a4 4 0 0 1-4 4H3',
    'currency'   => 'M12 2v20 M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6',
    'profile'    => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M12 3a4 4 0 1 0 0 8 4 4 0 1 0 0-8z',
];

// Agrupamentos conforme o design.
$novixNavMain = [
    ['label' => 'Dashboard', 'href' => '/public/dashboard.php', 'page' => 'dashboard.php', 'icon' => 'dashboard'],
    ['label' => 'Histórico', 'href' => '/public/history.php', 'page' => 'history.php', 'icon' => 'history'],
    ['label' => 'Contas', 'href' => '/public/accounts.php', 'page' => 'accounts.php', 'icon' => 'accounts'],
    ['label' => 'Carteiras', 'href' => '/public/wallets.php', 'page' => 'wallets.php', 'icon' => 'wallets'],
    ['label' => 'Cartões', 'href' => '/public/credit-cards.php', 'page' => 'credit-cards.php', 'icon' => 'cards'],
    ['label' => 'Metas', 'href' => '/public/goals.php', 'page' => 'goals.php', 'icon' => 'goals'],
];
// Import/Export: UM item -> import.php (que tem link para export.php). Marcado
// ativo também quando se está em export.php.
$novixNavGest = [
    ['label' => 'Categorias', 'href' => '/public/categories.php', 'page' => 'categories.php', 'icon' => 'categories'],
    ['label' => 'Import / Export', 'href' => '/public/import.php', 'page' => 'import.php', 'icon' => 'import', 'alt' => ['export.php']],
    ['label' => 'Moeda', 'href' => '/public/currency-settings.php', 'page' => 'currency-settings.php', 'icon' => 'currency'],
    ['label' => 'Perfil', 'href' => '/public/profile.php', 'page' => 'profile.php', 'icon' => 'profile'],
];

// Eyebrow + título por tela (apenas rótulos — nenhum backend novo).
$novixTitles = [
    'dashboard.php'         => ['Visão geral', 'Dashboard'],
    'history.php'           => ['Movimentações', 'Histórico'],
    'accounts.php'          => ['Onde está seu dinheiro', 'Contas'],
    'wallets.php'           => ['Dinheiro em espécie', 'Carteiras'],
    'credit-cards.php'      => ['Faturas e limites', 'Cartões'],
    'goals.php'             => ['Objetivos', 'Metas'],
    'categories.php'        => ['Classificação', 'Categorias'],
    'import.php'            => ['Dados', 'Import / Export'],
    'export.php'            => ['Dados', 'Import / Export'],
    'currency-settings.php' => ['Conversão', 'Moeda'],
    'profile.php'           => ['Sua conta', 'Perfil'],
];

$novixIsActive = function (array $item) use ($currentPage) {
    if ($item['page'] === $currentPage) {
        return true;
    }
    if (!empty($item['alt']) && in_array($currentPage, $item['alt'], true)) {
        return true;
    }
    return false;
};

$novixEyebrow = 'NovixFin';
$novixPageTitle = 'NovixFin';
if (isset($novixTitles[$currentPage])) {
    $novixEyebrow = $novixTitles[$currentPage][0];
    $novixPageTitle = $novixTitles[$currentPage][1];
} elseif (isset($title)) {
    $parts = explode('|', (string) $title);
    $novixPageTitle = trim($parts[0]);
}

$novixIsDashboard = ($currentPage === 'dashboard.php');

// Iniciais do avatar (mbstring guardado — não garantido em todo ambiente).
$novixName = (string) (isset($user['name']) ? $user['name'] : '');
$novixInitials = '';
$novixParts = preg_split('/\s+/', trim($novixName));
$novixHasMb = function_exists('mb_substr') && function_exists('mb_strtoupper') && function_exists('mb_strlen');
if (is_array($novixParts)) {
    foreach ($novixParts as $novixPart) {
        $novixLen = $novixHasMb ? mb_strlen($novixInitials) : strlen($novixInitials);
        if ($novixPart !== '' && $novixLen < 2) {
            $novixInitials .= $novixHasMb ? mb_strtoupper(mb_substr($novixPart, 0, 1)) : strtoupper(substr($novixPart, 0, 1));
        }
    }
}
if (trim($novixInitials) === '') {
    $novixInitials = 'NF';
}

// Fecha o navegador de saldos apenas para telas que NÃO são o dashboard (o
// dashboard renderiza o próprio botão no conteúdo — evita id duplicado).
$novixShowBalanceToggle = function_exists('isAuthenticated') && isAuthenticated() && !$novixIsDashboard;
?>
<div id="nvx-shell">

    <!-- ============ SIDEBAR ============ -->
    <aside id="nvx-side">
        <a href="/public/dashboard.php" class="nvx-sbrand" title="NovixFin">
            <span class="nvx-smark" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="var(--accent-ink)" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18 L10 8 L14 14 L20 5"></path></svg>
            </span>
            <span class="nvx-slogo nvx-slab">novixfin</span>
        </a>

        <nav id="nvx-nav">
            <span class="nvx-group nvx-slab">Principal</span>
            <?php foreach ($novixNavMain as $item) : ?>
                <a href="<?php echo sanitize($item['href']); ?>" class="nvx-navitem" title="<?php echo sanitize($item['label']); ?>"<?php echo $novixIsActive($item) ? ' aria-current="page"' : ''; ?>>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?php echo $novixNavIcons[$item['icon']]; ?>"></path></svg>
                    <span class="nvx-slab"><?php echo sanitize($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
            <span class="nvx-group nvx-slab">Gestão</span>
            <?php foreach ($novixNavGest as $item) : ?>
                <a href="<?php echo sanitize($item['href']); ?>" class="nvx-navitem" title="<?php echo sanitize($item['label']); ?>"<?php echo $novixIsActive($item) ? ' aria-current="page"' : ''; ?>>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?php echo $novixNavIcons[$item['icon']]; ?>"></path></svg>
                    <span class="nvx-slab"><?php echo sanitize($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="nvx-sfoot-wrap">
            <?php if ($novixShowBalanceToggle) : ?>
                <!-- Ocultar saldos: mesmo handler/ids de sempre (balance-visibility.js
                     atualiza #balance-visibility-icon/-text e posta em
                     /actions/profile/update-balance-visibility.php). -->
                <button id="balance-visibility-btn" type="button" class="nvx-sfoot" title="Mostrar/ocultar saldos" aria-label="Mostrar/ocultar saldos">
                    <span id="balance-visibility-icon" aria-hidden="true">&#128065;</span>
                    <span id="balance-visibility-text" class="nvx-slab">Ocultar saldos</span>
                </button>
            <?php endif; ?>

            <?php if ($user) : ?>
                <div class="nvx-srow">
                    <span class="nvx-avatar" aria-hidden="true"><?php echo sanitize($novixInitials); ?></span>
                    <div class="nvx-uinfo nvx-slab">
                        <div class="nvx-uname"><?php echo sanitize($novixName !== '' ? $novixName : 'Usuário'); ?></div>
                        <?php if (!empty($user['email'])) : ?>
                            <div class="nvx-umail"><?php echo sanitize($user['email']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="/public/logout.php" style="width:100%;">
                <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                <button type="submit" class="nvx-sfoot nvx-sfoot--danger" title="Sair">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4 M16 17l5-5-5-5 M21 12H9"></path></svg>
                    <span class="nvx-slab">Sair</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- ============ COLUNA PRINCIPAL ============ -->
    <main id="nvx-main">
        <span id="nvx-sidetog">
            <button id="nvx-side-toggle" type="button" class="nvx-togbtn" title="Recolher/expandir menu" aria-label="Recolher ou expandir o menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m14 6-6 6 6 6"></path></svg>
            </button>
        </span>

        <!-- TOPBAR: eyebrow + título. Controles do design (busca, seletor de mês,
             sino) foram OMITIDOS por não terem backend — ver checkpoint. O olho
             de ocultar saldos fica na sidebar (onde a feature é ligada). -->
        <header id="nvx-top">
            <div class="nvx-top-inner">
                <div style="min-width:0;">
                    <div class="nvx-eyebrow"><?php echo sanitize($novixEyebrow); ?></div>
                    <h1 class="nvx-pagetitle"><?php echo sanitize($novixPageTitle); ?></h1>
                </div>
            </div>
        </header>

        <!-- NAV MOBILE (faixa horizontal, só < 1020px) -->
        <nav id="nvx-mnav" aria-label="Navegação">
            <?php foreach (array_merge($novixNavMain, $novixNavGest) as $item) : ?>
                <a href="<?php echo sanitize($item['href']); ?>" class="nvx-mnav-item"<?php echo $novixIsActive($item) ? ' aria-current="page"' : ''; ?>>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?php echo $novixNavIcons[$item['icon']]; ?>"></path></svg>
                    <?php echo sanitize($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="nvx-content nvx-content-pad">
