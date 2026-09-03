<?php

use App\Core\Database;
use App\Services\CustomerRecordService;

// SQLite supplies a disposable real database; only MySQL's row-lock syntax is removed.
class RecordTestDatabase extends Database
{
    public function __construct(PDO $pdo)
    {
        $property = new ReflectionProperty(Database::class, 'pdo');
        $property->setValue($this, $pdo);
    }
    public function first(string $sql, array $params = []): ?array
    {
        return parent::first(str_replace(' FOR UPDATE', '', $sql), $params);
    }
}
$recordPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$recordDb = new RecordTestDatabase($recordPdo);
$recordPdo->exec('CREATE TABLE customers (id INTEGER PRIMARY KEY, outstanding_due NUMERIC);
CREATE TABLE customer_transactions (id INTEGER PRIMARY KEY, customer_id INTEGER, transaction_type TEXT, reference_type TEXT, reference_id INTEGER, amount NUMERIC, running_balance NUMERIC, transaction_date TEXT, created_at TEXT, description TEXT);
CREATE TABLE payments (id INTEGER PRIMARY KEY, customer_id INTEGER, amount NUMERIC);
CREATE TABLE cheques (id INTEGER PRIMARY KEY, payment_id INTEGER);
INSERT INTO customers VALUES (1,94450), (2,321);
INSERT INTO payments VALUES (7,1,5550);
INSERT INTO customer_transactions VALUES
(1,1,"opening_balance","system",NULL,50000,50000,"2026-09-02","2026-09-02","Opening Balance"),
(2,1,"sale","manual_bill",NULL,50000,100000,"2026-09-03","2026-09-03","019"),
(3,1,"payment","payment",7,5550,94450,"2026-09-03","2026-09-03","Cash");');
$recordService = new CustomerRecordService($recordDb);
$recordService->change(1, ['amount' => 60000, 'transaction_date' => '2026-09-01', 'description' => 'Corrected opening']);
eq(104450, $recordDb->scalar('SELECT outstanding_due FROM customers WHERE id=1'), 'Editing opening balance updates customer outstanding');
eq(110000, $recordDb->scalar('SELECT running_balance FROM customer_transactions WHERE id=2'), 'Editing opening balance rebuilds later running balances');
$removedBill = $recordService->change(2, null);
eq(50000, $removedBill['record']['amount'], 'Deleted bill retains an audit snapshot');
eq(54450, $recordDb->scalar('SELECT outstanding_due FROM customers WHERE id=1'), 'Deleting bill subtracts its debit');
$recordService->change(3, null);
eq(0, $recordDb->scalar('SELECT COUNT(*) FROM payments'), 'Deleting payment removes the linked payment');
eq(60000, $recordDb->scalar('SELECT outstanding_due FROM customers WHERE id=1'), 'Deleting payment restores its credit to outstanding');
$recordService->change(1, null);
eq(0, $recordDb->scalar('SELECT outstanding_due FROM customers WHERE id=1'), 'Deleting final opening balance resets outstanding to zero');
eq(321, $recordDb->scalar('SELECT outstanding_due FROM customers WHERE id=2'), 'Record corrections leave other customers unchanged');
$recordPdo->exec('INSERT INTO payments VALUES (8,1,200);
INSERT INTO cheques VALUES (9,8);
INSERT INTO customer_transactions VALUES
(4,1,"opening_balance","system",NULL,500,500,"2026-09-01","2026-09-01","Opening"),
(5,1,"payment","payment",8,200,300,"2026-09-02","2026-09-02","Cheque"),
(6,1,"adjustment","cheque_bounce",9,200,500,"2026-09-03","2026-09-03","Bounce");');
$recordService->change(5, null);
eq(0, $recordDb->scalar('SELECT COUNT(*) FROM cheques'), 'Deleting cheque payment removes its cheque');
eq(0, $recordDb->scalar('SELECT COUNT(*) FROM customer_transactions WHERE reference_type="cheque_bounce"'), 'Deleting bounced payment removes its reversal');
eq(500, $recordDb->scalar('SELECT outstanding_due FROM customers WHERE id=1'), 'Deleting bounced payment preserves correct net balance');
ok(CustomerRecordService::kind(['transaction_type'=>'sale','reference_type'=>'sale','reference_id'=>2]) === null, 'Stock-backed invoice cannot be deleted as an independent ledger row');
try {
    $recordService->change(999, null);
    ok(false, 'Missing record is rejected');
} catch (DomainException $e) {
    ok(true, 'Missing record is rejected');
}

// Excess payments carry across multiple bills and remain correct after corrections.
$recordPdo->exec('INSERT INTO customers VALUES (3,1000);
INSERT INTO customer_transactions VALUES
(10,3,"opening_balance","system",NULL,1000,1000,"2026-09-01","2026-09-01","Opening"),
(11,3,"payment","payment",10,1500,-500,"2026-09-02","2026-09-02","Overpayment");
INSERT INTO payments VALUES (10,3,1500);');
$creditLedger = new \App\Services\CustomerLedgerService($recordDb);
eq(-500, $creditLedger->recalculate(3), 'Payment above outstanding is retained as customer credit');
$recordPdo->exec('INSERT INTO customer_transactions VALUES (12,3,"sale","manual_bill",NULL,200,0,"2026-09-03","2026-09-03","Next bill")');
eq(-300, $creditLedger->recalculate(3), 'Next bill uses only part of available customer credit');
$recordPdo->exec('INSERT INTO customer_transactions VALUES (13,3,"sale","manual_bill",NULL,400,0,"2026-09-04","2026-09-04","Another bill")');
eq(100, $creditLedger->recalculate(3), 'Bill exceeding remaining credit leaves only the difference due');
$recordService->change(13, null);
eq(-300, $recordDb->scalar('SELECT outstanding_due FROM customers WHERE id=3'), 'Deleting a bill restores credit automatically');
$recordDb->update('customer_transactions', ['amount'=>1800], ['id'=>11]);
eq(-600, $creditLedger->recalculate(3), 'Increasing an existing payment increases carried credit');
$recordService->change(11, null);
eq(1200, $recordDb->scalar('SELECT outstanding_due FROM customers WHERE id=3'), 'Deleting an overpayment restores the full unpaid balance');
$recordPdo->exec('INSERT INTO customers VALUES (4,0);
INSERT INTO customer_transactions VALUES (14,4,"payment","payment",11,250,-250,"2026-09-02","2026-09-02","Advance")');
eq(-250, $creditLedger->recalculate(4), 'Payment without existing debt becomes advance credit');
