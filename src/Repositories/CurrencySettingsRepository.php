<?php

declare(strict_types=1);

namespace Novix\Repositories;

use PDO;

final class CurrencySettingsRepository
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function getSettings(int $userId): array
    {
        $stmt = $this->connection->prepare('SELECT * FROM user_currency_settings WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $settings = $stmt->fetch();

        if (!$settings) {
            // Retorna configurações padrão
            return [
                'primary_currency' => 'BRL',
                'enabled_currencies' => ['BRL', 'USD', 'EUR'],
                'currency_order' => ['BRL', 'USD', 'EUR'],
                'convert_all_to_one' => false,
                'conversion_target_currency' => null,
                'conversion_rate_brl_to_usd' => 0.18,
                'conversion_rate_brl_to_eur' => 0.18,
                'conversion_rate_usd_to_brl' => 5.61,
                'conversion_rate_usd_to_eur' => 0.85,
                'conversion_rate_eur_to_brl' => 5.61,
                'conversion_rate_eur_to_usd' => 1.18,
            ];
        }

        // Decodifica JSON
        $settings['enabled_currencies'] = json_decode($settings['enabled_currencies'] ?? '["BRL", "USD", "EUR"]', true);
        $settings['currency_order'] = json_decode($settings['currency_order'] ?? '["BRL", "USD", "EUR"]', true);

        return $settings;
    }

    public function saveSettings(int $userId, array $settings): void
    {
        $enabledJson = json_encode($settings['enabled_currencies'] ?? ['BRL', 'USD', 'EUR'], JSON_THROW_ON_ERROR);
        $orderJson = json_encode($settings['currency_order'] ?? ['BRL', 'USD', 'EUR'], JSON_THROW_ON_ERROR);

        // Production Hostinger runs MariaDB 11.x — `INSERT … AS alias` (MySQL 8.0.19+)
        // is invalid there (SQLSTATE 42000 / errno 1064). VALUES(col) ODKU works on MariaDB.
        $stmt = $this->connection->prepare('
            INSERT INTO user_currency_settings
            (user_id, primary_currency, enabled_currencies, currency_order, convert_all_to_one,
             conversion_target_currency, conversion_rate_brl_to_usd, conversion_rate_brl_to_eur,
             conversion_rate_usd_to_brl, conversion_rate_usd_to_eur, conversion_rate_eur_to_brl, conversion_rate_eur_to_usd)
            VALUES
            (:user_id, :primary_currency, :enabled_currencies, :currency_order, :convert_all_to_one,
             :conversion_target_currency, :conversion_rate_brl_to_usd, :conversion_rate_brl_to_eur,
             :conversion_rate_usd_to_brl, :conversion_rate_usd_to_eur, :conversion_rate_eur_to_brl, :conversion_rate_eur_to_usd)
            ON DUPLICATE KEY UPDATE
            primary_currency = VALUES(primary_currency),
            enabled_currencies = VALUES(enabled_currencies),
            currency_order = VALUES(currency_order),
            convert_all_to_one = VALUES(convert_all_to_one),
            conversion_target_currency = VALUES(conversion_target_currency),
            conversion_rate_brl_to_usd = VALUES(conversion_rate_brl_to_usd),
            conversion_rate_brl_to_eur = VALUES(conversion_rate_brl_to_eur),
            conversion_rate_usd_to_brl = VALUES(conversion_rate_usd_to_brl),
            conversion_rate_usd_to_eur = VALUES(conversion_rate_usd_to_eur),
            conversion_rate_eur_to_brl = VALUES(conversion_rate_eur_to_brl),
            conversion_rate_eur_to_usd = VALUES(conversion_rate_eur_to_usd),
            updated_at = CURRENT_TIMESTAMP
        ');

        $stmt->execute([
            'user_id' => $userId,
            'primary_currency' => $settings['primary_currency'] ?? 'BRL',
            'enabled_currencies' => $enabledJson,
            'currency_order' => $orderJson,
            'convert_all_to_one' => ($settings['convert_all_to_one'] ?? false) ? 1 : 0,
            'conversion_target_currency' => $settings['conversion_target_currency'] ?? null,
            'conversion_rate_brl_to_usd' => $settings['conversion_rate_brl_to_usd'] ?? 0.18,
            'conversion_rate_brl_to_eur' => $settings['conversion_rate_brl_to_eur'] ?? 0.18,
            'conversion_rate_usd_to_brl' => $settings['conversion_rate_usd_to_brl'] ?? 5.61,
            'conversion_rate_usd_to_eur' => $settings['conversion_rate_usd_to_eur'] ?? 0.85,
            'conversion_rate_eur_to_brl' => $settings['conversion_rate_eur_to_brl'] ?? 5.61,
            'conversion_rate_eur_to_usd' => $settings['conversion_rate_eur_to_usd'] ?? 1.18,
        ]);
    }
}

