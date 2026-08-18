<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Session;

/** Live, actionable business notifications with per-session read state. */
class NotificationService
{
    private const READ_KEY = 'read_notification_ids';

    public function all(int $limit = 50): array
    {
        try {
            $items = array_merge(
                $this->missingImages(),
                $this->verificationAttention(),
                $this->chequesDue(),
                $this->overdueCustomers(),
                [$this->monthlyReminder()]
            );
        } catch (\Throwable $e) {
            return [];
        }

        $read = array_flip(Session::get(self::READ_KEY, []));
        foreach ($items as &$item) $item['read'] = isset($read[$item['id']]);
        usort($items, fn ($a, $b) => [$a['read'], -strtotime($a['date'])] <=> [$b['read'], -strtotime($b['date'])]);
        return array_slice($items, 0, max(1, $limit));
    }

    public function unreadCount(): int
    {
        return count(array_filter($this->all(200), fn ($item) => !$item['read']));
    }

    public function markRead(string $id): void
    {
        $ids = Session::get(self::READ_KEY, []);
        if ($id !== '' && !in_array($id, $ids, true)) $ids[] = $id;
        Session::put(self::READ_KEY, array_slice($ids, -500));
    }

    public function markAllRead(): void
    {
        Session::put(self::READ_KEY, array_values(array_unique(array_column($this->all(200), 'id'))));
    }

    private function missingImages(): array
    {
        $rows = Database::instance()->all(
            'SELECT p.id, p.art_no, p.name, p.category_id, c.name AS category_name, p.updated_at,
                    EXISTS (SELECT 1 FROM product_images pi WHERE pi.product_id = p.id) AS has_image
               FROM products p LEFT JOIN categories c ON c.id = p.category_id
              WHERE p.deleted_at IS NULL
           ORDER BY p.updated_at DESC'
        );

        // Match the stock page identity rule: Art Number + Category is one base
        // product, even when older data still has separate colour rows.
        $groups = [];
        foreach ($rows as $row) {
            $art = strtolower(preg_replace('/[^a-z0-9]/i', '', trim((string) ($row['art_no'] ?? ''))));
            $key = ($art !== '' ? $art : '__product_' . $row['id']) . '::' . ($row['category_id'] ?? 0);
            if (!isset($groups[$key])) {
                $groups[$key] = $row;
            } else {
                $groups[$key]['has_image'] = (int) $groups[$key]['has_image'] || (int) $row['has_image'];
            }
        }
        $rows = array_slice(array_values(array_filter($groups, fn ($row) => !(int) $row['has_image'])), 0, 20);

        return array_map(fn ($r) => $this->item('image', 'product-' . $r['id'], 'Product image missing',
            trim(($r['art_no'] ?: $r['name'] ?: 'Product') . ($r['category_name'] ? ' / ' . $r['category_name'] : '')) . ' needs a product image.',
            'products/' . $r['id'], $r['updated_at']), $rows);
    }

    private function verificationAttention(): array
    {
        $rows = Database::instance()->all(
            "SELECT ga.id, ga.purchase_id, p.purchase_number, ga.updated_at,
                    COALESCE(SUM(ai.status = 'shortage'), 0) AS shortages,
                    COALESCE(SUM(ai.status = 'excess'), 0) AS excesses,
                    COALESCE(SUM(ai.status = 'pending'), 0) AS pending
               FROM goods_arrivals ga JOIN purchases p ON p.id = ga.purchase_id
          LEFT JOIN arrival_items ai ON ai.arrival_id = ga.id
              WHERE ga.inventory_updated = 0
           GROUP BY ga.id, ga.purchase_id, p.purchase_number, ga.updated_at
             HAVING shortages > 0 OR excesses > 0 OR pending > 0
           ORDER BY ga.updated_at DESC LIMIT 20"
        );
        return array_map(function ($r) {
            $message = (int) $r['pending'] > 0
                ? $r['pending'] . ' product count(s) still need verification.'
                : ((int) $r['shortages'] . ' shortage and ' . (int) $r['excesses'] . ' excess line(s) need review.');
            return $this->item('warning', 'arrival-' . $r['id'], 'Verification requires attention', $r['purchase_number'] . ': ' . $message, 'purchases/' . $r['purchase_id'] . '/arrival', $r['updated_at']);
        }, $rows);
    }

    private function chequesDue(): array
    {
        $rows = Database::instance()->all(
            "SELECT ch.id, ch.cheque_number, ch.amount, ch.cheque_date, c.name AS customer_name,
                    COALESCE(ch.deposit_date, ch.cheque_date) AS due_on
               FROM cheques ch JOIN payments p ON p.id = ch.payment_id JOIN customers c ON c.id = p.customer_id
              WHERE ch.status IN ('pending','deposited') AND COALESCE(ch.deposit_date, ch.cheque_date) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
           ORDER BY due_on LIMIT 20"
        );
        return array_map(fn ($r) => $this->item('cheque', 'cheque-' . $r['id'], 'Cheque needs attention',
            $r['customer_name'] . ' · #' . $r['cheque_number'] . ' · ' . money($r['amount']), 'cheques/' . $r['id'], $r['due_on'] . ' 09:00:00'), $rows);
    }

    private function overdueCustomers(): array
    {
        $rows = Database::instance()->all(
            'SELECT c.id, c.name, c.outstanding_due, ci.oldest_unpaid_date
               FROM customers c JOIN customer_intelligence ci ON ci.customer_id = c.id
              WHERE c.deleted_at IS NULL AND c.outstanding_due > 0 AND ci.oldest_unpaid_date < CURDATE()
           ORDER BY ci.oldest_unpaid_date LIMIT 15'
        );
        return array_map(fn ($r) => $this->item('customer', 'customer-overdue-' . $r['id'], 'Customer payment overdue',
            $r['name'] . ' owes ' . money($r['outstanding_due']) . '.', 'customers/' . $r['id'], $r['oldest_unpaid_date'] . ' 09:00:00'), $rows);
    }

    private function monthlyReminder(): array
    {
        $month = date('Y-m');
        return $this->item('calendar', 'monthly-' . $month, 'Monthly records reminder',
            'Add all purchases and business expenses for ' . date('F') . ' before reviewing the monthly summary.', 'finance', date('Y-m-01 08:00:00'));
    }

    private function item(string $type, string $key, string $title, string $message, string $url, string $date): array
    {
        return ['id' => substr(hash('sha256', $key), 0, 24), 'type' => $type, 'title' => $title, 'message' => $message, 'url' => $url, 'date' => $date];
    }
}
