<?php

declare(strict_types=1);

// Works from a checkout of this repo (vendor/ inside the package) and when the
// package is installed as a dependency (this file at vendor/dans6225/contact-warden/bin/).
foreach ([__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../../autoload.php'] as $autoload) {
    if (is_file($autoload)) {
        require $autoload;
        break;
    }
}

use ContactWarden\Store\DatabaseConfig;

if (!class_exists(DatabaseConfig::class)) {
    fwrite(STDERR, "Composer autoloader not found - run `composer install` first.\n");
    exit(1);
}

// Connection settings: real environment variables win; otherwise .env from the
// current directory (your project root when installed as a dependency), falling
// back to this package's own .env.
$envFile = null;
foreach ([getcwd() . '/.env', __DIR__ . '/../.env'] as $candidate) {
    if (is_file($candidate)) {
        $envFile = $candidate;
        break;
    }
}
if ($envFile !== null) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if (isset($_ENV[$key]) || getenv($key) !== false) {
            continue;
        }
        $_ENV[$key] = trim(trim($value), "\"'");
    }
}

$config = DatabaseConfig::fromEnv();
$pdo = new PDO($config->toDsn(), $config->username, $config->password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$migrationsDir = __DIR__ . '/../src/Store/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

echo "Target: {$config->host} / {$config->database}\n";
foreach ($files as $file) {
    echo "Applying " . basename($file) . "...\n";
    $sql = file_get_contents($file);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }
}

echo "Done.\n";
