<?php

namespace App\Services;

use App\Core\Database;

class CustomerRecordService
{
    public function __construct(private ?Database $database = null) {}

    public static function kind(array $row): ?string
    {
        $type = $row['transaction_type'] ?? '';
        $ref = $row['reference_type'] ?? '';
        if ($type === 'sale' && $ref === 'manual_bill') return 'bill';
        if ($type === 'payment' && $ref === 'payment' && !empty($row['reference_id'])) return 'payment';
        if ($type === 'opening_balance' && in_array($ref, ['', 'system'], true) && empty($row['reference_id'])) return 'balance';
        if ($type === 'adjustment' && $ref === 'outstanding_adjustment' && empty($row['reference_id'])) return 'balance';
        return null;
    }

    /** Returns an audit snapshot; the caller records it with the acting user. */
    public function change(int $id, ?array $values): array
    {
        $db = $this->database ?? Database::instance();
        return $db->transaction(function () use ($db, $id, $values): array {
            $row = $db->first('SELECT * FROM customer_transactions WHERE id = ?', [$id]);
            if (!$row) throw new \DomainException('This record no longer exists.');
            $customerId = (int) $row['customer_id'];
            $db->first('SELECT id FROM customers WHERE id = ? FOR UPDATE', [$customerId]);
            $row = $db->first('SELECT * FROM customer_transactions WHERE id = ? FOR UPDATE', [$id]);
            if (!$row) throw new \DomainException('This record no longer exists.');
            $kind = self::kind($row);
            if (!$kind || ($values !== null && $kind !== 'balance')) {
                throw new \DomainException('Use the original transaction screen to change this record.');
            }
            $snapshot = ['record' => $row];
            if ($values !== null) {
                $db->update('customer_transactions', $values, ['id' => $id]);
            } else {
                if ($kind === 'payment') {
                    $paymentId = (int) $row['reference_id'];
                    $payment = $db->first('SELECT * FROM payments WHERE id = ? AND customer_id = ? FOR UPDATE', [$paymentId, $customerId]);
                    if (!$payment) throw new \DomainException('The linked payment could not be found.');
                    $snapshot['payment'] = $payment;
                    $snapshot['cheques'] = $db->all('SELECT * FROM cheques WHERE payment_id = ?', [$paymentId]);
                    foreach ($snapshot['cheques'] as $cheque) {
                        $snapshot['reversals'][] = $db->all('SELECT * FROM customer_transactions WHERE customer_id = ? AND reference_type = ? AND reference_id = ?', [$customerId, 'cheque_bounce', $cheque['id']]);
                        $db->deleteWhere('customer_transactions', ['customer_id' => $customerId, 'reference_type' => 'cheque_bounce', 'reference_id' => $cheque['id']]);
                    }
                    $db->deleteWhere('customer_transactions', ['customer_id' => $customerId, 'reference_type' => 'payment', 'reference_id' => $paymentId]);
                    $db->deleteWhere('cheques', ['payment_id' => $paymentId]);
                    $db->deleteWhere('payments', ['id' => $paymentId]);
                } else {
                    $db->deleteWhere('customer_transactions', ['id' => $id]);
                }
            }
            (new CustomerLedgerService($db))->recalculate($customerId);
            return $snapshot;
        });
    }
}
