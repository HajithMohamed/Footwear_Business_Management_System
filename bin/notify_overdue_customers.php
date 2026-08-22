<?php

/**
 * Overdue Customer Notifier (CLI)
 * 
 * Run this script via Windows Task Scheduler or cron daily.
 * It checks for customers who have been overdue for > 30 days
 * and triggers a Windows desktop notification.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Models\Customer;

$config = require __DIR__ . '/../config/database.php';
Database::init($config);

echo "Checking for overdue customers...\n";

$model = new Customer();
// Get customers > 30 days overdue (default is 30)
$overdue = $model->getOverdueCustomers(30, 100);

if (empty($overdue)) {
    echo "No customers are currently over 30 days overdue.\n";
    exit(0);
}

$count = count($overdue);
$total = array_sum(array_column($overdue, 'outstanding_due'));
$totalFormatted = number_format($total, 2);

$title = "⚠️ CRM Alert: $count Overdue Accounts";
$message = "You have $count customer(s) whose payments are delayed by more than 1 month. Total overdue: Rs. $totalFormatted. Please check the system.";

echo "Found $count overdue customers. Sending notification...\n";

// Since user is on Windows, we can use PowerShell to trigger a toast notification.
// We execute a brief PowerShell command to show a balloon tip or toast.
$psScript = <<<POWERSHELL
[reflection.assembly]::loadwithpartialname("System.Windows.Forms");
[reflection.assembly]::loadwithpartialname("System.Drawing");
\$notify = new-object system.windows.forms.notifyicon;
\$notify.icon = [System.Drawing.SystemIcons]::Warning;
\$notify.visible = \$true;
\$notify.showballoontip(10000, "$title", "$message", [system.windows.forms.tooltipicon]::Warning);
Start-Sleep -s 5;
\$notify.visible = \$false;
POWERSHELL;

// Save the ps1 script to a temporary file
$tempPs1 = sys_get_temp_dir() . '\\notify_overdue.ps1';
file_put_contents($tempPs1, $psScript);

// Execute it silently in the background
exec('powershell.exe -ExecutionPolicy Bypass -WindowStyle Hidden -File "' . $tempPs1 . '"');

// Clean up
@unlink($tempPs1);

echo "Notification sent successfully.\n";
