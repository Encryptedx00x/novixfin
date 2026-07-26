<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/themes.php';
requireAuth();

use Novix\Database\Connection;
use Novix\Repositories\UserRepository;
use Novix\Services\GamificationService;

$pdo = Connection::get();
$userRepository = new UserRepository($pdo);

$userId = currentUserId() ?? 0;
$user = $userRepository->findById($userId);

$success = flash('success');
$error = flash('error');

$currentTheme = novix_valid_theme(isset($user['theme']) ? $user['theme'] : NOVIX_THEME_DEFAULT);
$hideBalancesPref = isset($user['hide_balances']) ? (bool) $user['hide_balances'] : true;

// ---------------------------------------------------------------------------
// Gamificação com dados REAIS de users.points / users.level / users.badges.
// Os limiares vêm de GamificationService (mesma fonte que concede os pontos),
// e não de valores repetidos aqui.
// ---------------------------------------------------------------------------
$points = (int) (isset($user['points']) ? $user['points'] : 0);
$level = (string) (isset($user['level']) && $user['level'] !== '' ? $user['level'] : 'Iniciante');

// Próximo nível: menor limiar acima dos pontos atuais.
$levelThresholds = GamificationService::LEVEL_THRESHOLDS; // [pontos => nome], desc
$ascending = array_reverse($levelThresholds, true);       // [0 => Iniciante, ...]
$currentFloor = 0;
$nextThreshold = null;
$nextLevelName = null;
foreach ($ascending as $threshold => $levelName) {
    if ($points >= $threshold) {
        $currentFloor = (int) $threshold;
    } elseif ($nextThreshold === null) {
        $nextThreshold = (int) $threshold;
        $nextLevelName = $levelName;
    }
}

if ($nextThreshold === null) {
    $levelPct = 100;
    $nextNote = 'Nível máximo alcançado';
} else {
    $span = $nextThreshold - $currentFloor;
    $levelPct = $span > 0 ? (int) round((($points - $currentFloor) / $span) * 100) : 0;
    if ($levelPct < 0) {
        $levelPct = 0;
    }
    if ($levelPct > 100) {
        $levelPct = 100;
    }
    $nextNote = number_format($nextThreshold - $points, 0, ',', '.') . ' pts para ' . $nextLevelName;
}

// Conquistas: badges gravadas + as ainda bloqueadas, com o critério real
// (todas as milestones de BADGE_MILESTONES são limiares de pontos).
$earnedBadges = [];
if (isset($user['badges']) && is_string($user['badges']) && $user['badges'] !== '') {
    $decodedBadges = json_decode($user['badges'], true);
    if (is_array($decodedBadges)) {
        foreach ($decodedBadges as $badgeSlug) {
            if (is_string($badgeSlug)) {
                $earnedBadges[$badgeSlug] = true;
            }
        }
    }
}

$badgeLabels = [
    'first_transaction' => ['icon' => '🎯', 'name' => 'Primeiro lançamento'],
    'ten_transactions'  => ['icon' => '📊', 'name' => 'Pegando o ritmo'],
    'goal_complete'     => ['icon' => '🏅', 'name' => 'Constância'],
    'dedicated'         => ['icon' => '💎', 'name' => 'Dedicação'],
    'legendary'         => ['icon' => '👑', 'name' => 'Lendário'],
];

$badgeList = [];
foreach (GamificationService::BADGE_MILESTONES as $threshold => $slug) {
    $meta = isset($badgeLabels[$slug]) ? $badgeLabels[$slug] : ['icon' => '🏆', 'name' => $slug];
    $badgeList[] = [
        'slug' => $slug,
        'icon' => $meta['icon'],
        'name' => $meta['name'],
        'criterio' => number_format((int) $threshold, 0, ',', '.') . ' pontos',
        'earned' => isset($earnedBadges[$slug]) || $points >= (int) $threshold,
    ];
}
$earnedCount = 0;
foreach ($badgeList as $badgeItem) {
    if ($badgeItem['earned']) {
        $earnedCount++;
    }
}

