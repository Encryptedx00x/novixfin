<?php

declare(strict_types=1);

/**
 * NovixFin — Task B5: desativa `register_bill_as_expense`.
 *
 * POR QUÊ: com a nova semântica de pagamento, pagar uma fatura NÃO cria mais
 * uma despesa na conta do próprio cartão. Aquela despesa (a) deixava o saldo
 * do cartão mais negativo ao pagar e (b) voltava a somar no total da fatura,
 * porque carregava o `bill_id` dela. `actions/credit-cards/pay-bill.php` já
 * não a cria; esta migração alinha o DADO ao novo comportamento.
 *
 * EFEITO COLATERAL DOCUMENTADO: `public/dashboard.php` usa esta mesma flag para
 * decidir se inclui cartões nos gráficos de despesa por conta. Com ela em 0, os
 * cartões saem desses gráficos — o que é coerente, já que a fatura deixou de
 * virar uma despesa lançada.
 *
 * REVERSÍVEL SEM PERDA: o `up` guarda o valor anterior de cada linha em
 * `register_bill_as_expense_prev` antes de zerar; o `down` copia de volta e
 * remove a coluna de backup. Nenhuma preferência de usuário é perdida.
 *
 * IDEMPOTENTE: checa a existência da tabela e das colunas antes de agir.
 */

return [
    'up' => function (PDO $pdo, callable $log): void {
        $hasTable = $pdo->query("SHOW TABLES LIKE 'credit_card_transaction_settings'")->rowCount() > 0;
        if (!$hasTable) {
            $log('  ~ credit_card_transaction_settings ausente; nada a fazer');
            return;
        }

        $hasBackup = $pdo->query(
            "SHOW COLUMNS FROM credit_card_transaction_settings LIKE 'register_bill_as_expense_prev'"
        )->rowCount() > 0;

        if (!$hasBackup) {
            $log('  + register_bill_as_expense_prev (backup do valor anterior)');
            $pdo->exec(
                'ALTER TABLE `credit_card_transaction_settings`
                 ADD COLUMN `register_bill_as_expense_prev` TINYINT(1) NULL DEFAULT NULL'
            );
            // Só guarda o valor original na primeira vez, para reaplicar a
            // migração não sobrescrever o backup com os zeros já gravados.
            $pdo->exec(
                'UPDATE `credit_card_transaction_settings`
                    SET `register_bill_as_expense_prev` = `register_bill_as_expense`'
            );
        }

        $affected = $pdo->exec(
            'UPDATE `credit_card_transaction_settings`
                SET `register_bill_as_expense` = 0
              WHERE `register_bill_as_expense` <> 0'
        );
        $log('  ~ register_bill_as_expense zerado em ' . (int) $affected . ' linha(s)');
    },

    'down' => function (PDO $pdo, callable $log): void {
        $hasTable = $pdo->query("SHOW TABLES LIKE 'credit_card_transaction_settings'")->rowCount() > 0;
        if (!$hasTable) {
            $log('  ~ credit_card_transaction_settings ausente; nada a fazer');
            return;
        }

        $hasBackup = $pdo->query(
            "SHOW COLUMNS FROM credit_card_transaction_settings LIKE 'register_bill_as_expense_prev'"
        )->rowCount() > 0;

        if ($hasBackup) {
            $log('  ~ restaurando register_bill_as_expense do backup');
            $pdo->exec(
                'UPDATE `credit_card_transaction_settings`
                    SET `register_bill_as_expense` = COALESCE(`register_bill_as_expense_prev`, 1)'
            );
            $log('  - register_bill_as_expense_prev');
            $pdo->exec(
                'ALTER TABLE `credit_card_transaction_settings` DROP COLUMN `register_bill_as_expense_prev`'
            );
        }
    },
];
