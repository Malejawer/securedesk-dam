<?php
declare(strict_types=1);
 
final class AuthService
{
    public function __construct(private QueryBuilder $qb) {}
 
    public function attempt(string $username, string $password): array
    {
        $username = trim($username);
 
        if ($username === '' || $password === '') {
            return ['ok' => false, 'error' => 'Usuario y contraseña obligatorios.'];
        }
 
        // Buscar usuario con QueryBuilder
        $user = $this->qb->table('users')
            ->where('username', '=', $username)
            ->first();
 
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Credenciales incorrectas.'];
        }
 
        return [
            'ok' => true,
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
            ]
        ];
    }
}