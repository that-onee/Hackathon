<?php
// Veritabanı migration — çalıştır, sonra sil
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

$migrations = [
    "ALTER TABLE projects ADD COLUMN eq_level VARCHAR(20) DEFAULT 'medium'",
    "ALTER TABLE projects ADD COLUMN roadmap_requested TINYINT(1) DEFAULT 0",
    "ALTER TABLE projects ADD COLUMN learning_outcomes_score FLOAT DEFAULT 0",
    "ALTER TABLE projects ADD COLUMN attempts INT DEFAULT 0",
    "ALTER TABLE roadmap_steps ADD COLUMN personal_note TEXT",
    "ALTER TABLE roadmap_steps ADD COLUMN completed_at DATETIME NULL",
];

echo "=== DATABASE MIGRATION ===\n\n";

foreach ($migrations as $sql) {
    try {
        getDB()->exec($sql);
        echo "✅ " . substr($sql, 0, 70) . "\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || 
            strpos($e->getMessage(), 'already exists') !== false) {
            echo "⚠️  Zaten var (atlandı): " . substr($sql, 12, 50) . "\n";
        } else {
            echo "❌ HATA: " . $e->getMessage() . "\n   SQL: $sql\n";
        }
    }
}

echo "\n✅ Migration tamamlandı! Bu dosyayı sil: migrate.php\n";
