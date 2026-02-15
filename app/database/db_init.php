<?php
declare(strict_types=1);
 
function db_init(PDO $pdo): array
{
    $messages = [];
 
    $pdo->exec('PRAGMA foreign_keys = ON;');
 
    // -------------------------
    // Tabla users
    // -------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'lector',
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");
    $messages[] = "✅ Tabla users creada/verificada";
 
    // -------------------------
    // Tabla tickets
    // -------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT NOT NULL,
            category TEXT,
 
            status TEXT NOT NULL DEFAULT 'nuevo'
                CHECK(status IN ('nuevo','en_proceso','resuelto')),
 
            priority TEXT NOT NULL DEFAULT 'media'
                CHECK(priority IN ('baja','media','alta','critica')),
 
            created_by INTEGER NOT NULL,
            assigned_to INTEGER,
 
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT,
 
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
        );
    ");
    $messages[] = "✅ Tabla tickets creada/verificada";
 
    // -------------------------
    // Índices útiles
    // -------------------------
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickets_created_by ON tickets(created_by);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickets_priority ON tickets(priority);");
 
    $messages[] = "✅ Índices creados/verificados";
 
    // -------------------------
    // Usuarios iniciales
    // -------------------------
    $seedUsers = [
        ['username' => 'admin',   'password' => 'admin123',   'role' => 'admin'],
        ['username' => 'tecnico', 'password' => 'tecnico123', 'role' => 'tecnico'],
        ['username' => 'lector',  'password' => 'lector123',  'role' => 'lector'],
    ];
 
    $stmt = $pdo->prepare("
        INSERT OR IGNORE INTO users (username, password_hash, role)
        VALUES (:username, :password_hash, :role)
    ");
 
    foreach ($seedUsers as $u) {
        $stmt->execute([
            ':username' => $u['username'],
            ':password_hash' => password_hash($u['password'], PASSWORD_DEFAULT),
            ':role' => $u['role'],
        ]);
    }
 
    $messages[] = "✅ Usuarios iniciales creados/verificados (admin, tecnico, lector)";
 
    return $messages;
}