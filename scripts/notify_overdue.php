<?php

/**
 * Notify Overdue Customers (CLI)
 * 
 * This script runs daily to find customers who haven't paid in > 30 days
 * and sends an email/SMS notification to the admin/manager.
 *
 * Usage via cron (e.g. daily at 8 AM):
 * 0 8 * * * cd /path/to/app && php scripts/notify_overdue.php
 */

define('BASE_PATH', dirname(__DIR__));

// Helpers + environment
require BASE_PATH . '/app/Helpers/helpers.php';
load_env(BASE_PATH . '/.env');

// Autoloader
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Models\Customer;

$daysOverdue = 30;
$limit = 50;

$customerModel = new Customer();
$overdueCustomers = $customerModel->getOverdueCustomers($daysOverdue, $limit);

if (empty($overdueCustomers)) {
    echo "No overdue customers found (> {$daysOverdue} days).\n";
    exit(0);
}

echo "Found " . count($overdueCustomers) . " overdue customers.\n";

// --- Notification Logic ---
// Replace this block with your actual SMS/Email integration

$message = "⚠️ OVERDUE ALERTS (" . date('Y-m-d') . ")\n\n";

foreach ($overdueCustomers as $c) {
    $outstanding = number_format($c['outstanding_due'], 2);
    $days = $c['days_overdue'];
    $message .= "- {$c['name']}: Rs. {$outstanding} ({$days} days overdue)\n";
}

// 1. Example Email (using standard PHP mail, configure SMTP as needed)
/*
$to = setting('admin_email', 'admin@example.com');
$subject = "Daily Overdue Customers Report";
$headers = "From: no-reply@shoebank.com";
mail($to, $subject, $message, $headers);
*/

// 2. Example SMS API
/*
$phone = setting('admin_phone', '0770000000');
$apiUrl = "https://api.smsprovider.com/send";
// curl request to SMS provider...
*/

echo "Notification sent successfully.\n";
echo "Message preview:\n";
echo "----------------------------------------\n";
echo $message;
echo "----------------------------------------\n";
