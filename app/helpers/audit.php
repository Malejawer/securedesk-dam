<?php
declare(strict_types=1);
 
/**
 * Registra un evento de auditoría.
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
        $ip = trim($parts[0]) ?: $ip;
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