// "Como os pontos entram": SOMENTE eventos realmente ligados no backend.
// tx_created_effective/tx_created_pending: actions/transactions/create.php e
// public/import.php. bill_paid: actions/credit-cards/pay-bill.php.
// goal_completed (+50) e spending_limit_held (+20) existem no service mas
// NENHUM caller os dispara ainda, então não aparecem como ativos.
$eventPoints = GamificationService::EVENT_POINTS;
$pointRules = [
    ['pts' => '+' . (int) $eventPoints['tx_created_effective'], 'label' => 'Transação efetivada'],
    ['pts' => '+' . (int) $eventPoints['bill_paid'], 'label' => 'Fatura de cartão paga'],
    ['pts' => '+' . (int) $eventPoints['tx_created_pending'], 'label' => 'Transação lançada como pendente'],
];

// Próximos passos: só ações que realmente creditam pontos hoje.
$nextSteps = [
    ['label' => 'Lançar uma transação', 'onde' => 'Dashboard', 'pts' => '+' . (int) $eventPoints['tx_created_effective'], 'href' => '/public/dashboard.php'],
    ['label' => 'Pagar uma fatura de cartão', 'onde' => 'Cartões', 'pts' => '+' . (int) $eventPoints['bill_paid'], 'href' => '/public/credit-cards.php'],
    ['label' => 'Importar seu extrato em CSV', 'onde' => 'Import / Export', 'pts' => '+' . (int) $eventPoints['tx_created_effective'] . ' cada', 'href' => '/public/import.php'],
];

// Iniciais para o avatar. mbstring não é usado em nenhum outro ponto do
// projeto, então usamos as funções multibyte só quando existirem.
$displayName = (string) (isset($user['name']) ? $user['name'] : '');
$initials = '';
$nameParts = preg_split('/\s+/', trim($displayName));
$hasMb = function_exists('mb_substr') && function_exists('mb_strtoupper') && function_exists('mb_strlen');
if (is_array($nameParts)) {
    foreach ($nameParts as $part) {
        $len = $hasMb ? mb_strlen($initials) : strlen($initials);
        if ($part !== '' && $len < 2) {
            $initials .= $hasMb
                ? mb_strtoupper(mb_substr($part, 0, 1))
                : strtoupper(substr($part, 0, 1));
        }
    }
}
if (trim($initials) === '') {
    $initials = 'NF';
}

$title = 'Perfil | NovixFin';
$pageStyles = ['styles/profile.css'];

include __DIR__ . '/../includes/layout/header.php';

?>

