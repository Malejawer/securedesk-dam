<?php
declare(strict_types=1);
 
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/audit.php';
 
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
 * Protege rutas por rol.
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
 * Atajo: solo admin.
 */
function requireAdmin(): void
{
    requireRole(['admin']);
}
 
/**
 * Flash messages (Bootstrap).
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
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
 * Maneja el login.
 */
function handleLogin(QueryBuilder $qb): array
{
    $data = [
        'error' => '',
        'success' => '',
        'oldUsername' => ''
    ];
 
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $data;
    }
 
    $data['oldUsername'] = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
 
    $auth = new AuthService($qb);
    $result = $auth->attempt($data['oldUsername'], $password);
 
    if ($result['ok']) {
        session_regenerate_id(true);
 
        $_SESSION['user'] = [
            'id' => (int)$result['user']['id'],
            'username' => $result['user']['username'],
            'role' => $result['user']['role'],
        ];
 
        // Auditoría: login correcto
        audit_log($qb, (int)$result['user']['id'], 'auth.login', 'users', (int)$result['user']['id'], 'Login correcto');
 
        header('Location: ?page=home');
        exit;
    }
 
    $data['error'] = $result['error'] ?? 'Error de autenticación.';
    return $data;
}
