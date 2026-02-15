<?php
declare(strict_types=1);
 
require_once __DIR__ . '/../services/AuthService.php';
 
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
 * Protege páginas privadas: si no hay sesión → login.
 */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        header('Location: ?page=login');
        exit;
    }
}
 
/**
 * Comprueba si el usuario tiene uno de los roles permitidos.
 */
function requireRole(array $allowedRoles): void
{
    requireAuth();
 
    $user = currentUser();
    if (!$user || !in_array($user['role'], $allowedRoles, true)) {
        setFlash('danger', 'No tienes permisos para acceder a esta sección.');
        header('Location: ?page=home');
        exit;
    }
}
 
/**
 * Comprueba si el usuario es admin.
 */
function requireAdmin(): void
{
    requireRole(['admin']);
}
 
/**
 * Guarda un mensaje flash en sesión.
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,     // success | danger | warning | info
        'message' => $message
    ];
}
 
/**
 * Obtiene y elimina el mensaje flash.
 */
function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
 
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}
 
/**
 * Cierra sesión.
 */
function logout(): void
{
    $_SESSION = [];
 
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
 
        header('Location: ?page=home');
        exit;
    }
 
    $data['error'] = $result['error'] ?? 'Error de autenticación.';
    return $data;
}