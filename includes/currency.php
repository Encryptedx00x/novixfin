<?php

declare(strict_types=1);

/**
 * Funções auxiliares para formatação de moedas
 */

function formatCurrency(float $amount, string $currency = 'BRL'): string
{
    $symbols = [
        'BRL' => 'R$',
        'USD' => 'US$',
        'EUR' => '€',
    ];

    $symbol = $symbols[$currency] ?? $currency;

    // v3 fix: USD/EUR use '.' decimal + ',' thousands (en-US / most-EU style);
    // BRL keeps the Brazilian ',' decimal + '.' thousands. Previous code
    // formatted every currency in the pt-BR style, which was wrong for USD/EUR.
    if ($currency === 'BRL') {
        return $symbol . ' ' . number_format($amount, 2, ',', '.');
    }

    return $symbol . ' ' . number_format($amount, 2, '.', ',');
}

function getCurrencySymbol(string $currency = 'BRL'): string
{
    $symbols = [
        'BRL' => 'R$',
        'USD' => 'US$',
        'EUR' => '€',
    ];

    return $symbols[$currency] ?? $currency;
}

/**
 * Converte um valor de uma moeda para outra usando as taxas de conversão
 */
function convertCurrency(float $amount, string $fromCurrency, string $toCurrency, array $settings): float
{
    if ($fromCurrency === $toCurrency) {
        return $amount;
    }

    $fromLower = strtolower($fromCurrency);
    $toLower = strtolower($toCurrency);
    
    // Taxas de conversão (from -> to)
    $rateKey = "conversion_rate_{$fromLower}_to_{$toLower}";
    
    if (isset($settings[$rateKey]) && $settings[$rateKey] > 0) {
        return $amount * (float) $settings[$rateKey];
    }

    // Se não houver taxa direta, tenta via BRL como intermediário
    if ($fromCurrency !== 'BRL' && $toCurrency !== 'BRL') {
        $toBRL = convertCurrency($amount, $fromCurrency, 'BRL', $settings);
        return convertCurrency($toBRL, 'BRL', $toCurrency, $settings);
    }

    // Se não conseguir converter, retorna o valor original
    return $amount;
}

