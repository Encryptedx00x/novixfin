<?php

declare(strict_types=1);

namespace Novix\Repositories;

use DateTimeImmutable;
use PDO;

final class TransactionRepository
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function listByUser(int $userId, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null, ?bool $isEffective = null, ?bool $isRecurring = null): array
    {
        // Verifica se effective_date existe na tabela
        $hasEffectiveDateColumn = false;
        try {
            $checkStmt = $this->connection->query("SHOW COLUMNS FROM transactions LIKE 'effective_date'");
            $hasEffectiveDateColumn = $checkStmt->rowCount() > 0;
        } catch (\PDOException $e) {
            // Ignora erro
        }
        
        $effectiveDateField = $hasEffectiveDateColumn ? ', t.effective_date' : ', NULL as effective_date';

        $hasRecurrenceTypeColumn = false;
        try {
            $checkRecurrence = $this->connection->query("SHOW COLUMNS FROM transactions LIKE 'recurrence_type'");
            $hasRecurrenceTypeColumn = $checkRecurrence->rowCount() > 0;
        } catch (\PDOException $e) {
            // Ignora erro
        }

        $recurrenceFields = $hasRecurrenceTypeColumn
            ? ', t.recurrence_type, t.recurrence_day, t.recurrence_end_date'
            : ', NULL as recurrence_type, NULL as recurrence_day, NULL as recurrence_end_date';

        $installmentFields = ', NULL as parent_transaction_id, NULL as installment_index, NULL as recurrence_total_occurrences';
        try {
            $checkInstallments = $this->connection->query("SHOW COLUMNS FROM transactions");
            $transactionColumns = array_flip($checkInstallments->fetchAll(PDO::FETCH_COLUMN));
            $installmentFields = (isset($transactionColumns['parent_transaction_id']) ? ', t.parent_transaction_id' : ', NULL as parent_transaction_id')
                . (isset($transactionColumns['installment_index']) ? ', t.installment_index' : ', NULL as installment_index')
                . (isset($transactionColumns['recurrence_total_occurrences']) ? ', t.recurrence_total_occurrences' : ', NULL as recurrence_total_occurrences');
        } catch (\PDOException $e) {
            // Ignora erro
        }
        
        // Verifica se user_id existe na tabela categories
        $hasUserIdColumn = false;
        try {
            $checkColumn = $this->connection->query("SHOW COLUMNS FROM categories LIKE 'user_id'");
            $hasUserIdColumn = $checkColumn->rowCount() > 0;
        } catch (\PDOException $e) {
            // Ignora erro
        }
        
        if ($hasUserIdColumn) {
            $categoryJoin = 'INNER JOIN categories c ON t.category_id = c.id AND c.user_id = :user_id_cat';
            $params = ['user_id' => $userId, 'user_id_cat' => $userId];
        } else {
            $categoryJoin = 'INNER JOIN categories c ON t.category_id = c.id';
            $params = ['user_id' => $userId];
        }
        
        $query = 'SELECT t.id, t.type, t.amount, t.description, t.transaction_date, t.category_id, t.currency,
            t.is_effective, t.is_recurring, t.account_id' . $effectiveDateField . $recurrenceFields . $installmentFields . ',
            c.name as category_name, c.type as category_type,
            a.name as account_name, a.icon as account_icon
            FROM transactions t
            ' . $categoryJoin . '
            LEFT JOIN accounts a ON t.account_id = a.id
            WHERE t.user_id = :user_id
            AND (t.account_id IS NULL OR a.is_active = 1)';

        if ($from) {
            $query .= ' AND t.transaction_date >= :from';
            $params['from'] = $from->format('Y-m-d');
        }

        if ($to) {
            $query .= ' AND t.transaction_date <= :to';
            $params['to'] = $to->format('Y-m-d');
        }

        if ($isEffective !== null) {
            $query .= ' AND t.is_effective = :is_effective';
            $params['is_effective'] = $isEffective ? 1 : 0;
        }

        if ($isRecurring !== null) {
            $query .= ' AND t.is_recurring = :is_recurring';
            $params['is_recurring'] = $isRecurring ? 1 : 0;
        }

        $query .= ' ORDER BY t.transaction_date DESC, t.created_at DESC';

        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function create(int $userId, int $accountId, int $categoryId, string $type, float $amount, string $description, DateTimeImmutable $date, string $currency = 'BRL', bool $isEffective = true, bool $isRecurring = false, ?int $billId = null, ?DateTimeImmutable $effectiveDate = null, ?string $recurrenceType = null, ?int $recurrenceDay = null, ?DateTimeImmutable $recurrenceEndDate = null, ?int $parentTransactionId = null, ?int $installmentIndex = null, ?int $recurrenceTotalOccurrences = null): int
    {
        // Verifica quais colunas existem na tabela
        $columns = [];
        try {
            $stmt = $this->connection->query("SHOW COLUMNS FROM transactions");
            $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $columns = array_flip($existingColumns);
        } catch (\PDOException $e) {
            error_log('Warning: Could not check columns: ' . $e->getMessage());
        }
        
        // Monta query apenas com colunas que existem
        $insertColumns = ['user_id', 'account_id', 'bill_id', 'category_id', 'type', 'amount', 'currency', 'description', 'transaction_date', 'is_effective', 'is_recurring'];
        $insertValues = [];
        $params = [
            'user_id' => $userId,
            'account_id' => $accountId,
            'bill_id' => $billId,
            'category_id' => $categoryId,
            'type' => $type,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'transaction_date' => $date->format('Y-m-d'),
            'is_effective' => $isEffective ? 1 : 0,
            'is_recurring' => $isRecurring ? 1 : 0,
        ];
        
        // Adiciona colunas opcionais se existirem
        if (isset($columns['effective_date']) && $effectiveDate) {
            $insertColumns[] = 'effective_date';
            $params['effective_date'] = $effectiveDate->format('Y-m-d');
        }
        
        if (isset($columns['recurrence_type'])) {
            $insertColumns[] = 'recurrence_type';
            $params['recurrence_type'] = $recurrenceType ?? ($isRecurring ? 'monthly' : 'none');
        }
        
        if (isset($columns['recurrence_day']) && $recurrenceDay !== null) {
            $insertColumns[] = 'recurrence_day';
            $params['recurrence_day'] = $recurrenceDay;
        }
        
        if (isset($columns['recurrence_end_date']) && $recurrenceEndDate) {
            $insertColumns[] = 'recurrence_end_date';
            $params['recurrence_end_date'] = $recurrenceEndDate->format('Y-m-d');
        }

        if (isset($columns['parent_transaction_id']) && $parentTransactionId !== null) {
            $insertColumns[] = 'parent_transaction_id';
            $params['parent_transaction_id'] = $parentTransactionId;
        }

        if (isset($columns['installment_index']) && $installmentIndex !== null) {
            $insertColumns[] = 'installment_index';
            $params['installment_index'] = $installmentIndex;
        }

        if (isset($columns['recurrence_total_occurrences']) && $recurrenceTotalOccurrences !== null) {
            $insertColumns[] = 'recurrence_total_occurrences';
            $params['recurrence_total_occurrences'] = $recurrenceTotalOccurrences;
        }
        
        // Prepara placeholders
        foreach ($insertColumns as $col) {
            $insertValues[] = ':' . $col;
        }

        $sql = 'INSERT INTO transactions (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertValues) . ')';

        // v3 Batch F: wrap INSERT + balance update + effective_at update in a
        // transaction so a mid-way failure leaves no inconsistent account
        // balance. The guard `inTransaction()` ensures that if the caller
        // (e.g. pay-bill.php) already started a transaction, we don't try to
        // nest (InnoDB/PDO has no auto savepoints) — we just contribute our
        // statements to their transaction.
        $startedHere = false;
        if (!$this->connection->inTransaction()) {
            $this->connection->beginTransaction();
            $startedHere = true;
        }

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);

            $transactionId = (int) $this->connection->lastInsertId();

            // Atualiza o saldo da conta apenas se a transação for efetivada
            if ($isEffective) {
                $multiplier = $type === 'income' ? 1 : -1;
                $stmt = $this->connection->prepare('UPDATE accounts SET balance = balance + :amount WHERE id = :account_id');
                $stmt->execute([
                    'amount' => $amount * $multiplier,
                    'account_id' => $accountId,
                ]);

                // Se efetivada, registra a data/hora de efetivação (se a coluna existir)
                try {
                    $now = new DateTimeImmutable();
                    $stmt = $this->connection->prepare('UPDATE transactions SET effective_at = :effective_at WHERE id = :id');
                    $stmt->execute([
                        'id' => $transactionId,
                        'effective_at' => $now->format('Y-m-d H:i:s'),
                    ]);
                } catch (\PDOException $e) {
                    // Coluna effective_at pode não existir ainda, ignora o erro
                    error_log('Warning: effective_at column may not exist: ' . $e->getMessage());
                }
            }

            if ($startedHere) {
                $this->connection->commit();
            }

            return $transactionId;
        } catch (\Throwable $e) {
            if ($startedHere) {
                try { $this->connection->rollBack(); } catch (\Throwable $rb) { /* ignore */ }
            }
            throw $e;
        }
    }

    public function update(int $transactionId, int $userId, int $accountId, int $categoryId, string $type, float $amount, string $description, DateTimeImmutable $date, string $currency = 'BRL', bool $isEffective = true, bool $isRecurring = false, ?DateTimeImmutable $effectiveDate = null, ?string $recurrenceType = null, ?int $recurrenceDay = null, ?DateTimeImmutable $recurrenceEndDate = null, ?int $installmentIndex = null, ?int $recurrenceTotalOccurrences = null, bool $updateInstallmentFields = true): void
    {
        // Verifica quais colunas existem na tabela
        $columns = [];
        try {
            $stmt = $this->connection->query("SHOW COLUMNS FROM transactions");
            $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $columns = array_flip($existingColumns);
        } catch (\PDOException $e) {
            error_log('Warning: Could not check columns: ' . $e->getMessage());
        }
        
        // Busca a transação atual para verificar mudanças no status de efetivado e conta
        $stmt = $this->connection->prepare('SELECT account_id, type, amount, is_effective, bill_id FROM transactions WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $transactionId, 'user_id' => $userId]);
        $oldTransaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $oldAccountId = $oldTransaction ? (int) ($oldTransaction['account_id'] ?? 0) : 0;
        $accountChanged = $oldAccountId !== $accountId;
        
        // Monta query UPDATE com effective_date se a coluna existir
        $updateFields = [
            'account_id = :account_id',
            'category_id = :category_id',
            'type = :type',
            'amount = :amount',
            'currency = :currency',
            'description = :description',
            'transaction_date = :transaction_date',
            'is_effective = :is_effective',
            'is_recurring = :is_recurring',
        ];
        
        $params = [
            'id' => $transactionId,
            'user_id' => $userId,
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'type' => $type,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'transaction_date' => $date->format('Y-m-d'),
            'is_effective' => $isEffective ? 1 : 0,
            'is_recurring' => $isRecurring ? 1 : 0,
        ];
        
        if (isset($columns['effective_date'])) {
            $updateFields[] = 'effective_date = :effective_date';
            if ($effectiveDate !== null) {
                $params['effective_date'] = $effectiveDate->format('Y-m-d');
            } else {
                $params['effective_date'] = null;
            }
        }

        if (isset($columns['recurrence_type'])) {
            $updateFields[] = 'recurrence_type = :recurrence_type';
            $params['recurrence_type'] = $recurrenceType ?? ($isRecurring ? 'monthly' : 'none');
        }

        if (isset($columns['recurrence_day'])) {
            $updateFields[] = 'recurrence_day = :recurrence_day';
            $params['recurrence_day'] = $recurrenceDay;
        }

        if (isset($columns['recurrence_end_date'])) {
            $updateFields[] = 'recurrence_end_date = :recurrence_end_date';
            $params['recurrence_end_date'] = $recurrenceEndDate !== null
                ? $recurrenceEndDate->format('Y-m-d')
                : null;
        }

        if ($updateInstallmentFields && isset($columns['installment_index'])) {
            $updateFields[] = 'installment_index = :installment_index';
            $params['installment_index'] = $installmentIndex;
        }

        if ($updateInstallmentFields && isset($columns['recurrence_total_occurrences'])) {
            $updateFields[] = 'recurrence_total_occurrences = :recurrence_total_occurrences';
            $params['recurrence_total_occurrences'] = $recurrenceTotalOccurrences;
        }
        
        $sql = 'UPDATE transactions SET ' . implode(', ', $updateFields) . ' WHERE id = :id AND user_id = :user_id';
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        
        // Ajusta os saldos das contas
        if ($oldTransaction) {
            $oldEffective = (bool) $oldTransaction['is_effective'];
            $oldAmount = (float) $oldTransaction['amount'];
            $oldType = $oldTransaction['type'];
            $oldMultiplier = $oldType === 'income' ? 1 : -1;
            $newMultiplier = $type === 'income' ? 1 : -1;
            
            // Se a conta mudou ou o status de efetivado mudou, precisa ajustar os saldos
            if ($accountChanged || $oldEffective !== $isEffective) {
                // Remove o valor da conta antiga se estava efetivado
                if ($oldEffective && $oldAccountId > 0) {
                    $stmt = $this->connection->prepare('UPDATE accounts SET balance = balance - :amount WHERE id = :account_id');
                    $stmt->execute([
                        'amount' => $oldAmount * $oldMultiplier,
                        'account_id' => $oldAccountId,
                    ]);
                }
                
                // Adiciona o valor na conta nova (ou mesma conta se não mudou) se está efetivado
                if ($isEffective && $accountId > 0) {
                    $stmt = $this->connection->prepare('UPDATE accounts SET balance = balance + :amount WHERE id = :account_id');
                    $stmt->execute([
                        'amount' => $amount * $newMultiplier,
                        'account_id' => $accountId,
                    ]);
                }
            } elseif ($oldEffective && $isEffective && !$accountChanged) {
                // Se apenas o valor ou tipo mudou (mas conta e efetivado não mudaram)
                // Remove o valor antigo e adiciona o novo
                if ($oldAccountId > 0) {
                    // Remove valor antigo
                    $stmt = $this->connection->prepare('UPDATE accounts SET balance = balance - :amount WHERE id = :account_id');
                    $stmt->execute([
                        'amount' => $oldAmount * $oldMultiplier,
                        'account_id' => $oldAccountId,
                    ]);
                    
                    // Adiciona valor novo
                    $stmt = $this->connection->prepare('UPDATE accounts SET balance = balance + :amount WHERE id = :account_id');
                    $stmt->execute([
                        'amount' => $amount * $newMultiplier,
                        'account_id' => $oldAccountId,
                    ]);
                }
            }
        }
    }

    public function delete(int $transactionId, int $userId): void
    {
        // Busca a transação antes de deletar para atualizar a fatura
        $stmt = $this->connection->prepare('SELECT account_id, type, amount, is_effective, bill_id FROM transactions WHERE id = :id AND user_id = :user_id');
        $stmt->execute([
            'id' => $transactionId,
            'user_id' => $userId,
        ]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            return;
        }

        // v3 validation fix: wrap DELETE + account-balance revert + bill-total
        // refresh in a transaction so a mid-way PDO failure rolls back the
        // DELETE (no orphaned balance state where the row is gone but the
        // account still carries the tx's effect).
        $startedHere = false;
        if (!$this->connection->inTransaction()) {
            $this->connection->beginTransaction();
            $startedHere = true;
        }

        try {
            // Deleta a transação
            $stmt = $this->connection->prepare('DELETE FROM transactions WHERE id = :id AND user_id = :user_id');
            $stmt->execute([
                'id' => $transactionId,
                'user_id' => $userId,
            ]);

            // Atualiza o saldo da conta se a transação estava efetivada
            if (($transaction['is_effective'] ?? 0) == 1 && $transaction['account_id']) {
                $multiplier = $transaction['type'] === 'income' ? -1 : 1; // Inverte porque está removendo
                $stmt = $this->connection->prepare('UPDATE accounts SET balance = balance + :amount WHERE id = :account_id');
                $stmt->execute([
                    'amount' => (float) $transaction['amount'] * $multiplier,
                    'account_id' => $transaction['account_id'],
                ]);
            }

            // Atualiza a fatura se a transação estava vinculada a uma
            if ($transaction['bill_id']) {
                $billRepository = new \Novix\Repositories\CreditCardBillRepository($this->connection);
                $billRepository->updateBillTotal((int) $transaction['bill_id']);
            }

            if ($startedHere) {
                $this->connection->commit();
            }
        } catch (\Throwable $e) {
            if ($startedHere) {
                try { $this->connection->rollBack(); } catch (\Throwable $rb) { /* ignore */ }
            }
            throw $e;
        }
    }

    public function deleteByAccount(int $accountId, int $userId): int
    {
        // v3 validation fix: pre-fetch EVERY effective transaction we are
        // about to bulk-delete so we can revert account balances atomically.
        // Pre-v3 deleted rows and left `accounts.balance` inflated/deflated
        // by the deleted amount → silent data inconsistency.
        $fetch = $this->connection->prepare(
            'SELECT id, type, amount FROM transactions
              WHERE account_id = :account_id AND user_id = :user_id AND is_effective = 1'
        );
        $fetch->execute([':account_id' => $accountId, ':user_id' => $userId]);
        $effectiveRows = $fetch->fetchAll(PDO::FETCH_ASSOC);

        $startedHere = false;
        if (!$this->connection->inTransaction()) {
            $this->connection->beginTransaction();
            $startedHere = true;
        }

        try {
            // Revert the account balance by the sum of every effective row.
            // incomeType delta: income → +amount, expense → -amount. We're
            // REVERSING, so flip the sign.
            $incomeSum  = 0.0;
            $expenseSum = 0.0;
            foreach ($effectiveRows as $row) {
                $amount = (float) $row['amount'];
                if ($row['type'] === 'income') {
                    $incomeSum += $amount;
                } else {
                    $expenseSum += $amount;
                }
            }
            // Reverse: subtract what was added, add back what was subtracted.
            $reversal = -1.0 * $incomeSum + 1.0 * $expenseSum;
            if ($reversal != 0.0) {
                $stmt = $this->connection->prepare('UPDATE accounts SET balance = balance + :delta WHERE id = :account_id');
                $stmt->execute([':delta' => $reversal, ':account_id' => $accountId]);
            }

            // Collect linked bills so we can refresh their totals afterwards.
            $billFetch = $this->connection->prepare(
                'SELECT DISTINCT bill_id FROM transactions
                  WHERE account_id = :account_id AND user_id = :user_id AND bill_id IS NOT NULL'
            );
            $billFetch->execute([':account_id' => $accountId, ':user_id' => $userId]);
            $billIds = $billFetch->fetchAll(PDO::FETCH_COLUMN);

            $stmt = $this->connection->prepare('DELETE FROM transactions WHERE account_id = :account_id AND user_id = :user_id');
            $stmt->execute([':account_id' => $accountId, ':user_id' => $userId]);
            $deleted = (int) $stmt->rowCount();

            // Refresh any linked credit-card bills so totals reflect the deletes.
            if (!empty($billIds)) {
                $billRepository = new \Novix\Repositories\CreditCardBillRepository($this->connection);
                foreach ($billIds as $billId) {
                    if ($billId) {
                        $billRepository->updateBillTotal((int) $billId);
                    }
                }
            }

            if ($startedHere) {
                $this->connection->commit();
            }
            return $deleted;
        } catch (\Throwable $e) {
            if ($startedHere) {
                try { $this->connection->rollBack(); } catch (\Throwable $rb) { /* ignore */ }
            }
            throw $e;
        }
    }

    public function deleteAll(int $userId): int
    {
        $startedHere = false;
        if (!$this->connection->inTransaction()) {
            $this->connection->beginTransaction();
            $startedHere = true;
        }

        try {
            $stmt = $this->connection->prepare('
                SELECT account_id, type, amount
                FROM transactions
                WHERE user_id = :user_id AND is_effective = 1 AND account_id IS NOT NULL
            ');
            $stmt->execute(['user_id' => $userId]);
            $effectiveRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->connection->prepare('
                SELECT DISTINCT bill_id
                FROM transactions
                WHERE user_id = :user_id AND bill_id IS NOT NULL
            ');
            $stmt->execute(['user_id' => $userId]);
            $affectedBills = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $hasRecurringGenerated = false;
            try {
                $check = $this->connection->query("SHOW TABLES LIKE 'recurring_generated'");
                $hasRecurringGenerated = (bool) $check->fetchColumn();
            } catch (\Throwable $e) {
                $hasRecurringGenerated = false;
            }

            if ($hasRecurringGenerated) {
                $stmt = $this->connection->prepare('
                    DELETE rg
                    FROM recurring_generated rg
                    INNER JOIN transactions t
                        ON t.user_id = :user_id
                       AND (rg.parent_transaction_id = t.id OR rg.child_transaction_id = t.id)
                ');
                $stmt->execute(['user_id' => $userId]);
            }

            $stmt = $this->connection->prepare('DELETE FROM transactions WHERE user_id = :user_id');
            $stmt->execute(['user_id' => $userId]);
            $deletedCount = (int) $stmt->rowCount();

            $reversals = [];
            foreach ($effectiveRows as $row) {
                $accountId = (int) ($row['account_id'] ?? 0);
                if ($accountId <= 0) {
                    continue;
                }
                if (!isset($reversals[$accountId])) {
                    $reversals[$accountId] = 0.0;
                }
                $amount = (float) $row['amount'];
                $reversals[$accountId] += $row['type'] === 'income' ? -1.0 * $amount : $amount;
            }

            if (!empty($reversals)) {
                $stmt = $this->connection->prepare('UPDATE accounts SET balance = balance + :delta WHERE id = :account_id AND user_id = :user_id');
                foreach ($reversals as $accountId => $delta) {
                    if ($delta == 0.0) {
                        continue;
                    }
                    $stmt->execute([
                        'delta' => $delta,
                        'account_id' => $accountId,
                        'user_id' => $userId,
                    ]);
                }
            }

            if (!empty($affectedBills)) {
                $billRepository = new \Novix\Repositories\CreditCardBillRepository($this->connection);
                foreach ($affectedBills as $billId) {
                    if ($billId) {
                        $billRepository->updateBillTotal((int) $billId);
                    }
                }
            }

            if ($startedHere) {
                $this->connection->commit();
            }

            return $deletedCount;
        } catch (\Throwable $e) {
            if ($startedHere) {
                try { $this->connection->rollBack(); } catch (\Throwable $rb) { /* ignore */ }
            }
            throw $e;
        }
    }

    public function totals(int $userId, bool $onlyEffective = true): array
    {
        $query = 'SELECT
            SUM(CASE WHEN t.type = "income" THEN t.amount ELSE 0 END) AS total_income,
            SUM(CASE WHEN t.type = "expense" THEN t.amount ELSE 0 END) AS total_expense
            FROM transactions t
            LEFT JOIN accounts a ON t.account_id = a.id
            WHERE t.user_id = :user_id 
            AND (t.account_id IS NULL OR a.is_active = 1)';
        
        if ($onlyEffective) {
            $query .= ' AND t.is_effective = 1';
        }
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute(['user_id' => $userId]);

        $result = $stmt->fetch() ?: ['total_income' => 0, 'total_expense' => 0];

        $income = (float) ($result['total_income'] ?? 0);
        $expense = (float) ($result['total_expense'] ?? 0);

        return [
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
        ];
    }

    /**
     * Calcula totais agrupados por moeda
     * @param bool|null $onlyEffective null = todas, true = apenas efetivadas, false = apenas não efetivadas
     * @param int|array|null $accountIds null = todas, int = uma conta (compatibilidade), array = múltiplas contas
     */
    public function totalsByCurrency(int $userId, $accountIds = null, ?bool $onlyEffective = true): array
    {
        $query = 'SELECT 
            COALESCE(t.currency, "BRL") as currency,
            SUM(CASE WHEN t.type = "income" THEN t.amount ELSE 0 END) AS income,
            SUM(CASE WHEN t.type = "expense" THEN t.amount ELSE 0 END) AS expense
            FROM transactions t
            LEFT JOIN accounts a ON t.account_id = a.id
            WHERE t.user_id = :user_id 
            AND (t.account_id IS NULL OR a.is_active = 1)';
        
        $params = ['user_id' => $userId];
        
        if ($onlyEffective === true) {
            $query .= ' AND t.is_effective = 1';
        } elseif ($onlyEffective === false) {
            $query .= ' AND t.is_effective = 0';
        }
        // Se $onlyEffective === null, não adiciona filtro (mostra todas)
        
        // Suporta array de IDs ou ID único (compatibilidade)
        if (is_array($accountIds) && !empty($accountIds)) {
            $placeholders = [];
            foreach ($accountIds as $idx => $id) {
                $paramKey = 'account_id_' . $idx;
                $placeholders[] = ':' . $paramKey;
                $params[$paramKey] = $id;
            }
            $query .= ' AND t.account_id IN (' . implode(',', $placeholders) . ')';
        } elseif (is_int($accountIds)) {
            $query .= ' AND t.account_id = :account_id';
            $params['account_id'] = $accountIds;
        }
        
        $query .= ' GROUP BY COALESCE(t.currency, "BRL")';
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);

        $results = $stmt->fetchAll();
        
        $totals = [];
        foreach ($results as $row) {
            $currency = $row['currency'] ?? 'BRL';
            $income = (float) ($row['income'] ?? 0);
            $expense = (float) ($row['expense'] ?? 0);
            
            $totals[$currency] = [
                'income' => $income,
                'expense' => $expense,
                'balance' => $income - $expense,
            ];
        }

        // Garante que todas as moedas existam
        foreach (['BRL', 'USD', 'EUR'] as $currency) {
            if (!isset($totals[$currency])) {
                $totals[$currency] = [
                    'income' => 0.0,
                    'expense' => 0.0,
                    'balance' => 0.0,
                ];
            }
        }

        return $totals;
    }

    public function totalsByCategory(int $userId, bool $onlyEffective = true): array
    {
        $query = 'SELECT c.name, c.type, SUM(t.amount) AS total 
            FROM transactions t 
            INNER JOIN categories c ON t.category_id = c.id 
            LEFT JOIN accounts a ON t.account_id = a.id
            WHERE t.user_id = :user_id 
            AND (t.account_id IS NULL OR a.is_active = 1)';
        
        if ($onlyEffective) {
            $query .= ' AND t.is_effective = 1';
        }
        
        $query .= ' GROUP BY c.id, c.name, c.type';
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function monthlyHistory(int $userId, int $months = 6, ?bool $onlyEffective = true): array
    {
        $query = 'SELECT DATE_FORMAT(t.transaction_date, "%Y-%m") AS month, 
            SUM(CASE WHEN t.type = "income" THEN t.amount ELSE 0 END) AS income, 
            SUM(CASE WHEN t.type = "expense" THEN t.amount ELSE 0 END) AS expense 
            FROM transactions t
            LEFT JOIN accounts a ON t.account_id = a.id
            WHERE t.user_id = :user_id 
            AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
            AND (t.account_id IS NULL OR a.is_active = 1)';
        
        if ($onlyEffective === true) {
            $query .= ' AND t.is_effective = 1';
        } elseif ($onlyEffective === false) {
            $query .= ' AND t.is_effective = 0';
        }
        // Se $onlyEffective === null, não adiciona filtro (mostra todas)
        
        $query .= ' GROUP BY month 
            ORDER BY month ASC';
        
        $stmt = $this->connection->prepare($query);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('months', $months, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

