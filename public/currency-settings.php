<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;
use Novix\Repositories\CurrencySettingsRepository;
use Novix\Repositories\UserRepository;

$pdo = Connection::get();
$currencySettingsRepository = new CurrencySettingsRepository($pdo);
$userRepository = new UserRepository($pdo);

$userId = currentUserId() ?? 0;
$user = $userRepository->findById($userId);
$settings = $currencySettingsRepository->getSettings($userId);

$success = flash('success');
$error = flash('error');

$title = 'Configurações de Moedas | NovixFin';
$pageStyles = ['styles/moeda.css'];

include __DIR__ . '/../includes/layout/header.php';

$primaryCur = $settings['primary_currency'] ?? 'BRL';
$enabledCur = $settings['enabled_currencies'] ?? [];
$convertOn = (bool) ($settings['convert_all_to_one'] ?? false);
$targetCur = $settings['conversion_target_currency'] ?? '';
$curMeta = [
    'BRL' => ['sym' => 'R$', 'name' => 'Real Brasileiro'],
    'USD' => ['sym' => 'US$', 'name' => 'Dólar Americano'],
    'EUR' => ['sym' => '€', 'name' => 'Euro'],
];
$fmtRate = static function ($v, $default) {
    return number_format((float) ($v ?? $default), 4, '.', '');
};

?>

