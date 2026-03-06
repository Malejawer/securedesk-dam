<?php
declare(strict_types=1);

/**
 * Registra un evento de auditoría.
 *
 * @param QueryBuilder $qb
 * @param int|null $userId
 * @param string $action  Ej: auth.login, auth.logout, ticket.create, ticket.update, comment.create...
 * @param string|null $entity Ej: users, tickets
 * @param int|null $entityId
 * @param string|null $details
 */
function audit_log(
    QueryBuilder $qb,
    ?int $userId,
    string $action,
    ?string $entity = null,
    ?int $entityId = null,
    ?string $details = null
): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
    if ($xff) {
        $parts = explode(',', $xff);
        $first = trim($parts[0] ?? '');
        if ($first !== '') {
            $ip = $first;
        }
    }

    $origin = $_SERVER['HTTP_HOST'] ?? null;

    if ($details !== null && mb_strlen($details) > 4000) {
        $details = mb_substr($details, 0, 4000) . '...';
    }

    $qb->table('audit_logs')->insert([
        'user_id'   => $userId,
        'action'    => $action,
        'entity'    => $entity,
        'entity_id' => $entityId,
        'details'   => $details,
        'ip'        => $ip,
        'origin'    => $origin,
    ]);
}