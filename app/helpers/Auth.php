<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/audit.php';

/**
 * =========================
 * CSRF helpers
 * =========================
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) < 32) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    $t = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_validate(?string $token): bool
{
    if (!is_string($token) || $token === '') {
        return false;
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

/**
 * Devuelve el usuario actual de sesión o null.
 */
function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Indica si hay sesión iniciada.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']);
}

/**
 * Protege páginas privadas: si no hay login, redirige al login.
 */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        header('Location: ?page=login');
        exit;
    }
}

/**
 * Matriz de permisos por rol.
 */
function rolePermissions(): array
{
    return [
        'admin' => [
            'dashboard.view',
            'tickets.view',
            'tickets.create',
            'tickets.edit',
            'comments.create',
            'attachments.upload',
            'attachments.download',
            'reports.export',
            'audit.view',
            'users.manage',
            'database.update',
        ],
        'tecnico' => [
            'dashboard.view',
            'tickets.view',
            'tickets.create',
            'tickets.edit',
            'comments.create',
            'attachments.upload',
            'attachments.download',
            'reports.export',
        ],
        'lector' => [
            'dashboard.view',
            'tickets.view',
            'attachments.download',
        ],
    ];
}

/**
 * Comprueba si un usuario tiene un permiso concreto.
 */
function userCan(?array $user, string $permission): bool
{
    if (!is_array($user)) {
        return false;
    }

    $role = (string)($user['role'] ?? '');
    if ($role === '') {
        return false;
    }

    $matrix = rolePermissions();
    $permissions = $matrix[$role] ?? [];

    return in_array($permission, $permissions, true);
}

/**
 * Protege rutas por rol legacy.
 */
function requireRole(array $roles): void
{
    requireAuth();

    $user = currentUser();
    $role = $user['role'] ?? null;

    if (!$role || !in_array($role, $roles, true)) {
        setFlash('danger', 'No tienes permisos para acceder a esta sección.');
        header('Location: ?page=home');
        exit;
    }
}

/**
 * Protege rutas por permiso.
 */
function requirePermission(string $permission): void
{
    requireAuth();

    $user = currentUser();
    if (!userCan($user, $permission)) {
        setFlash('danger', 'No tienes permisos para acceder a esta sección.');
        header('Location: ?page=home');
        exit;
    }
}

/**
 * Atajo: solo admin.
 */
function requireAdmin(): void
{
    requirePermission('audit.view');
}

/**
 * Flash messages (Bootstrap).
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Logout seguro.
 */
function logout(): void
{
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool)$params['secure'],
            (bool)$params['httponly']
        );
    }

    session_destroy();
}

/**
 * Maneja el login con limitación de intentos.
 *
 * @param QueryBuilder $qb
 * @return array
 */