<section class="space-y-6 moeda-screen">
    <div>
        <span style="font-family:var(--f-d); font-weight:var(--w-d); letter-spacing:var(--ls-d); font-size:22px; color:var(--ink);">Configurações de moeda</span>
        <p style="font-size:13px; color:var(--muted); margin-top:4px;">Defina a moeda principal, quais moedas aparecem e as cotações manuais usadas nos totais.</p>
    </div>

    <?php if ($success) : ?>
        <div style="padding:12px 16px; border-radius:var(--r-chip); background:color-mix(in srgb, var(--pos) 11%, transparent); border:1px solid color-mix(in srgb, var(--pos) 30%, transparent); color:var(--pos); font-size:13px;"><?php echo sanitize($success); ?></div>
    <?php endif; ?>
    <?php if ($error) : ?>
        <div style="padding:12px 16px; border-radius:var(--r-chip); background:color-mix(in srgb, var(--neg) 11%, transparent); border:1px solid color-mix(in srgb, var(--neg) 30%, transparent); color:var(--neg); font-size:13px;"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="/actions/currency-settings/save.php">
        <input type="hidden" name="_token" value="<?php echo csrfToken(); ?>">
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(380px,1fr)); gap:16px; align-items:start;">
            <!-- COLUNA ESQUERDA: moedas + converter tudo -->
            <div style="display:flex; flex-direction:column; gap:16px;">
                <div style="background:var(--surface); border:var(--card-b); box-shadow:var(--card-sh); border-radius:var(--r); padding:22px 24px; display:flex; flex-direction:column; gap:14px;">
                    <div style="font-size:16px; font-weight:800; color:var(--ink);">Moedas</div>
                    <label style="display:flex; flex-direction:column; gap:5px;">
                        <span style="font-family:var(--f-m); font-size:10px; letter-spacing:.14em; text-transform:uppercase; color:var(--muted);">Moeda principal</span>
                        <select name="primary_currency" required style="width:100%; padding:10px 12px;">
                            <?php foreach ($curMeta as $code => $m) : ?>
                                <option value="<?php echo $code; ?>" <?php echo $primaryCur === $code ? 'selected' : ''; ?>><?php echo $m['sym']; ?> <?php echo $m['name']; ?> (<?php echo $code; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <span style="font-size:11.5px; color:var(--muted);">É a moeda dos totais do dashboard e das carteiras.</span>
                    </label>
                    <div style="display:flex; flex-direction:column; gap:7px;">
                        <span style="font-family:var(--f-m); font-size:10px; letter-spacing:.14em; text-transform:uppercase; color:var(--muted);">Moedas habilitadas</span>
                        <?php foreach ($curMeta as $code => $m) :
                            $on = in_array($code, $enabledCur, true);
                            $isPrimary = $primaryCur === $code;
                        ?>
                        <label style="display:flex; align-items:center; gap:11px; padding:10px 13px; border:1px solid <?php echo $on ? 'color-mix(in srgb, var(--accent) 35%, transparent)' : 'var(--line-soft)'; ?>; border-radius:var(--r-chip); cursor:pointer; background:<?php echo $on ? 'color-mix(in srgb, var(--accent) 5%, transparent)' : 'transparent'; ?>;">
                            <input type="checkbox" name="enabled_currencies[]" value="<?php echo $code; ?>" <?php echo $on ? 'checked' : ''; ?> style="width:15px; height:15px; flex:none;">
                            <span style="font-family:var(--f-m); font-size:13px; font-weight:700; color:var(--ink); width:34px;"><?php echo $m['sym']; ?></span>
                            <span style="font-size:13px; font-weight:600; color:var(--ink);"><?php echo $m['name']; ?></span>
                            <?php if ($isPrimary) : ?><span style="margin-left:auto; font-family:var(--f-m); font-size:9.5px; letter-spacing:.12em; text-transform:uppercase; padding:2px 8px; border-radius:999px; background:color-mix(in srgb, var(--accent) 11%, transparent); color:var(--accent); flex:none;">Principal</span><?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                        <span style="font-size:11.5px; color:var(--muted);">Só as habilitadas aparecem no lançamento rápido e nos filtros.</span>
                    </div>
                </div>

                <div style="background:var(--surface); border:var(--card-b); box-shadow:var(--card-sh); border-radius:var(--r); padding:22px 24px; display:flex; flex-direction:column; gap:13px;">
                    <div style="display:flex; align-items:flex-start; gap:12px;">
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:16px; font-weight:800; color:var(--ink);">Converter tudo para uma moeda</div>
                            <div style="font-size:12px; color:var(--muted); margin-top:3px; line-height:1.55;">Consolida os valores do app na moeda-alvo usando as cotações manuais ao lado. Os lançamentos continuam guardados na moeda original.</div>
                        </div>
                        <label class="moeda-switch" title="Converter tudo">
                            <input type="checkbox" name="convert_all_to_one" value="1" id="convert-all-toggle" <?php echo $convertOn ? 'checked' : ''; ?>>
                            <span class="moeda-track"></span>
                            <span class="moeda-knob"></span>
                        </label>
                    </div>
                    <div id="conversion-target" class="<?php echo $convertOn ? '' : 'hidden'; ?>">
                        <label style="display:flex; flex-direction:column; gap:5px;">
                            <span style="font-family:var(--f-m); font-size:10px; letter-spacing:.14em; text-transform:uppercase; color:var(--muted);">Converter para</span>
                            <select name="conversion_target_currency" style="width:100%; padding:10px 12px;">
                                <option value="">Selecione...</option>
                                <?php foreach ($curMeta as $code => $m) : ?>
                                    <option value="<?php echo $code; ?>" <?php echo $targetCur === $code ? 'selected' : ''; ?>><?php echo $m['sym']; ?> <?php echo $m['name']; ?> (<?php echo $code; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </div>
            </div>

            <!-- COLUNA DIREITA: cotações manuais -->
            <div style="background:var(--surface); border:var(--card-b); box-shadow:var(--card-sh); border-radius:var(--r); padding:22px 24px; display:flex; flex-direction:column; gap:14px;">
                <div>
                    <div style="font-size:16px; font-weight:800; color:var(--ink);">Cotações manuais</div>
                    <div style="font-size:12px; color:var(--muted); margin-top:3px; line-height:1.55;">Você define as taxas &mdash; nada vem de fora. Ex.: se 1 dólar vale R$ 5,61, informe 5,61 em USD &rarr; BRL.</div>
                </div>
                <?php
                $curPairs = [
                    ['a' => 'BRL', 'b' => 'USD', 'ab' => 'conversion_rate_brl_to_usd', 'abD' => 0.18, 'ba' => 'conversion_rate_usd_to_brl', 'baD' => 5.61],
                    ['a' => 'BRL', 'b' => 'EUR', 'ab' => 'conversion_rate_brl_to_eur', 'abD' => 0.18, 'ba' => 'conversion_rate_eur_to_brl', 'baD' => 5.61],
                    ['a' => 'USD', 'b' => 'EUR', 'ab' => 'conversion_rate_usd_to_eur', 'abD' => 0.85, 'ba' => 'conversion_rate_eur_to_usd', 'baD' => 1.18],
                ];
                foreach ($curPairs as $cp) :
                ?>
                <div style="border:1px solid var(--line-soft); border-radius:var(--r-chip); padding:13px 15px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-family:var(--f-m); font-size:12px; font-weight:800; color:var(--ink);"><?php echo $cp['a']; ?> &#8646; <?php echo $cp['b']; ?></span>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:10px;">
                        <label style="display:flex; flex-direction:column; gap:4px;">
                            <span style="font-family:var(--f-m); font-size:10px; letter-spacing:.1em; color:var(--muted);">1 <?php echo $cp['a']; ?> =</span>
                            <span style="display:flex; align-items:baseline; gap:6px; border:1px solid var(--line); border-radius:var(--r-chip); background:var(--bg); padding:0 11px;"><input type="number" step="0.0001" name="<?php echo $cp['ab']; ?>" value="<?php echo $fmtRate($settings[$cp['ab']] ?? null, $cp['abD']); ?>" inputmode="decimal" style="width:100%; padding:9px 0; border:none; background:none; outline:none; font-family:var(--f-m); font-size:13.5px; font-weight:700; color:var(--ink);"><span style="font-family:var(--f-m); font-size:11.5px; color:var(--muted); flex:none;"><?php echo $cp['b']; ?></span></span>
                        </label>
                        <label style="display:flex; flex-direction:column; gap:4px;">
                            <span style="font-family:var(--f-m); font-size:10px; letter-spacing:.1em; color:var(--muted);">1 <?php echo $cp['b']; ?> =</span>
                            <span style="display:flex; align-items:baseline; gap:6px; border:1px solid var(--line); border-radius:var(--r-chip); background:var(--bg); padding:0 11px;"><input type="number" step="0.0001" name="<?php echo $cp['ba']; ?>" value="<?php echo $fmtRate($settings[$cp['ba']] ?? null, $cp['baD']); ?>" inputmode="decimal" style="width:100%; padding:9px 0; border:none; background:none; outline:none; font-family:var(--f-m); font-size:13.5px; font-weight:700; color:var(--ink);"><span style="font-family:var(--f-m); font-size:11.5px; color:var(--muted); flex:none;"><?php echo $cp['a']; ?></span></span>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
                <button type="submit" style="width:100%; padding:13px; background:var(--accent); color:var(--accent-ink); font-weight:700; font-size:14.5px; clip-path:var(--cut); border-radius:var(--r-btn); border:none; cursor:pointer;">Salvar configurações</button>
                <a href="/public/profile.php" style="text-align:center; font-size:12.5px; color:var(--muted); text-decoration:none;">Cancelar</a>
            </div>
        </div>
    </form>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const convertToggle = document.getElementById('convert-all-toggle');
    const conversionTarget = document.getElementById('conversion-target');
    
    if (convertToggle && conversionTarget) {
        convertToggle.addEventListener('change', function() {
            if (this.checked) {
                conversionTarget.classList.remove('hidden');
            } else {
                conversionTarget.classList.add('hidden');
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>

