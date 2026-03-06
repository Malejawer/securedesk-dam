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
    // Tabla ticket_attachments (adjuntos)
    // -------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ticket_attachments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL,

            stored_name TEXT NOT NULL,
            original_name TEXT NOT NULL,
            size_bytes INTEGER NOT NULL DEFAULT 0,

            uploaded_by INTEGER,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),

            FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
        );
    ");
    $messages[] = "✅ Tabla ticket_attachments creada/verificada";

    // -------------------------
    // Tabla ticket_comments (comentarios)
    // -------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ticket_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            comment TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),

            FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
    $messages[] = "✅ Tabla ticket_comments creada/verificada";

    // -------------------------
    // Tabla ticket_changes (historial cambios críticos)
    // -------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ticket_changes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,

            field TEXT NOT NULL,         -- status | priority | assigned_to
            old_value TEXT,
            new_value TEXT,

            created_at TEXT NOT NULL DEFAULT (datetime('now')),

            FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
    $messages[] = "✅ Tabla ticket_changes creada/verificada";

    // -------------------------
    // Tabla audit_logs (auditoría)
    // -------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            entity TEXT,
            entity_id INTEGER,
            details TEXT,
            ip TEXT,
            origin TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),

            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        );
    ");
    $messages[] = "✅ Tabla audit_logs creada/verificada";

    // -------------------------
    // Tabla login_attempts (limitador de intentos de login)
    // -------------------------
    // Registra intentos fallidos por combinación username+ip, con contador y bloqueo temporal.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT,
            ip TEXT,
            attempts INTEGER NOT NULL DEFAULT 0,
            last_attempt_at TEXT,
            locked_until TEXT
        );
    ");
    $messages[] = "✅ Tabla login_attempts creada/verificada";

    // -------------------------
    // Índices útiles
    // -------------------------
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickets_created_by ON tickets(created_by);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickets_priority ON tickets(priority);");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ticket_attachments_ticket_id ON ticket_attachments(ticket_id);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ticket_attachments_uploaded_by ON ticket_attachments(uploaded_by);");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ticket_comments_ticket_id ON ticket_comments(ticket_id);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ticket_comments_user_id ON ticket_comments(user_id);");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ticket_changes_ticket_id ON ticket_changes(ticket_id);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ticket_changes_user_id ON ticket_changes(user_id);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ticket_changes_created_at ON ticket_changes(created_at);");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_logs_user_id ON audit_logs(user_id);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_logs_entity ON audit_logs(entity);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at ON audit_logs(created_at);");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_username_ip ON login_attempts(username, ip);");

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