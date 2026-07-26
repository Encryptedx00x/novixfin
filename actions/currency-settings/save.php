<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/csrf.php';
requireAuth();

use Novix\Database\Connection;
use Novix\Repositories\CurrencySettingsRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('currency-settings.php');
}

verifyCsrf($_POST['_token'] ?? '');

$userId = currentUserId() ?? 0;

// Validação
$primaryCurrency = $_POST['primary_currency'] ?? 'BRL';
if (!in_array($primaryCurrency, ['BRL', 'USD', 'EUR'], true)) {
    flash('error', 'Moeda principal inválida.');
    redirect('currency-settings.php');
}

$enabledCurrencies = $_POST['enabled_currencies'] ?? [];
if (empty($enabledCurrencies)) {
    flash('error', 'Selecione pelo menos uma moeda.');
    redirect('currency-settings.php');
}

// Garante que a moeda principal está nas habilitadas
if (!in_array($primaryCurrency, $enabledCurrencies, true)) {
    $enabledCurrencies[] = $primaryCurrency;
}

// Ordem das moedas (mantém a ordem original se não especificada)
$currencyOrder = array_unique(array_merge([$primaryCurrency], $enabledCurrencies));

$convertAllToOne = isset($_POST['convert_all_to_one']) && $_POST['convert_all_to_one'] === '1';
$conversionTargetCurrency = $convertAllToOne ? ($_POST['conversion_target_currency'] ?? null) : null;

if ($convertAllToOne && empty($conversionTargetCurrency)) {
    flash('error', 'Selecione a moeda de destino para conversão.');
    redirect('currency-settings.php');
}

// Validação das taxas
$rates = [
    'conversion_rate_brl_to_usd' => filter_input(INPUT_POST, 'conversion_rate_brl_to_usd', FILTER_VALIDATE_FLOAT),
    'conversion_rate_brl_to_eur' => filter_input(INPUT_POST, 'conversion_rate_brl_to_eur', FILTER_VALIDATE_FLOAT),
    'conversion_rate_usd_to_brl' => filter_input(INPUT_POST, 'conversion_rate_usd_to_brl', FILTER_VALIDATE_FLOAT),
    'conversion_rate_usd_to_eur' => filter_input(INPUT_POST, 'conversion_rate_usd_to_eur', FILTER_VALIDATE_FLOAT),
    'conversion_rate_eur_to_brl' => filter_input(INPUT_POST, 'conversion_rate_eur_to_brl', FILTER_VALIDATE_FLOAT),
    'conversion_rate_eur_to_usd' => filter_input(INPUT_POST, 'conversion_rate_eur_to_usd', FILTER_VALIDATE_FLOAT),
];

foreach ($rates as $key => $rate) {
    if ($rate === false || $rate <= 0) {
        flash('error', 'Todas as taxas de conversão devem ser números positivos.');
        redirect('currency-settings.php');
    }
}

$pdo = Connection::get();
$currencySettingsRepository = new CurrencySettingsRepository($pdo);

$settings = [
    'primary_currency' => $primaryCurrency,
    'enabled_currencies' => $enabledCurrencies,
    'currency_order' => $currencyOrder,
    'convert_all_to_one' => $convertAllToOne,
    'conversion_target_currency' => $conversionTargetCurrency,
] + $rates;

try {
    $currencySettingsRepository->saveSettings($userId, $settings);
} catch (Throwable $e) {
    error_log('currency-settings/save.php: ' . $e->getMessage());
    flash('error', 'Não foi possível salvar as configurações. Tente novamente.');
    redirect('currency-settings.php');
}

flash('success', 'Configurações de moedas salvas com sucesso!');
redirect('currency-settings.php');