<section class="pf-screen">
    <div>
        <h1 class="pf-head-title">Perfil</h1>
        <p class="pf-head-sub">Sua conta, o tema do app e o seu progresso.</p>
    </div>

    <?php if ($success) : ?>
        <div class="pf-alert pf-alert--ok" role="status"><?php echo sanitize($success); ?></div>
    <?php endif; ?>

    <?php if ($error) : ?>
        <div class="pf-alert pf-alert--err" role="alert"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <div class="pf-grid">
        <!-- ============ COLUNA ESQUERDA ============ -->
        <div class="pf-col">
            <!-- Sua conta — handler inalterado: /actions/profile/update.php -->
            <div class="pf-card">
                <div class="pf-account-head">
                    <span class="pf-avatar"><?php echo sanitize($initials); ?></span>
                    <div>
                        <div class="pf-card-title">Sua conta</div>
                        <div class="pf-card-sub">Nome e email de acesso.</div>
                    </div>
                </div>

                <form method="POST" action="/actions/profile/update.php" class="pf-col" style="gap:14px;">
                    <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                    <label class="pf-field">
                        <span class="pf-label">Nome completo</span>
                        <input type="text" id="name" name="name" required minlength="3"
                               value="<?php echo sanitize($displayName); ?>" class="pf-input">
                    </label>
                    <label class="pf-field">
                        <span class="pf-label">Email</span>
                        <input type="email" id="email" name="email" required
                               value="<?php echo sanitize(isset($user['email']) ? $user['email'] : ''); ?>" class="pf-input">
                    </label>
                    <button type="submit" class="pf-btn">Salvar alterações</button>
                </form>

                <!-- v3.1 Task 5: ocultar saldos — form próprio, onchange->submit,
                     handler inalterado: /actions/profile/update-balance-visibility.php -->
                <form method="POST" action="/actions/profile/update-balance-visibility.php">
                    <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="redirect_to" value="profile.php">
                    <input type="hidden" name="hide_balances" value="0">
                    <div class="pf-switch-row">
                        <div style="flex:1; min-width:0;">
                            <div class="pf-switch-label">Ocultar saldos</div>
                            <div class="pf-switch-help">Troca todos os valores por •••• no app inteiro, bom pra abrir em público. O atalho do olho no topo faz o mesmo.</div>
                        </div>
                        <span class="pf-switch">
                            <input type="checkbox" name="hide_balances" value="1"
                                   role="switch"
                                   aria-label="Ocultar saldos por padrão"
                                   <?php echo $hideBalancesPref ? 'checked' : ''; ?>
                                   onchange="this.form.submit()">
                            <span class="pf-switch-track" aria-hidden="true"></span>
                            <span class="pf-switch-knob" aria-hidden="true"></span>
                        </span>
                    </div>
                </form>
            </div>

            <!-- Tema — 15 swatches, handler inalterado: /actions/profile/update-theme.php -->
            <div class="pf-card">
                <div>
                    <div class="pf-card-title">Tema</div>
                    <div class="pf-card-sub">Sua pele do NovixFin, aplica na hora em todas as telas.</div>
                </div>
                <form method="POST" action="/actions/profile/update-theme.php" id="pf-theme-form">
                    <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                    <!-- O slug vai no name/value do botão clicado. Sem input
                         hidden "theme" para não enviar o campo duplicado. -->
                    <div class="pf-swatches" role="group" aria-label="Escolha o tema do app">
                        <?php foreach (NOVIX_THEME_META as $themeSlug => $themeMeta) : ?>
                            <?php $isActive = ($themeSlug === $currentTheme); ?>
                            <button type="submit" name="theme" value="<?php echo sanitize($themeSlug); ?>"
                                    class="pf-swatch"
                                    data-theme-slug="<?php echo sanitize($themeSlug); ?>"
                                    aria-pressed="<?php echo $isActive ? 'true' : 'false'; ?>"
                                    title="<?php echo sanitize($themeMeta['name']); ?>">
                                <span class="pf-swatch-dot" style="background: <?php echo sanitize($themeMeta['c1']); ?>;">
                                    <span class="pf-swatch-c2" style="background: <?php echo sanitize($themeMeta['c2']); ?>;"></span>
                                    <span class="pf-swatch-c3" style="background: <?php echo sanitize($themeMeta['c3']); ?>;"></span>
                                </span>
                                <span class="pf-swatch-name"><?php echo sanitize($themeMeta['name']); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </form>
                <p class="pf-note">O tema fica salvo na sua conta e também abre assim na página inicial e no login.</p>
            </div>
        </div>

        <!-- ============ COLUNA DIREITA ============ -->
        <div class="pf-col">
            <!-- Seu progresso (dados reais de users.points / users.level) -->
            <div class="pf-card" style="gap:15px;">
                <div class="pf-card-title">Seu progresso</div>
                <div class="pf-points-row">
                    <div>
                        <?php // pontos não são valor monetário: sem data-balance, não devem ser mascarados. ?>
                        <span class="pf-points"><?php echo number_format($points, 0, ',', '.'); ?></span><span class="pf-points-unit">pontos</span>
                    </div>
                    <span class="pf-level-pill"><?php echo sanitize($level); ?></span>
                </div>
                <div>
                    <div class="pf-track">
                        <span style="width: <?php echo (int) $levelPct; ?>%;"></span>
                    </div>
                    <div class="pf-track-legend">
                        <span><?php echo sanitize($level); ?></span>
                        <span><?php echo sanitize($nextNote); ?></span>
                    </div>
                </div>
                <div class="pf-rules">
                    <span class="pf-rules-title">Como os pontos entram</span>
                    <?php foreach ($pointRules as $rule) : ?>
                        <div class="pf-rule">
                            <span class="pf-rule-pts"><?php echo sanitize($rule['pts']); ?></span>
                            <span class="pf-rule-label"><?php echo sanitize($rule['label']); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <span class="pf-note">Os pontos entram sozinhos, junto com a ação. Nada pra resgatar ou expirar.</span>
                </div>
            </div>

            <!-- Conquistas (badges reais de users.badges) -->
            <div class="pf-card" style="gap:13px;">
                <div style="display:flex; align-items:baseline; gap:10px;">
                    <span class="pf-card-title">Conquistas</span>
                    <span class="pf-badge-count"><?php echo (int) $earnedCount; ?> de <?php echo count($badgeList); ?></span>
                </div>
                <div class="pf-badges">
                    <?php foreach ($badgeList as $badge) : ?>
                        <div class="pf-badge <?php echo $badge['earned'] ? 'pf-badge--earned' : 'pf-badge--locked'; ?>">
                            <span class="pf-badge-icon" aria-hidden="true"><?php echo $badge['icon']; ?></span>
                            <div class="pf-badge-body">
                                <div class="pf-badge-name"><?php echo sanitize($badge['name']); ?></div>
                                <div class="pf-badge-crit"><?php echo sanitize($badge['criterio']); ?></div>
                            </div>
                            <span class="pf-badge-st <?php echo $badge['earned'] ? 'pf-badge-st--earned' : 'pf-badge-st--locked'; ?>">
                                <?php echo $badge['earned'] ? 'Conquistada' : 'A caminho'; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Próximos passos -->
            <div class="pf-card" style="gap:12px;">
                <div>
                    <div class="pf-card-title">Próximos passos</div>
                    <div class="pf-card-sub">Sem pressa, cada um vale pontos quando acontecer.</div>
                </div>
                <?php foreach ($nextSteps as $step) : ?>
                    <a href="<?php echo sanitize($step['href']); ?>" class="pf-step">
                        <span class="pf-step-body">
                            <span class="pf-step-label"><?php echo sanitize($step['label']); ?></span>
                            <span class="pf-step-where"><?php echo sanitize($step['onde']); ?></span>
                        </span>
                        <span class="pf-step-pts"><?php echo sanitize($step['pts']); ?></span>
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="var(--muted)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex:none;" aria-hidden="true"><path d="m10 6 6 6-6 6"></path></svg>
                    </a>
                <?php endforeach; ?>
                <p class="pf-note">
                    Prefere ajustar moedas? <a href="/public/currency-settings.php" class="pf-link-inline">Configurações de moeda</a>.
                </p>
            </div>
        </div>
    </div>
</section>

<script>
// Sincroniza localStorage['novixfin-theme'] no clique do swatch, para a landing
// e o login abrirem no mesmo tema. O submit segue normal para o handler PHP,
// que continua sendo a fonte da verdade (users.theme).
(function () {
    var form = document.getElementById('pf-theme-form');
    if (!form) return;
    form.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-theme-slug]') : null;
        if (!btn) return;
        var slug = btn.getAttribute('data-theme-slug');
        if (!slug) return;
        try { localStorage.setItem('novixfin-theme', slug); } catch (err) {}
    });
})();
</script>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
