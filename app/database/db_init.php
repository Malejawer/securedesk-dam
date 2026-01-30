<?php

declare(strict_types=1);

function db_init(PDO $pdo): array
{
    $messages = [];

    $pdo->exec('PRAGMA foreign_keys = ON;');

    // Tabla users
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user',
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");
    $messages[] = "✅ Tabla users creada/verificada";

    // Tabla tickets
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            status TEXT NOT NULL DEFAULT 'open',
            priority TEXT NOT NULL DEFAULT 'medium',
            created_by INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (created_by) REFERENCES users(id)
        );
    ");
    $messages[] = "✅ Tabla tickets creada/verificada";

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickets_created_by ON tickets(created_by);");
    $messages[] = "✅ Índice idx_tickets_created_by creado/verificado";

    return $messages;
}
