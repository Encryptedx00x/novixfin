<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    require_once __DIR__ . '/../includes/bootstrap.php';
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/csrf.php';
} catch (Throwable $e) {
    error_log('Bootstrap error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    die('Erro ao carregar aplicação. Verifique os logs do servidor.');
}

if (isAuthenticated()) {
    redirect('dashboard.php');
}

$email = '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF
        $token = $_POST['_token'] ?? '';
        if (empty($token)) {
            throw new RuntimeException('Token CSRF não fornecido.');
        }
        verifyCsrf($token);

        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: '';
        $password = $_POST['password'] ?? '';

        // v3 W2 fix: server-side throttle keyed by (email + IP) and stored
        // in the `login_attempts` table. Clearing cookies DOES NOT reset
        // the counter — the DB row survives. The pre-v3 session-based
        // counter was keyed on the session ID cookie, so an attacker who
        // omitted cookies got unlimited attempts.
        $identifier = throttle_identifier((string) $email);

        if (is_throttled($identifier, 5, 300)) {
            // Record this attempt too so the window keeps extending while
            // the user keeps hammering — the cool-down only starts ticking
            // once attempts actually stop.
            record_failed_attempt($identifier);
            $error = 'Muitas tentativas. Tente novamente em alguns minutos.';
        } elseif (!$email || !$password) {
            $error = 'Informe email e senha válidos.';
        } else {
            // Tenta autenticar
            if (authenticate($email, $password)) {
                clear_failed_attempts($identifier);
                flash('success', 'Login realizado com sucesso!');
                redirect('dashboard.php');
            } else {
                record_failed_attempt($identifier);
                $error = 'Credenciais inválidas.';
            }
        }
    } catch (Throwable $e) {
        error_log('Login error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $error = 'Erro ao processar login. Tente novamente.';
    }
}

// Pega mensagens flash se houver
if (empty($error)) {
    $error = flash('error') ?? '';
}
$success = flash('success') ?? '';

// Entrada slice: banner flavor. Throttle -> gold clock, other errors ->
// negative triangle, success -> positive check (design v4 states).
$isThrottleError = $error !== '' && strpos($error, 'Muitas tentativas') === 0;

require_once __DIR__ . '/../includes/layout/entrada.php';

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novix Fin | Entrar</title>
    <?php novix_entrada_head(); ?>
</head>
<body>
    <div id="lg-shell">
        <?php novix_entrada_brand(
            'De volta ao seu mês lapidado.',
            'Saldos, previsão e metas exatamente onde você deixou.'
        ); ?>

        <div id="lg-formcol">
            <span aria-hidden="true" class="lg-glow-a"></span>
            <span aria-hidden="true" class="lg-glow-b"></span>

            <form method="POST" action="" class="lg-form">
                <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                <div>
                    <div class="lg-title">Entrar</div>
                    <div class="lg-sub">Sua conta, seus números. Só você vê.</div>
                </div>

                <?php if ($error) : ?>
                    <div role="alert" class="lg-banner <?php echo $isThrottleError ? 'lg-banner--gold' : 'lg-banner--neg'; ?>">
                        <?php if ($isThrottleError) : ?>
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l2.5 2.5 M21 12a9 9 0 1 1-9-9 9 9 0 0 1 9 9z"></path></svg>
                        <?php else : ?>
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2 21h20z M12 9v5 M12 18h.01"></path></svg>
                        <?php endif; ?>
                        <div style="min-width:0;">
                            <div class="lg-banner-title"><?php echo sanitize($error); ?></div>
                            <?php if ($isThrottleError) : ?>
                                <div class="lg-banner-body">O bloqueio é automático após 5 tentativas em 5 minutos e expira sozinho.</div>
                            <?php else : ?>
                                <div class="lg-banner-body">Confira o email e a senha e tente de novo.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success) : ?>
                    <div role="alert" class="lg-banner lg-banner--pos">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                        <div style="min-width:0;">
                            <div class="lg-banner-title"><?php echo sanitize($success); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <label class="lg-field">
                    <span class="lg-label">Email</span>
                    <input type="email" id="email" name="email" value="<?php echo sanitize($email); ?>" required placeholder="voce@email.com" autocomplete="email" class="lg-input">
                </label>
                <label class="lg-field">
                    <span class="lg-label">Senha</span>
                    <input type="password" id="password" name="password" required placeholder="••••••••" autocomplete="current-password" class="lg-input">
                </label>
                <button type="submit" class="lg-btn">Entrar</button>
                <?php if ($isThrottleError) : ?>
                    <div class="lg-throttle-note">O bloqueio é automático após 5 tentativas em 5 minutos e expira sozinho.</div>
                <?php endif; ?>
                <div class="lg-switch">
                    <span class="lg-switch-q">Primeira vez aqui?</span>
                    <a href="/public/register.php" class="lg-switch-go">Criar conta</a>
                    <a href="/public/index.php" class="lg-back"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>Voltar</a>
                </div>
            </form>

            <div class="lg-theme-note">Tema herdado da sua escolha na página inicial.</div>
        </div>
    </div>
    <?php novix_entrada_parallax_script(); ?>
</body>
</html>