function handleLogin(QueryBuilder $qb): array
{
    $data = [
        'error' => '',
        'success' => '',
        'oldUsername' => '',
    ];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $data;
    }

    // CSRF
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $data['error'] = 'Token CSRF inválido. Recarga la página e inténtalo de nuevo.';
        return $data;
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $data['oldUsername'] = $username;

    // Validaciones estrictas
    if ($username === '' || $password === '') {
        $data['error'] = 'Usuario y contraseña son obligatorios.';
        return $data;
    }

    if (mb_strlen($username) > 50) {
        $data['error'] = 'El usuario es demasiado largo.';
        return $data;
    }

    if (!preg_match('/^[a-zA-Z0-9_\\-\\.]+$/', $username)) {
        $data['error'] = 'Formato de usuario no válido.';
        return $data;
    }

    if (mb_strlen($password) > 200) {
        $data['error'] = 'Contraseña no válida.';
        return $data;
    }

    // =========================
    // Política de contraseña
    // =========================
    if (mb_strlen($password) < 8) {
        $data['error'] = 'La contraseña debe tener al menos 8 caracteres.';
        return $data;
    }

    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $data['error'] = 'La contraseña debe contener letras y números.';
        return $data;
    }

    // =========================
    // Limitación de intentos
    // =========================
    $MAX_ATTEMPTS = 5;
    $WINDOW_MINUTES = 5;
    $LOCK_MINUTES = 5;

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ip = (is_string($ip) ? $ip : '');

    $attemptRow = $qb->table('login_attempts')
        ->select(['id', 'attempts', 'last_attempt_at', 'locked_until'])
        ->where('username', '=', $username)
        ->where('ip', '=', $ip)
        ->first();

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    if ($attemptRow) {
        $lockedUntil = $attemptRow['locked_until'] ?? null;
        if ($lockedUntil) {
            try {
                $lockedDT = new DateTimeImmutable($lockedUntil, new DateTimeZone('UTC'));
                if ($lockedDT > $now) {
                    $diff = $lockedDT->getTimestamp() - $now->getTimestamp();
                    $minsLeft = (int)ceil($diff / 60);
                    $data['error'] = "Cuenta bloqueada temporalmente. Intenta de nuevo en {$minsLeft} minuto(s).";
                    audit_log($qb, null, 'auth.login_blocked', 'users', null, "Login bloqueado para {$username} desde IP {$ip}");
                    return $data;
                }
            } catch (Exception $e) {
                // ignorar
            }
        }
    }

    $auth = new AuthService($qb);
    $result = $auth->attempt($username, $password);

    if ($result['ok']) {
        if ($attemptRow) {
            try {
                $qb->table('login_attempts')
                    ->where('id', '=', (int)$attemptRow['id'])
                    ->delete();
            } catch (Throwable $_) {
                try {
                    $qb->table('login_attempts')
                        ->where('id', '=', (int)$attemptRow['id'])
                        ->update([
                            'attempts' => 0,
                            'last_attempt_at' => null,
                            'locked_until' => null,
                        ]);
                } catch (Throwable $_) {
                    // no crítico
                }
            }
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$result['user']['id'],
            'username' => $result['user']['username'],
            'role' => $result['user']['role'],
        ];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        audit_log($qb, (int)$result['user']['id'], 'auth.login', 'users', (int)$result['user']['id'], 'Login correcto');

        header('Location: ?page=home');
        exit;
    }

    $attempts = 1;
    $lockedUntilStr = null;

    if ($attemptRow) {
        $lastAttemptAt = $attemptRow['last_attempt_at'] ?? null;
        $attemptsStored = (int)($attemptRow['attempts'] ?? 0);

        $withinWindow = false;
        if ($lastAttemptAt) {
            try {
                $lastDT = new DateTimeImmutable($lastAttemptAt, new DateTimeZone('UTC'));
                $diffSec = $now->getTimestamp() - $lastDT->getTimestamp();
                if ($diffSec <= ($WINDOW_MINUTES * 60)) {
                    $withinWindow = true;
                }
            } catch (Exception $_) {
                $withinWindow = false;
            }
        }

        if ($withinWindow) {
            $attempts = $attemptsStored + 1;
        } else {
            $attempts = 1;
        }

        if ($attempts >= $MAX_ATTEMPTS) {
            $lockedDT = $now->add(new DateInterval('PT' . ($LOCK_MINUTES * 60) . 'S'));
            $lockedUntilStr = $lockedDT->format('Y-m-d H:i:s');

            try {
                $qb->table('login_attempts')
                    ->where('id', '=', (int)$attemptRow['id'])
                    ->update([
                        'attempts' => $attempts,
                        'last_attempt_at' => $now->format('Y-m-d H:i:s'),
                        'locked_until' => $lockedUntilStr,
                    ]);
            } catch (Throwable $_) {
                if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) {
                    $pdo = $GLOBALS['db'];
                    $stmt = $pdo->prepare("UPDATE login_attempts SET attempts = :attempts, last_attempt_at = :last, locked_until = :locked WHERE id = :id");
                    $stmt->execute([
                        ':attempts' => $attempts,
                        ':last' => $now->format('Y-m-d H:i:s'),
                        ':locked' => $lockedUntilStr,
                        ':id' => (int)$attemptRow['id'],
                    ]);
                }
            }

            audit_log($qb, null, 'auth.lock', 'users', null, "Usuario {$username} bloqueado tras {$attempts} intentos desde IP {$ip}");
            $data['error'] = "Demasiados intentos. Cuenta bloqueada temporalmente por {$LOCK_MINUTES} minutos.";
            return $data;
        }

        try {
            $qb->table('login_attempts')
                ->where('id', '=', (int)$attemptRow['id'])
                ->update([
                    'attempts' => $attempts,
                    'last_attempt_at' => $now->format('Y-m-d H:i:s'),
                    'locked_until' => null,
                ]);
        } catch (Throwable $_) {
            if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) {
                $pdo = $GLOBALS['db'];
                $stmt = $pdo->prepare("UPDATE login_attempts SET attempts = :attempts, last_attempt_at = :last WHERE id = :id");
                $stmt->execute([
                    ':attempts' => $attempts,
                    ':last' => $now->format('Y-m-d H:i:s'),
                    ':id' => (int)$attemptRow['id'],
                ]);
            }
        }
    } else {
        try {
            $qb->table('login_attempts')->insert([
                'username' => $username,
                'ip' => $ip,
                'attempts' => 1,
                'last_attempt_at' => $now->format('Y-m-d H:i:s'),
                'locked_until' => null,
            ]);
        } catch (Throwable $_) {
            if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) {
                $pdo = $GLOBALS['db'];
                $stmt = $pdo->prepare("INSERT INTO login_attempts (username, ip, attempts, last_attempt_at) VALUES (:username, :ip, :attempts, :last)");
                $stmt->execute([
                    ':username' => $username,
                    ':ip' => $ip,
                    ':attempts' => 1,
                    ':last' => $now->format('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    audit_log($qb, null, 'auth.login_failed', 'users', null, "Login fallido para {$username} desde IP {$ip} (intentos={$attempts})");

    $data['error'] = 'Usuario o contraseña incorrectos.';
    return $data;
}