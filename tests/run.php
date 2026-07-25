<?php
/**
 * Dependency-free test runner (no PHPUnit needed — works on any PHP host).
 *
 *   php tests/run.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Minimal autoloader for the App\ namespace (mirrors public/index.php).
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;

function ok(bool $cond, string $label): void
{
    if ($cond) {
        $GLOBALS['__pass']++;
        echo "  \033[32m✓\033[0m {$label}\n";
    } else {
        $GLOBALS['__fail']++;
        echo "  \033[31m✗\033[0m {$label}\n";
    }
}

function eq($expected, $actual, string $label): void
{
    ok($expected == $actual, $label . "  (expected " . var_export($expected, true)
        . ", got " . var_export($actual, true) . ")");
}

echo "\nRunning tests…\n";
foreach (glob(BASE_PATH . '/tests/*Test.php') as $testFile) {
    echo "\n" . basename($testFile) . "\n";
    require $testFile;
}

$pass = $GLOBALS['__pass'];
$fail = $GLOBALS['__fail'];
echo "\n----------------------------------------\n";
echo ($fail === 0 ? "\033[32mALL PASSED\033[0m" : "\033[31mFAILURES\033[0m")
    . "  —  {$pass} passed, {$fail} failed\n\n";

exit($fail === 0 ? 0 : 1);
