<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Cheque extends Model
{
    protected string $table = 'cheques';

    public function create(array $data): int
    {
        return $this->db()->insert('cheques', $data);
    }

    public function getById(int $id): ?array
    {
        return $this->db()->first(
            'SELECT c.*, p.customer_id, cust.name AS customer_name, p.amount AS payment_amount
             FROM cheques c
             JOIN payments p ON c.payment_id = p.id
             JOIN customers cust ON p.customer_id = cust.id
             WHERE c.id = ?',
            [$id]
        );
    }

    public function byStatus(string $status, int $limit = 50): array
    {
        $limit = Database::limit($limit);

        return $this->db()->all(
            "SELECT c.*, p.customer_id, cust.name AS customer_name, u.name AS status_updated_by_name
             FROM cheques c
             JOIN payments p ON c.payment_id = p.id
             JOIN customers cust ON p.customer_id = cust.id
             LEFT JOIN users u ON c.status_updated_by = u.id
             WHERE c.status = ?
             ORDER BY COALESCE(c.deposit_date, c.cheque_date) ASC
             LIMIT {$limit}",
            [$status]
        );
    }

    public function pending(): array
    {
        return $this->byStatus('pending', 100);
    }

    public function updateStatus(int $id, string $status, string $reason = null, int $updatedBy = null): void
    {
        $data = [
            'status'            => $status,
            'bounce_reason'     => $reason,
            'status_updated_at' => date('Y-m-d H:i:s'),
            'status_updated_by' => $updatedBy,
        ];

        // Banking it is what turns a promise into money, so stamp the moment.
        if ($status === 'cleared') {
            $data['deposited_at'] = date('Y-m-d H:i:s');
        }

        $this->db()->update('cheques', $data, ['id' => $id]);
    }

    /** Plan (or record) when the cheque goes to the bank. */
    public function setDeposit(int $id, ?string $depositDate): void
    {
        $this->db()->update('cheques', ['deposit_date' => $depositDate ?: null], ['id' => $id]);
    }

    public function attachImage(int $id, string $path, ?string $thumbPath): void
    {
        $this->db()->update('cheques', [
            'image_path' => $path,
            'thumb_path' => $thumbPath,
        ], ['id' => $id]);
    }

    public function countByStatus(): array
    {
        return $this->db()->all(
            'SELECT status, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total
               FROM cheques GROUP BY status'
        );
    }

    /**
     * Pending cheques coming due within $days — the reminder the owner asked
     * for, so a cheque never sits in the drawer past its date.
     *
     * Due date is the deposit date when one is planned, otherwise the date
     * written on the cheque. Anything already past due is included, because a
     * missed cheque is more urgent than an upcoming one, not less.
     */
    public function dueSoon(int $days = 7, int $limit = 50): array
    {
        $days  = max(0, $days);
        $limit = Database::limit($limit, 200, 50);

        return $this->db()->all(
            "SELECT c.*, p.customer_id, cust.name AS customer_name, cust.phone,
                    COALESCE(c.deposit_date, c.cheque_date)                        AS due_on,
                    DATEDIFF(COALESCE(c.deposit_date, c.cheque_date), CURDATE())    AS days_until
               FROM cheques c
               JOIN payments p     ON p.id = c.payment_id
               JOIN customers cust ON cust.id = p.customer_id
              WHERE c.status = 'pending'
                AND COALESCE(c.deposit_date, c.cheque_date) <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
           ORDER BY due_on ASC
              LIMIT {$limit}",
            [$days]
        );
    }

    /** Headline totals for the cheque screens and the finance dashboard. */
    public function summary(): array
    {
        $row = $this->db()->first(
            "SELECT
                COALESCE(SUM(status = 'pending'), 0)                              AS pending_count,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount END), 0)     AS pending_value,
                COALESCE(SUM(status = 'cleared'), 0)                              AS cleared_count,
                COALESCE(SUM(CASE WHEN status = 'cleared' THEN amount END), 0)     AS cleared_value,
                COALESCE(SUM(status = 'bounced'), 0)                              AS bounced_count,
                COALESCE(SUM(CASE WHEN status = 'bounced' THEN amount END), 0)     AS bounced_value,
                COALESCE(SUM(status = 'pending'
                         AND COALESCE(deposit_date, cheque_date) < CURDATE()), 0)  AS overdue_count
               FROM cheques"
        );

        return $row ?: [];
    }

    public function byCustomer(int $customerId): array
    {
        return $this->db()->all(
            'SELECT c.* FROM cheques c
               JOIN payments p ON p.id = c.payment_id
              WHERE p.customer_id = ?
           ORDER BY COALESCE(c.deposit_date, c.cheque_date) DESC',
            [$customerId]
        );
    }
}
