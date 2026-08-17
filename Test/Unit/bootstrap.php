<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$autoload = $root . '/../../../../../vendor/autoload.php';

if (is_file($autoload)) {
    require_once $autoload;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Exemptax\\Integration\\';
    if (!str_starts_with($class, $prefix) || str_starts_with($class, $prefix . 'Test\\')) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = dirname(__DIR__, 2) . '/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
