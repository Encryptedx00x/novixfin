<?php

declare(strict_types=1);

namespace Novix\Repositories;

use DateTimeImmutable;
use PDO;

final class CreditCardBillRepository
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Busca ou cria uma fatura para o mês específico
     */
    public function findOrCreateBill(int $accountId, int $userId, string $billingMonth): array
    {
        $stmt = $this->connection->prepare('
            SELECT * FROM credit_card_bills 
            WHERE account_id = :account_id AND billing_month = :billing_month
        ');
        $stmt->execute([
            'account_id' => $accountId,
            'billing_month' => $billingMonth,
        ]);
        
        $bill = $stmt->fetch();
        
        if ($bill) {
            return $bill;
        }
        
        // Busca dados do cartão para criar a fatura
        $stmt = $this->connection->prepare('
            SELECT closing_day, due_day, interest_rate 
            FROM accounts 
            WHERE id = :account_id AND user_id = :user_id AND type = "credit"
        ');
        $stmt->execute([
            'account_id' => $accountId,
            'user_id' => $userId,
        ]);
        $account = $stmt->fetch();
        
        if (!$account) {
            throw new \RuntimeException('Cartão de crédito não encontrado.');
        }
        
        // Calcula datas de fechamento e vencimento
        $yearMonth = explode('-', $billingMonth);
        $year = (int) $yearMonth[0];
        $month = (int) $yearMonth[1];
        
        $closingDay = $account['closing_day'] ?? 10;
        $dueDay = $account['due_day'] ?? 20;
        
        // Data de fechamento: dia X do mês atual
        $closingDate = new DateTimeImmutable(sprintf('%d-%02d-%02d', $year, $month, min($closingDay, 28)));
        
        // Data de vencimento: 
        // Se o dia de vencimento é MAIOR que o dia de fechamento, vence no MESMO mês
        // Se o dia de vencimento é MENOR ou IGUAL ao dia de fechamento, vence no mês SEGUINTE
        if ($dueDay > $closingDay) {
            // Vencimento no mesmo mês (ex: fecha dia 14, vence dia 20)
            $dueDate = new DateTimeImmutable(sprintf('%d-%02d-%02d', $year, $month, min($dueDay, 28)));
        } else {
            // Vencimento no mês seguinte (ex: fecha dia 24, vence dia 1)
            $nextMonth = $month === 12 ? 1 : $month + 1;
            $nextYear = $month === 12 ? $year + 1 : $year;
            $dueDate = new DateTimeImmutable(sprintf('%d-%02d-%02d', $nextYear, $nextMonth, min($dueDay, 28)));
        }
        
        // Cria a fatura
        $stmt = $this->connection->prepare('
            INSERT INTO credit_card_bills 
            (account_id, user_id, billing_month, closing_date, due_date, currency, status)
            VALUES (:account_id, :user_id, :billing_month, :closing_date, :due_date, :currency, "open")
        ');
        $stmt->execute([
            'account_id' => $accountId,
            'user_id' => $userId,
            'billing_month' => $billingMonth,
            'closing_date' => $closingDate->format('Y-m-d'),
            'due_date' => $dueDate->format('Y-m-d'),
            'currency' => 'BRL',
        ]);
        
        $billId = (int) $this->connection->lastInsertId();
        
        // Recarrega a fatura criada
        $stmt = $this->connection->prepare('SELECT * FROM credit_card_bills WHERE id = :id');
        $stmt->execute(['id' => $billId]);
        
        return $stmt->fetch();
    }

    /**
     * Busca faturas de um cartão
     */
    public function listByAccount(int $accountId, int $userId, int $limit = 12): array
    {
        $stmt = $this->connection->prepare('
            SELECT * FROM credit_card_bills 
            WHERE account_id = :account_id AND user_id = :user_id
            ORDER BY billing_month DESC
            LIMIT :limit
        ');
        $stmt->bindValue('account_id', $accountId, PDO::PARAM_INT);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Atualiza o total de uma fatura baseado nas transações vinculadas
     */
    public function updateBillTotal(int $billId): void
    {
        $stmt = $this->connection->prepare('
            SELECT COALESCE(SUM(amount), 0) as total
            FROM transactions
            WHERE bill_id = :bill_id AND type = "expense"
        ');
        $stmt->execute(['bill_id' => $billId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = (float) ($result['total'] ?? 0);
        
        // Busca informações atuais da fatura
        $stmt = $this->connection->prepare('SELECT * FROM credit_card_bills WHERE id = :id');
        $stmt->execute(['id' => $billId]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bill) {
            return;
        }
        
        $totalAmount = (float) ($bill['total_amount'] ?? 0);
        $paidAmount = (float) ($bill['paid_amount'] ?? 0);
        $interestAmount = (float) ($bill['interest_amount'] ?? 0);
        $fineAmount = (float) ($bill['fine_amount'] ?? 0);
        $currentStatus = $bill['status'] ?? 'open';
        
        // Calcula o total com juros e multas
        $totalWithInterest = $total + $interestAmount + $fineAmount;
        
        // Atualiza o total e recalcula o status
        $newStatus = $currentStatus;
        
        if ($totalWithInterest <= 0 && $paidAmount <= 0) {
            // Se não tem nada para pagar e nada foi pago, mantém como aberta (ou cancela se preferir)
            // Mas se estava paga, volta para aberta
            if ($currentStatus === 'paid') {
                $newStatus = 'open';
            }
            // Se estava cancelada, mantém cancelada
            // Caso contrário, mantém o status atual (geralmente 'open')
        } elseif ($totalWithInterest <= 0 && $paidAmount > 0) {
            // Se não tem nada para pagar mas algo foi pago, marca como paga
            if ($currentStatus !== 'cancelled') {
                $newStatus = 'paid';
            }
        } elseif ($paidAmount >= $totalWithInterest && $totalWithInterest > 0) {
            // Se já foi pago tudo e há valor para pagar, marca como paga
            if ($currentStatus !== 'cancelled') {
                $newStatus = 'paid';
            }
        } elseif ($paidAmount > 0 && $currentStatus === 'paid' && $totalWithInterest > $paidAmount) {
            // Se estava paga mas agora tem mais valor além do que foi pago, volta para open
            $newStatus = 'open';
        } elseif ($currentStatus === 'paid' && $totalWithInterest > $paidAmount) {
            // Se estava paga mas o total aumentou além do que foi pago, volta para open
            $newStatus = 'open';
        } elseif ($totalWithInterest > 0 && $paidAmount < $totalWithInterest) {
            // Se há valor para pagar e ainda não foi pago tudo, verifica se está vencida
            $dueDate = new \DateTimeImmutable($bill['due_date']);
            $now = new \DateTimeImmutable();
            if ($now > $dueDate && $currentStatus !== 'cancelled') {
                $newStatus = 'overdue';
            } elseif ($currentStatus === 'overdue' && $now <= $dueDate) {
                $newStatus = 'open';
            } elseif ($currentStatus === 'paid') {
                // Se estava paga mas agora não está mais, volta para open
                $newStatus = 'open';
            }
        }
        
        $stmt = $this->connection->prepare('
            UPDATE credit_card_bills 
            SET total_amount = :total_amount,
                status = :status
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $billId,
            'total_amount' => $total,
            'status' => $newStatus,
        ]);
    }

    /**
     * Calcula juros para faturas vencidas
     */
    public function calculateInterest(int $billId): float
    {
        $stmt = $this->connection->prepare('
            SELECT b.*, a.interest_rate
            FROM credit_card_bills b
            INNER JOIN accounts a ON b.account_id = a.id
            WHERE b.id = :id
        ');
        $stmt->execute(['id' => $billId]);
        $bill = $stmt->fetch();
        
        if (!$bill) {
            return 0.0;
        }
        
        $status = $bill['status'] ?? 'open';
        $dueDate = new DateTimeImmutable($bill['due_date']);
        $now = new DateTimeImmutable();
        
        // Só calcula juros se a fatura estiver vencida e não paga
        if ($status === 'paid' || $status === 'cancelled') {
            return 0.0;
        }
        
        if ($now <= $dueDate) {
            // Atualiza status para open se não estiver vencida
            if ($status === 'overdue') {
                $stmt = $this->connection->prepare('UPDATE credit_card_bills SET status = "open" WHERE id = :id');
                $stmt->execute(['id' => $billId]);
            }
            return 0.0;
        }
        
        // Atualiza status para overdue
        if ($status !== 'overdue') {
            $stmt = $this->connection->prepare('UPDATE credit_card_bills SET status = "overdue" WHERE id = :id');
            $stmt->execute(['id' => $billId]);
        }
        
        $interestRate = (float) ($bill['interest_rate'] ?? 0.0);
        if ($interestRate <= 0) {
            return 0.0;
        }
        
        $totalAmount = (float) ($bill['total_amount'] ?? 0.0);
        $paidAmount = (float) ($bill['paid_amount'] ?? 0.0);
        $balance = max(0, $totalAmount - $paidAmount);
        
        if ($balance <= 0) {
            return 0.0;
        }
        
        // Calcula dias de atraso
        $daysLate = $dueDate->diff($now)->days;
        
        // Juros simples: (valor * taxa / 100) * (dias / 30)
        $monthlyInterest = ($balance * $interestRate / 100);
        $interestAmount = $monthlyInterest * ($daysLate / 30.0);
        
        // Atualiza juros na fatura
        $stmt = $this->connection->prepare('
            UPDATE credit_card_bills 
            SET interest_amount = :interest_amount
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $billId,
            'interest_amount' => $interestAmount,
        ]);
        
        return $interestAmount;
    }

    /**
     * Marca fatura como paga
     */
    public function markAsPaid(int $billId, float $paidAmount, ?DateTimeImmutable $paidAt = null): void
    {
        $paidAt = $paidAt ?? new DateTimeImmutable();
        
        // Busca informações atuais da fatura
        $stmt = $this->connection->prepare('SELECT * FROM credit_card_bills WHERE id = :id');
        $stmt->execute(['id' => $billId]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bill) {
            return;
        }
        
        // Garante que o total está atualizado
        $this->updateBillTotal($billId);
        
        // Recarrega a fatura com o total atualizado
        $stmt = $this->connection->prepare('SELECT * FROM credit_card_bills WHERE id = :id');
        $stmt->execute(['id' => $billId]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalAmount = (float) ($bill['total_amount'] ?? 0);
        $interestAmount = (float) ($bill['interest_amount'] ?? 0);
        $fineAmount = (float) ($bill['fine_amount'] ?? 0);
        $totalWithInterest = $totalAmount + $interestAmount + $fineAmount;
        
        // Determina o novo status
        $newStatus = $bill['status'] ?? 'open';
        if ($paidAmount >= $totalWithInterest) {
            $newStatus = 'paid';
        } elseif ($paidAmount > 0 && $totalWithInterest > $paidAmount) {
            // Se tem pagamento parcial mas ainda falta pagar, mantém como open (ou overdue se estiver vencida)
            if ($newStatus !== 'overdue') {
                $newStatus = 'open';
            }
        }
        
        $stmt = $this->connection->prepare('
            UPDATE credit_card_bills 
            SET paid_amount = :paid_amount,
                status = :status,
                paid_at = :paid_at
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $billId,
            'paid_amount' => $paidAmount,
            'status' => $newStatus,
            'paid_at' => $paidAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Busca fatura atual (mês corrente)
     */
    public function getCurrentBill(int $accountId, int $userId): ?array
    {
        $currentMonth = date('Y-m');
        
        try {
            return $this->findOrCreateBill($accountId, $userId, $currentMonth);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Busca todas as faturas vencidas de um usuário
     */
    public function getOverdueBills(int $userId): array
    {
        $stmt = $this->connection->prepare('
            SELECT b.*, a.name as account_name, a.icon as account_icon
            FROM credit_card_bills b
            INNER JOIN accounts a ON b.account_id = a.id
            WHERE b.user_id = :user_id 
            AND b.status = "overdue"
            AND b.due_date < CURDATE()
            ORDER BY b.due_date ASC
        ');
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchAll();
    }

    /**
     * Atualiza todas as faturas de um usuário
     * Útil para corrigir faturas após exclusão em massa de transações
     */
    public function updateAllBillsForUser(int $userId): int
    {
        $stmt = $this->connection->prepare('
            SELECT id FROM credit_card_bills 
            WHERE user_id = :user_id
        ');
        $stmt->execute(['user_id' => $userId]);
        $bills = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $updatedCount = 0;
        foreach ($bills as $billId) {
            $this->updateBillTotal((int) $billId);
            $updatedCount++;
        }
        
        return $updatedCount;
    }
}

