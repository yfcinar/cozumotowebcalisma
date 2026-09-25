<?php

declare(strict_types=1);

/**
 * Şema kurulum scripti. .env'deki DB_DRIVER'a göre uygun schema dosyasını çalıştırır.
 * Kullanım: php database/migrate.php
 */

use App\Database\Database;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$settings = require $root . '/config/settings.php';
$driver   = $settings['db']['driver'];
$pdo      = Database::fromConfig($settings['db']);

$schemaFile = $root . '/database/schema.' . $driver . '.sql';
if (!is_file($schemaFile)) {
    fwrite(STDERR, "Şema dosyası bulunamadı: {$schemaFile}\n");
    exit(1);
}

$sql = file_get_contents($schemaFile);

// SQLite çoklu ifadeyi exec ile çalıştırabilir; MySQL için noktalı virgülle bölüyoruz.
if ($driver === 'sqlite') {
    $pdo->exec($sql);
} else {
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt !== '' && !str_starts_with($stmt, '--')) {
            $pdo->exec($stmt);
        }
    }
}

// Mevcut kurulumlara sonradan eklenen kolonları güvenli biçimde uygula (idempotent).
$columnExists = static function (PDO $pdo, string $driver, string $table, string $column): bool {
    if ($driver === 'sqlite') {
        foreach ($pdo->query("PRAGMA table_info({$table})") as $col) {
            if (($col['name'] ?? '') === $column) {
                return true;
            }
        }
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c'
    );
    $stmt->execute(['t' => $table, 'c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};

$additions = [
    ['messages', 'is_starred', 'INTEGER NOT NULL DEFAULT 0', 'TINYINT(1) NOT NULL DEFAULT 0'],
    ['messages', 'status', "TEXT NOT NULL DEFAULT 'new'", "VARCHAR(20) NOT NULL DEFAULT 'new'"],
    ['services', 'price', 'TEXT', 'VARCHAR(120) NULL'],
];
foreach ($additions as [$table, $column, $sqliteType, $mysqlType]) {
    if (!$columnExists($pdo, $driver, $table, $column)) {
        $type = $driver === 'sqlite' ? $sqliteType : $mysqlType;
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$type}");
        echo "  + {$table}.{$column} eklendi.\n";
    }
}

echo "✓ Şema kuruldu ({$driver}).\n";
