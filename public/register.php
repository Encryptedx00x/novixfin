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

$name = '';
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

        // v3 W2 fix: IP-only register throttle. Pre-v3 had NO throttle on
        // registration at all, so an attacker could spam-account from a
        // single IP without any cooldown. The identifier is keyed only on
        // IP because there is no victim email yet — we are protecting the
        // sign-up form from bulk abuse (account-spam / email enumeration).
        $identifier = throttle_identifier_for_register();

        if (is_throttled($identifier, 10, 600)) {
            // 10 attempts / 10 min per IP for the registration form.
            record_failed_attempt($identifier);
            $error = 'Muitas tentativas de cadastro. Tente novamente em alguns minutos.';
        } else {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            // Validações
            if (empty($name) || strlen($name) < 3) {
                $error = 'Informe um nome com pelo menos 3 caracteres.';
            } elseif (!$email) {
                $error = 'Informe um email válido.';
            } elseif (strlen($password) < 8) {
                $error = 'A senha deve ter pelo menos 8 caracteres.';
            } elseif ($password !== $confirmPassword) {
                $error = 'As senhas precisam coincidir.';
            } else {
                // Verifica se email já existe
                $existingUser = findUserByEmail($email);
                if ($existingUser) {
                    // Record the failure so repeated enumeration-on-same-email
                    // is rate-limited like any other kind of spray.
                    record_failed_attempt($identifier);
                    $error = 'Este email já está cadastrado.';
                } else {
                    // Cria o usuário
                    try {
                        // Entrada slice: localStorage -> DB theme bridge.
                        // Hidden input "theme" is filled by JS from
                        // localStorage['novixfin-theme']; createUser validates
                        // it against the whitelist (fallback 'ametista').
                        $theme = (string) ($_POST['theme'] ?? '');
                        createUser($name, $email, $password, $theme);
                        // Successful registration clears the register-throttle
                        // counter so a legit user who retried after a few typos
                        // isn't nudged toward the limit.
                        clear_failed_attempts($identifier);
                        flash('success', 'Conta criada com sucesso! Faça login.');
                        redirect('login.php');
                    } catch (Throwable $e) {
                        error_log('Create user error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                        $error = 'Erro ao criar conta. Tente novamente.';
                    }
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Register error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $error = 'Erro ao processar registro. Tente novamente.';
    }
}

// Pega mensagens flash se houver
if (empty($error)) {
    $error = flash('error') ?? '';
}
$success = flash('success') ?? '';

// Entrada slice: banner flavor (throttle -> gold clock, others -> negative
// triangle; success redirects to login, so no success banner here).
$isThrottleError = $error !== '' && strpos($error, 'Muitas tentativas') === 0;

require_once __DIR__ . '/../includes/layout/entrada.php';

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novix Fin | Criar conta</title>
    <?php novix_entrada_head(); ?>
</head>
<body>
    <div id="lg-shell">
        <?php novix_entrada_brand(
            'Comece a lapidar o seu mês.',
            'Do bruto ao patrimônio, um lançamento de cada vez.'
        ); ?>

        <div id="lg-formcol">
            <span aria-hidden="true" class="lg-glow-a"></span>
            <span aria-hidden="true" class="lg-glow-b"></span>

            <form method="POST" action="" id="register-form" class="lg-form lg-form--registro">
                <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="theme" id="register-theme" value="">
                <div>
                    <div class="lg-title">Criar conta</div>
                    <div class="lg-sub">Leva um minuto. Seus números, só seus.</div>
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
                                <div class="lg-banner-body">O bloqueio é automático após 10 tentativas em 10 minutos e expira sozinho.</div>
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
                    <span class="lg-label">Nome</span>
                    <input type="text" id="name" name="name" value="<?php echo sanitize($name); ?>" required minlength="3" placeholder="Como podemos te chamar" autocomplete="name" class="lg-input">
                </label>
                <label class="lg-field">
                    <span class="lg-label">Email</span>
                    <input type="email" id="email" name="email" value="<?php echo sanitize($email); ?>" required placeholder="voce@email.com" autocomplete="email" class="lg-input">
                </label>
                <label class="lg-field">
                    <span class="lg-label">Senha</span>
                    <input type="password" id="password" name="password" required minlength="8" placeholder="Mínimo 8 caracteres" autocomplete="new-password" class="lg-input">
                </label>
                <label class="lg-field">
                    <span class="lg-label">Confirmar senha</span>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Repita a senha" autocomplete="new-password" class="lg-input">
                </label>
                <button type="submit" class="lg-btn">Criar conta</button>
                <?php if ($isThrottleError) : ?>
                    <div class="lg-throttle-note">O bloqueio é automático após 10 tentativas em 10 minutos e expira sozinho.</div>
                <?php endif; ?>
                <div class="lg-switch">
                    <span class="lg-switch-q">Já tem conta?</span>
                    <a href="/public/login.php" class="lg-switch-go">Entrar</a>
                    <a href="/public/index.php" class="lg-back"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>Voltar</a>
                </div>
            </form>

            <div class="lg-theme-note">Tema herdado da sua escolha na página inicial.</div>
        </div>
    </div>
    <script>
    // localStorage -> DB bridge: send the landing theme choice with signup.
    (function () {
        var input = document.getElementById('register-theme');
        var form = document.getElementById('register-form');
        if (!input || !form) return;
        var fill = function () {
            var t = '';
            try { t = localStorage.getItem('novixfin-theme') || ''; } catch (e) {}
            input.value = t;
        };
        fill();
        form.addEventListener('submit', fill);
    })();
    </script>
    <?php novix_entrada_parallax_script(); ?>
</body>
</html>
