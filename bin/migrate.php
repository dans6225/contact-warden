<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ContactWarden\Store\DatabaseConfig;

$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

$config = DatabaseConfig::fromEnv();
$pdo = new PDO($config->toDsn(), $config->username, $config->password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$migrationsDir = __DIR__ . '/../src/Store/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

foreach ($files as $file) {
    echo "Applying " . basename($file) . "...\n";
    $sql = file_get_contents($file);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }
}

echo "Done.\n";
