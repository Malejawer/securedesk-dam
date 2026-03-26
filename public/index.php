<?php
declare(strict_types=1);
 
session_start();
 
require_once __DIR__ . '/../app/database/Database.php';
require_once __DIR__ . '/../app/services/HealthCheckService.php';
require_once __DIR__ . '/../app/database/QueryBuilder.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/audit.php';

require_once __DIR__ . '/../vendor/dompdf/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
 
$config = require __DIR__ . '/../app/config/config.php';
 
$db = Database::connect($config);
$service = new HealthCheckService($db);
$qb = new QueryBuilder($db);
 
$status = $service->check()
    ? '✅ SQLite creada y conexión funcionando en local'
    : '❌ Error en la conexión';
 
$page = $_GET['page'] ?? 'login';
 
$title = 'SecureDesk DAM';
$content = '';
 
// -------------------------
// Adjuntos: carpeta segura (fuera de /public)
// Guardaremos en /storage/uploads
// -------------------------
$uploadsDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0775, true);
}

switch ($page) {
 
    case 'db_update':
        requireAdmin();
 
        // ✅ Ruta correcta en tu proyecto
        require_once __DIR__ . '/../app/database/db_init.php';
 
        $messages = db_init($db);
 
        // Auditoría: actualización BD
        $u = currentUser();
        $uid = is_array($u) ? (int)($u['id'] ?? 0) : 0;
        audit_log($qb, $uid > 0 ? $uid : null, 'admin.db_update', 'database', null, 'Ejecutado db_init');
 
        setFlash('success', 'Base de datos verificada/actualizada correctamente.');
        header('Location: ?page=account');
        exit;
 
    case 'login':
        if (isLoggedIn()) {
            header('Location: ?page=home');
            exit;
        }
 
        $title = 'Login - SecureDesk DAM';
 
        $data = handleLogin($qb);
        $error = $data['error'];
        $success = $data['success'];
        $oldUsername = $data['oldUsername'];
 
        ob_start();
        require __DIR__ . '/../views/login_view.php';
        $content = ob_get_clean();
        break;
 
    case 'logout':
        // Auditoría: logout
        $u = currentUser();
        if (is_array($u) && isset($u['id'])) {
            audit_log($qb, (int)$u['id'], 'auth.logout', 'users', (int)$u['id'], 'Logout');
        }
 
        logout();
        header('Location: ?page=login');
        exit;
 
    case 'home':
        requireAuth();

        $title = 'Home - SecureDesk DAM';

        $user = currentUser();
        $currentUserId = is_array($user) ? (int)($user['id'] ?? 0) : 0;

        // Auditoría ligera: acceso al dashboard
        if ($currentUserId > 0) {
            audit_log(
                $qb,
                $currentUserId,
                'dashboard.view',
                'dashboard',
                null,
                'Acceso al dashboard principal'
            );
        }

        // =========================
        // KPIs - Contadores
        // =========================

        $totalTickets = (int)$db->query("SELECT COUNT(*) FROM tickets")->fetchColumn();

        $stmt = $db->query("
            SELECT status, COUNT(*) as total
            FROM tickets
            GROUP BY status
        ");
        $statusCountsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $statusCounts = [
            'nuevo' => 0,
            'en_proceso' => 0,
            'resuelto' => 0,
        ];

        foreach ($statusCountsRaw as $row) {
            $status = (string)($row['status'] ?? '');
            if (array_key_exists($status, $statusCounts)) {
                $statusCounts[$status] = (int)$row['total'];
            }
        }

        $stmt = $db->query("
            SELECT priority, COUNT(*) as total
            FROM tickets
            GROUP BY priority
        ");
        $priorityCountsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $priorityCounts = [
            'baja' => 0,
            'media' => 0,
            'alta' => 0,
            'critica' => 0,
        ];

        foreach ($priorityCountsRaw as $row) {
            $priority = (string)($row['priority'] ?? '');
            if (array_key_exists($priority, $priorityCounts)) {
                $priorityCounts[$priority] = (int)$row['total'];
            }
        }

        $stmt = $db->query("
            SELECT
                CASE
                    WHEN category IS NULL OR TRIM(category) = '' THEN 'otros'
                    WHEN LOWER(TRIM(category)) = 'phishing' THEN 'phishing'
                    WHEN LOWER(TRIM(category)) = 'malware' THEN 'malware'
                    WHEN LOWER(TRIM(category)) = 'permisos' THEN 'permisos'
                    ELSE 'otros'
                END AS category_group,
                COUNT(*) AS total
            FROM tickets
            GROUP BY category_group
        ");
        $categoryCountsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $categoryCounts = [
            'phishing' => 0,
            'malware' => 0,
            'permisos' => 0,
            'otros' => 0,
        ];

        foreach ($categoryCountsRaw as $row) {
            $category = (string)($row['category_group'] ?? '');
            if (array_key_exists($category, $categoryCounts)) {
                $categoryCounts[$category] = (int)$row['total'];
            }
        }

        $lastUpdate = date('Y-m-d H:i:s');

        ob_start();
        require __DIR__ . '/../views/home_view.php';
        $content = ob_get_clean();
        break;
 
    case 'tickets':
        requireRole(['admin', 'tecnico', 'lector']);

        $title = 'Tickets - SecureDesk DAM';

        $statusFilter = $_GET['status'] ?? null;
        $priorityFilter = $_GET['priority'] ?? null;
        $assignedFilter = $_GET['assigned'] ?? null;
        $searchRaw = trim((string)($_GET['q'] ?? ''));

        $allowedStatus = ['nuevo','en_proceso','resuelto'];
        $allowedPriority = ['baja','media','alta','critica'];
        $allowedAssigned = ['unassigned'];

        $conditions = [];
        $params = [];

        if ($statusFilter && in_array($statusFilter, $allowedStatus, true)) {
            $conditions[] = 't.status = :status';
            $params[':status'] = $statusFilter;
        }

        if ($priorityFilter && in_array($priorityFilter, $allowedPriority, true)) {
            $conditions[] = 't.priority = :priority';
            $params[':priority'] = $priorityFilter;
        }

        if ($assignedFilter && in_array($assignedFilter, $allowedAssigned, true)) {
            if ($assignedFilter === 'unassigned') {
                $conditions[] = 't.assigned_to IS NULL';
            }
        }

        $search = '';
        if ($searchRaw !== '') {
            $search = mb_substr($searchRaw, 0, 100);
            $conditions[] = '(t.title LIKE :search OR t.description LIKE :search)';
            $params[':search'] = '%' . $search . '%';

            // Auditoría ligera: búsqueda
            $user = currentUser();
            $currentUserId = is_array($user) ? (int)($user['id'] ?? 0) : 0;

            if ($currentUserId > 0) {
                audit_log(
                    $qb,
                    $currentUserId,
                    'ticket.search',
                    'tickets',
                    null,
                    'Búsqueda: ' . $search
                );
            }
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $stmt = $db->prepare("
            SELECT 
                t.*,
                u_assign.username AS assigned_username,
                u_create.username AS created_username,
                (
                    SELECT COUNT(*)
                    FROM ticket_comments c
                    WHERE c.ticket_id = t.id
                ) AS comments_count
            FROM tickets t
            LEFT JOIN users u_assign ON t.assigned_to = u_assign.id
            LEFT JOIN users u_create ON t.created_by = u_create.id
            $where
            ORDER BY t.created_at DESC
        ");

        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        require __DIR__ . '/../views/tickets_view.php';
        $content = ob_get_clean();
        break;
 
    case 'ticket_new':
        requireRole(['admin', 'tecnico']);
 
        $title = 'Nuevo ticket - SecureDesk DAM';
 
        $user = currentUser();
        $currentUserId = (int)($user['id'] ?? 0);
 
        $assignees = $qb->table('users')
            ->select(['id', 'username', 'role'])
            ->whereIn('role', ['admin', 'tecnico'])
            ->orderBy('username', 'ASC')
            ->get();
 
        $errors = [];
        $old = [
            'title' => '',
            'description' => '',
            'category' => '',
            'priority' => 'media',
            'assigned_to' => '',
        ];
 
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF
            if (!csrf_validate($_POST['csrf_token'] ?? '')) {
                setFlash('danger', 'Token CSRF inválido. Recarga la página e inténtalo de nuevo.');
                header('Location: ?page=ticket_new');
                exit;
            }

            $old['title'] = trim($_POST['title'] ?? '');
            $old['description'] = trim($_POST['description'] ?? '');
            $old['category'] = trim($_POST['category'] ?? '');
            $old['priority'] = $_POST['priority'] ?? 'media';
            $old['assigned_to'] = $_POST['assigned_to'] ?? '';
 
            if ($old['title'] === '') {
                $errors['title'] = 'El título es obligatorio.';
            }
 
            $allowedPriority = ['baja','media','alta','critica'];
            if (!in_array($old['priority'], $allowedPriority, true)) {
                $old['priority'] = 'media';
            }
 
            $assignedTo = null;
            if ($old['assigned_to'] !== '') {
                if (!ctype_digit($old['assigned_to'])) {
                    $errors['assigned_to'] = 'Asignación no válida.';
                } else {
                    $assignedTo = (int)$old['assigned_to'];
 
                    $assigneeOk = $qb->table('users')
                        ->select(['id'])
                        ->where('id', '=', $assignedTo)
                        ->whereIn('role', ['admin', 'tecnico'])
                        ->first();
 
                    if (!$assigneeOk) {
                        $errors['assigned_to'] = 'Solo se puede asignar a Admin o Técnico.';
                    }
                }
            }
 
            $createdBy = $currentUserId;
            if ($createdBy <= 0) {
                $errors['general'] = 'No se pudo identificar el usuario autenticado.';
            }
 
            if (!$errors) {
                $newTicketId = $qb->table('tickets')->insert([
                    'title' => $old['title'],
                    'description' => $old['description'] !== '' ? $old['description'] : '',
                    'category' => $old['category'] !== '' ? $old['category'] : null,
                    'status' => 'nuevo',
                    'priority' => $old['priority'],
                    'created_by' => $createdBy,
                    'assigned_to' => $assignedTo,
                    'updated_at' => null,
                ]);
 
                // Auditoría: crear ticket
                $u = currentUser();
                $uid = is_array($u) ? (int)($u['id'] ?? 0) : 0;
                audit_log($qb, $uid > 0 ? $uid : null, 'ticket.create', 'tickets', $newTicketId, 'Título: ' . $old['title']);

                // =========================
                // Adjunto opcional al crear ticket
                // =========================
                $attachmentWarning = null;

                if (isset($_FILES['attachment']) && is_array($_FILES['attachment'])) {

                    $file = $_FILES['attachment'];

                    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {

                        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                            $attachmentWarning = 'Ticket creado, pero hubo un error subiendo el adjunto.';
                        } else {

                            $originalName = (string)($file['name'] ?? 'archivo');
                            $sizeBytes = (int)($file['size'] ?? 0);

                            $maxBytes = 10 * 1024 * 1024; // 10MB
                            if ($sizeBytes <= 0 || $sizeBytes > $maxBytes) {
                                $attachmentWarning = 'Ticket creado, pero el adjunto es demasiado grande (máx. 10MB).';
                            } else {

                                $ext = pathinfo($originalName, PATHINFO_EXTENSION);
                                $ext = $ext ? ('.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext)) : '';

                                $storedName = bin2hex(random_bytes(16)) . '_' . time() . $ext;
                                $destPath = $uploadsDir . DIRECTORY_SEPARATOR . $storedName;

                                if (!move_uploaded_file((string)$file['tmp_name'], $destPath)) {
                                    $attachmentWarning = 'Ticket creado, pero no se pudo guardar el adjunto.';
                                } else {

                                    $qb->table('ticket_attachments')->insert([
                                        'ticket_id'     => $newTicketId,
                                        'stored_name'   => $storedName,
                                        'original_name' => $originalName,
                                        'size_bytes'    => $sizeBytes,
                                        'uploaded_by'   => $currentUserId > 0 ? $currentUserId : null,
                                    ]);

                                    audit_log(
                                        $qb,
                                        $currentUserId > 0 ? $currentUserId : null,
                                        'attachment.upload',
                                        'tickets',
                                        $newTicketId,
                                        'Archivo: ' . $originalName
                                    );
                                }
                            }
                        }
                    }
                }
 
                if ($attachmentWarning !== null) {
                    setFlash('warning', $attachmentWarning);
                } else {
                    setFlash('success', 'Ticket creado correctamente.');
                }

                header('Location: ?page=tickets');
                exit;
            }
        }
 
        ob_start();
        require __DIR__ . '/../views/ticket_new_view.php';
        $content = ob_get_clean();
        break;
 
    case 'ticket':
        requireRole(['admin', 'tecnico', 'lector']);
 
        $idRaw = $_GET['id'] ?? '';
        if (!ctype_digit((string)$idRaw)) {
            setFlash('danger', 'Ticket no válido.');
            header('Location: ?page=tickets');
            exit;
        }
        $ticketId = (int)$idRaw;
 
        $user = currentUser();
        $role = (string)($user['role'] ?? '');
        $canEdit = in_array($role, ['admin', 'tecnico'], true);
 
        $allowedStatus = ['nuevo','en_proceso','resuelto'];
        $allowedPriority = ['baja','media','alta','critica'];
 
        // JOIN -> prepare()
        $stmt = $db->prepare("
            SELECT 
                t.*,
                u_assign.username AS assigned_username,
                u_create.username AS created_username
            FROM tickets t
            LEFT JOIN users u_assign ON t.assigned_to = u_assign.id
            LEFT JOIN users u_create ON t.created_by = u_create.id
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
 
        if (!$ticket) {
            setFlash('danger', 'El ticket no existe.');
            header('Location: ?page=tickets');
            exit;
        }
 
        // Adjuntos (JOIN -> prepare)
        $stmt = $db->prepare("
            SELECT
                a.*,
                u.username AS uploaded_username
            FROM ticket_attachments a
            LEFT JOIN users u ON a.uploaded_by = u.id
            WHERE a.ticket_id = :ticket_id
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([':ticket_id' => $ticketId]);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
        // Comentarios (JOIN -> prepare)
        $stmt = $db->prepare("
            SELECT
                c.*,
                u.username AS username
            FROM ticket_comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.ticket_id = :ticket_id
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([':ticket_id' => $ticketId]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
        // Historial de cambios (JOIN -> prepare)
        $stmt = $db->prepare("
            SELECT
                ch.*,
                u.username AS username
            FROM ticket_changes ch
            LEFT JOIN users u ON ch.user_id = u.id
            WHERE ch.ticket_id = :ticket_id
            ORDER BY ch.created_at DESC
        ");
        $stmt->execute([':ticket_id' => $ticketId]);
        $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
        // Assignees para select
        $assignees = $qb->table('users')
            ->select(['id', 'username', 'role'])
            ->whereIn('role', ['admin', 'tecnico'])
            ->orderBy('username', 'ASC')
            ->get();
 
        $errors = [];
 
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF
            if (!csrf_validate($_POST['csrf_token'] ?? '')) {
                setFlash('danger', 'Token CSRF inválido. Recarga la página e inténtalo de nuevo.');
                header('Location: ?page=ticket&id=' . $ticketId);
                exit;
            }

            if (!$canEdit) {
                setFlash('danger', 'No tienes permisos para editar este ticket.');
                header('Location: ?page=ticket&id=' . $ticketId);
                exit;
            }
 
            $newDescription = trim($_POST['description'] ?? '');
            $newStatus = $_POST['status'] ?? ($ticket['status'] ?? 'nuevo');
            $newPriority = $_POST['priority'] ?? ($ticket['priority'] ?? 'media');
            $assignedRaw = $_POST['assigned_to'] ?? '';
 
            if (!in_array($newStatus, $allowedStatus, true)) {
                $errors['status'] = 'Estado no válido.';
            }
            if (!in_array($newPriority, $allowedPriority, true)) {
                $errors['priority'] = 'Prioridad no válida.';
            }
 
            $assignedTo = null;
            if ($assignedRaw !== '') {
                if (!ctype_digit($assignedRaw)) {
                    $errors['assigned_to'] = 'Asignación no válida.';
                } else {
                    $assignedTo = (int)$assignedRaw;
 
                    $assigneeOk = $qb->table('users')
                        ->select(['id'])
                        ->where('id', '=', $assignedTo)
                        ->whereIn('role', ['admin', 'tecnico'])
                        ->first();
 
                    if (!$assigneeOk) {
                        $errors['assigned_to'] = 'Solo se puede asignar a Admin o Técnico.';
                    }
                }
            }
 
            if (!$errors) {
                // ========= TAREA 5: Cambios críticos =========
                $editorId = (int)($user['id'] ?? 0);
 
                $oldStatus = (string)($ticket['status'] ?? '');
                $oldPriority = (string)($ticket['priority'] ?? '');
                $oldAssignedId = $ticket['assigned_to'] ?? null;
 
                $getAssignedLabel = function ($userId) use ($qb): string {
                    if ($userId === null || $userId === '' || (int)$userId <= 0) {
                        return 'Sin asignar';
                    }
                    $u = $qb->table('users')->select(['username'])->where('id', '=', (int)$userId)->first();
                    return $u ? (string)$u['username'] : 'Usuario eliminado';
                };
 
                if ($oldStatus !== $newStatus) {
                    $qb->table('ticket_changes')->insert([
                        'ticket_id' => $ticketId,
                        'user_id' => $editorId,
                        'field' => 'status',
                        'old_value' => $oldStatus,
                        'new_value' => $newStatus,
                    ]);
                }
 
                if ($oldPriority !== $newPriority) {
                    $qb->table('ticket_changes')->insert([
                        'ticket_id' => $ticketId,
                        'user_id' => $editorId,
                        'field' => 'priority',
                        'old_value' => $oldPriority,
                        'new_value' => $newPriority,
                    ]);
                }
 
                $oldAssignedLabel = $getAssignedLabel($oldAssignedId);
                $newAssignedLabel = $getAssignedLabel($assignedTo);
 
                if ($oldAssignedLabel !== $newAssignedLabel) {
                    $qb->table('ticket_changes')->insert([
                        'ticket_id' => $ticketId,
                        'user_id' => $editorId,
                        'field' => 'assigned_to',
                        'old_value' => $oldAssignedLabel,
                        'new_value' => $newAssignedLabel,
                    ]);
                }
 
                // Actualizar ticket
                $qb->table('tickets')
                    ->where('id', '=', $ticketId)
                    ->update([
                        'description' => $newDescription,
                        'status' => $newStatus,
                        'priority' => $newPriority,
                        'assigned_to' => $assignedTo,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
 
                // Auditoría: actualizar ticket
                $changed = [];
                if ($oldStatus !== $newStatus) $changed[] = 'estado';
                if ($oldPriority !== $newPriority) $changed[] = 'prioridad';
                if ($oldAssignedLabel !== $newAssignedLabel) $changed[] = 'asignación';
                $details = $changed ? ('Cambios: ' . implode(', ', $changed)) : 'Sin cambios críticos';
                audit_log($qb, $editorId > 0 ? $editorId : null, 'ticket.update', 'tickets', $ticketId, $details);
 
                setFlash('success', 'Ticket actualizado correctamente.');
                header('Location: ?page=ticket&id=' . $ticketId);
                exit;
            }
        }
 
        // Recargar ticket (JOIN -> prepare)
        $stmt = $db->prepare("
            SELECT 
                t.*,
                u_assign.username AS assigned_username,
                u_create.username AS created_username
            FROM tickets t
            LEFT JOIN users u_assign ON t.assigned_to = u_assign.id
            LEFT JOIN users u_create ON t.created_by = u_create.id
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
 
        // Recargar historial de cambios (JOIN -> prepare)
        $stmt = $db->prepare("
            SELECT
                ch.*,
                u.username AS username
            FROM ticket_changes ch
            LEFT JOIN users u ON ch.user_id = u.id
            WHERE ch.ticket_id = :ticket_id
            ORDER BY ch.created_at DESC
        ");
        $stmt->execute([':ticket_id' => $ticketId]);
        $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
        $title = 'Ticket #' . $ticketId . ' - SecureDesk DAM';
 
        ob_start();
        require __DIR__ . '/../views/ticket_detail_view.php';
        $content = ob_get_clean();
        break;
 
    case 'ticket_comment_add':
        requireRole(['admin', 'tecnico']);
 
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?page=tickets');
            exit;
        }
 
        // CSRF
        if (!csrf_validate($_POST['csrf_token'] ?? '')) {
            setFlash('danger', 'Token CSRF inválido. Recarga la página e inténtalo de nuevo.');
            $tid = isset($_POST['ticket_id']) && ctype_digit((string)$_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
            header('Location: ' . ($tid > 0 ? ('?page=ticket&id=' . $tid) : '?page=tickets'));
            exit;
        }

        $ticketIdRaw = $_POST['ticket_id'] ?? '';
        if (!ctype_digit((string)$ticketIdRaw)) {
            setFlash('danger', 'Ticket no válido.');
            header('Location: ?page=tickets');
            exit;
        }
        $ticketId = (int)$ticketIdRaw;
 
        if (!$qb->table('tickets')->find($ticketId)) {
            setFlash('danger', 'El ticket no existe.');
            header('Location: ?page=tickets');
            exit;
        }
 
        $comment = trim((string)($_POST['comment'] ?? ''));
        if ($comment === '') {
            setFlash('danger', 'El comentario no puede estar vacío.');
            header('Location: ?page=ticket&id=' . $ticketId);
            exit;
        }
 
        if (mb_strlen($comment) > 2000) {
            setFlash('danger', 'El comentario es demasiado largo (máx. 2000 caracteres).');
            header('Location: ?page=ticket&id=' . $ticketId);
            exit;
        }
 
        $user = currentUser();
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            setFlash('danger', 'No se pudo identificar el usuario autenticado.');
            header('Location: ?page=login');
            exit;
        }
 
        $newCommentId = $qb->table('ticket_comments')->insert([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'comment' => $comment,
        ]);
 
        // Auditoría: añadir comentario
        audit_log($qb, $userId, 'comment.create', 'tickets', $ticketId, 'Añadido comentario');
 
        setFlash('success', 'Comentario añadido correctamente.');
        header('Location: ?page=ticket&id=' . $ticketId);
        exit;
 
    case 'attachment_upload':
        requireRole(['admin', 'tecnico']);
 
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?page=tickets');
            exit;
        }
 
        if (!csrf_validate($_POST['csrf_token'] ?? '')) {
            setFlash('danger', 'Token CSRF inválido. Recarga la página e inténtalo de nuevo.');
            $tid = isset($_POST['ticket_id']) && ctype_digit((string)$_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
            header('Location: ' . ($tid > 0 ? ('?page=ticket&id=' . $tid) : '?page=tickets'));
            exit;
        }

        $ticketIdRaw = $_POST['ticket_id'] ?? '';
        if (!ctype_digit((string)$ticketIdRaw)) {
            setFlash('danger', 'Ticket no válido.');
            header('Location: ?page=tickets');
            exit;
        }
        $ticketId = (int)$ticketIdRaw;
 
        if (!$qb->table('tickets')->find($ticketId)) {
            setFlash('danger', 'El ticket no existe.');
            header('Location: ?page=tickets');
            exit;
        }
 
        if (!isset($_FILES['attachment'])) {
            setFlash('danger', 'No se recibió ningún archivo.');
            header('Location: ?page=ticket&id=' . $ticketId);
            exit;
        }
 
        $file = $_FILES['attachment'];
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            $map = [
                UPLOAD_ERR_INI_SIZE => 'El archivo supera el tamaño permitido por el servidor.',
                UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño permitido por el formulario.',
                UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente.',
                UPLOAD_ERR_NO_FILE => 'Selecciona un archivo para subir.',
            ];
            setFlash('danger', $map[$err] ?? 'Error subiendo el archivo.');
            header('Location: ?page=ticket&id=' . $ticketId);
            exit;
        }
 
        $originalName = (string)($file['name'] ?? 'archivo');
        $sizeBytes = (int)($file['size'] ?? 0);
 
        $maxBytes = 10 * 1024 * 1024;
        if ($sizeBytes <= 0 || $sizeBytes > $maxBytes) {
            setFlash('danger', 'El archivo es demasiado grande (máx. 10MB).');
            header('Location: ?page=ticket&id=' . $ticketId);
            exit;
        }
 
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $ext = $ext ? ('.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext)) : '';
        $storedName = bin2hex(random_bytes(16)) . '_' . time() . $ext;
        $destPath = $uploadsDir . DIRECTORY_SEPARATOR . $storedName;
 
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            setFlash('danger', 'No se pudo guardar el archivo en el servidor.');
            header('Location: ?page=ticket&id=' . $ticketId);
            exit;
        }
 
        $user = currentUser();
        $uploadedBy = (int)($user['id'] ?? 0);
 
        $newAttachmentId = $qb->table('ticket_attachments')->insert([
            'ticket_id' => $ticketId,
            'stored_name' => $storedName,
            'original_name' => $originalName,
            'size_bytes' => $sizeBytes,
            'uploaded_by' => $uploadedBy,
        ]);
 
        // Auditoría: subir adjunto
        audit_log($qb, $uploadedBy > 0 ? $uploadedBy : null, 'attachment.upload', 'tickets', $ticketId, 'Archivo: ' . $originalName);
 
        setFlash('success', 'Adjunto subido correctamente.');
        header('Location: ?page=ticket&id=' . $ticketId);
        exit;
 
    case 'attachment_download':
        requireAuth();
 
        $attIdRaw = $_GET['id'] ?? '';
        if (!ctype_digit((string)$attIdRaw)) {
            setFlash('danger', 'Adjunto no válido.');
            header('Location: ?page=tickets');
            exit;
        }
        $attId = (int)$attIdRaw;
 
        // JOIN -> prepare()
        $stmt = $db->prepare("
            SELECT a.*, t.id AS ticket_id
            FROM ticket_attachments a
            INNER JOIN tickets t ON a.ticket_id = t.id
            WHERE a.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $attId]);
        $att = $stmt->fetch(PDO::FETCH_ASSOC);
 
        if (!$att) {
            setFlash('danger', 'El adjunto no existe.');
            header('Location: ?page=tickets');
            exit;
        }
 
        $storedName = basename((string)($att['stored_name'] ?? ''));
        $originalName = (string)($att['original_name'] ?? 'archivo');
        $path = $uploadsDir . DIRECTORY_SEPARATOR . $storedName;
 
        if ($storedName === '' || !is_file($path)) {
            setFlash('danger', 'Archivo no encontrado en el servidor.');
            header('Location: ?page=ticket&id=' . (int)$att['ticket_id']);
            exit;
        }
 
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $originalName) . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
 
        readfile($path);
        exit;
 
    case 'audit':
        requireAdmin();

        $title = 'Auditoría - SecureDesk DAM';

        // Filtros básicos
        $filterUserId = null;
        $filterAction = null;

        $userIdRaw = $_GET['user_id'] ?? '';
        if ($userIdRaw !== '' && ctype_digit((string)$userIdRaw)) {
            $filterUserId = (int)$userIdRaw;
        }

        $actionRaw = trim((string)($_GET['action'] ?? ''));
        if ($actionRaw !== '') {
            $filterAction = mb_substr($actionRaw, 0, 120);
        }

        // Usuarios para filtro (QueryBuilder)
        $users = $qb->table('users')
            ->select(['id', 'username'])
            ->orderBy('username', 'ASC')
            ->get();

        // Acciones distintas para filtro
        $stmt = $db->prepare("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC");
        $stmt->execute();
        $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Logs (QueryBuilder)
        $q = $qb->table('audit_logs')
            ->select(['id','created_at','user_id','action','entity','entity_id','details'])
            ->orderBy('created_at', 'DESC');

        if ($filterUserId !== null) {
            $q->where('user_id', '=', $filterUserId);
        }
        if ($filterAction !== null) {
            $q->where('action', '=', $filterAction);
        }

        // Limitar salida
        $logs = $q->limit(300)->get();

        // Enriquecer con username sin JOIN (manteniendo QueryBuilder)
        $userIds = [];
        foreach ($logs as $r) {
            if (isset($r['user_id']) && $r['user_id'] !== null && $r['user_id'] !== '') {
                $uid = (int)$r['user_id'];
                if ($uid > 0) $userIds[$uid] = true;
            }
        }

        $userMap = [];
        if (!empty($userIds)) {
            $rows = $qb->table('users')
                ->select(['id','username'])
                ->whereIn('id', array_keys($userIds))
                ->get();

            foreach ($rows as $u) {
                $userMap[(int)$u['id']] = (string)$u['username'];
            }
        }

        foreach ($logs as &$r) {
            $uid = isset($r['user_id']) ? (int)$r['user_id'] : 0;
            $r['username'] = $uid > 0 ? ($userMap[$uid] ?? 'Usuario eliminado') : '—';
        }
        unset($r);

        ob_start();
        require __DIR__ . '/../views/audit_view.php';
        $content = ob_get_clean();
        break;

    case 'export':
        requireRole(['admin', 'tecnico']);

        $format = strtolower(trim((string)($_GET['format'] ?? 'csv')));
        if (!in_array($format, ['csv','html','pdf'], true)) {
            setFlash('danger', 'Formato de exportación no soportado.');
            header('Location: ?page=tickets');
            exit;
        }

        // Reutilizamos los mismos filtros que en listado
        $statusFilter = $_GET['status'] ?? null;
        $priorityFilter = $_GET['priority'] ?? null;

        $allowedStatus = ['nuevo','en_proceso','resuelto'];
        $allowedPriority = ['baja','media','alta','critica'];

        $conditions = [];
        $params = [];

        if ($statusFilter && in_array($statusFilter, $allowedStatus, true)) {
            $conditions[] = 't.status = :status';
            $params[':status'] = $statusFilter;
        }

        if ($priorityFilter && in_array($priorityFilter, $allowedPriority, true)) {
            $conditions[] = 't.priority = :priority';
            $params[':priority'] = $priorityFilter;
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        // Obtener tickets (igual que en tickets)
        $stmt = $db->prepare("
            SELECT 
                t.*,
                u_assign.username AS assigned_username,
                u_create.username AS created_username
            FROM tickets t
            LEFT JOIN users u_assign ON t.assigned_to = u_assign.id
            LEFT JOIN users u_create ON t.created_by = u_create.id
            $where
            ORDER BY t.created_at DESC
        ");
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Auditoría: registrar exportación
        $u = currentUser();
        $uid = is_array($u) ? (int)($u['id'] ?? 0) : 0;
        $actionMap = [
            'csv' => 'report.export_csv',
            'html' => 'report.export_html',
            'pdf' => 'report.export_pdf',
        ];
        $action = $actionMap[$format];
        $details = 'Exportación de tickets; formato=' . strtoupper($format);
        if (!empty($statusFilter)) $details .= '; status=' . $statusFilter;
        if (!empty($priorityFilter)) $details .= '; priority=' . $priorityFilter;

        audit_log($qb, $uid > 0 ? $uid : null, $action, 'reports', null, $details);

        // Generar respuesta según formato
        if ($format === 'csv') {
            // CSV headers
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="tickets_export_' . date('Ymd_His') . '.csv"');

            $out = fopen('php://output', 'w');

            // Encabezado
            fputcsv($out, ['ID','Título','Descripción','Estado','Prioridad','Creado por','Asignado a','Creado en','Actualizado en']);

            foreach ($tickets as $t) {
                fputcsv($out, [
                    (int)$t['id'],
                    $t['title'] ?? '',
                    $t['description'] ?? '',
                    $t['status'] ?? '',
                    $t['priority'] ?? '',
                    $t['created_username'] ?? '',
                    $t['assigned_username'] ?? '',
                    $t['created_at'] ?? '',
                    $t['updated_at'] ?? '',
                ]);
            }

            fclose($out);
            exit;
        }

        // fallback
        setFlash('danger', 'Error en la exportación.');
        header('Location: ?page=tickets');
        exit;
    
    case 'export_ticket':
        // Exporta el detalle de un único ticket (CSV / HTML / PDF)
        requireRole(['admin', 'tecnico']);

        // --- Validar id y formato ---
        $idRaw = $_GET['id'] ?? '';
        if (!ctype_digit((string)$idRaw)) {
            setFlash('danger', 'Ticket no válido.');
            header('Location: ?page=tickets');
            exit;
        }
        $ticketId = (int)$idRaw;

        $format = strtolower(trim((string)($_GET['format'] ?? 'csv')));
        if (!in_array($format, ['csv','html','pdf'], true)) {
            setFlash('danger', 'Formato de exportación no soportado.');
            header('Location: ?page=ticket&id=' . $ticketId);
            exit;
        }

        // --- Cargar ticket y relaciones ---
        $stmt = $db->prepare("
            SELECT 
                t.*,
                u_assign.username AS assigned_username,
                u_create.username AS created_username
            FROM tickets t
            LEFT JOIN users u_assign ON t.assigned_to = u_assign.id
            LEFT JOIN users u_create ON t.created_by = u_create.id
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            setFlash('danger', 'El ticket no existe.');
            header('Location: ?page=tickets');
            exit;
        }

        // Adjuntos
        $stmt = $db->prepare("
            SELECT a.*, u.username AS uploaded_username
            FROM ticket_attachments a
            LEFT JOIN users u ON a.uploaded_by = u.id
            WHERE a.ticket_id = :ticket_id
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([':ticket_id' => $ticketId]);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Comentarios
        $stmt = $db->prepare("
            SELECT c.*, u.username AS username
            FROM ticket_comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.ticket_id = :ticket_id
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([':ticket_id' => $ticketId]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cambios
        $stmt = $db->prepare("
            SELECT ch.*, u.username AS username
            FROM ticket_changes ch
            LEFT JOIN users u ON ch.user_id = u.id
            WHERE ch.ticket_id = :ticket_id
            ORDER BY ch.created_at DESC
        ");
        $stmt->execute([':ticket_id' => $ticketId]);
        $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Auditoría
        $u = currentUser();
        $uid = is_array($u) ? (int)($u['id'] ?? 0) : 0;
        $actionMap = [
            'csv' => 'report.ticket_export_csv',
            'html' => 'report.ticket_export_html',
            'pdf' => 'report.ticket_export_pdf',
        ];
        $action = $actionMap[$format];
        $details = 'Exportación ticket; id=' . $ticketId . '; formato=' . strtoupper($format);
        audit_log($qb, $uid > 0 ? $uid : null, $action, 'tickets', $ticketId, $details);

        // --- Construir HTML completo (usado por HTML y PDF) ---
        // Escapar todo
        $generatedBy = htmlspecialchars($u['username'] ?? 'Sistema', ENT_QUOTES, 'UTF-8');
        $now = date('Y-m-d H:i:s');
        $t_id = (int)$ticket['id'];
        $t_title = htmlspecialchars($ticket['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $t_status = htmlspecialchars($ticket['status'] ?? '', ENT_QUOTES, 'UTF-8');
        $t_priority = htmlspecialchars($ticket['priority'] ?? '', ENT_QUOTES, 'UTF-8');
        $t_assigned = htmlspecialchars($ticket['assigned_username'] ?? '—', ENT_QUOTES, 'UTF-8');
        $t_created_by = htmlspecialchars($ticket['created_username'] ?? '—', ENT_QUOTES, 'UTF-8');
        $t_created_at = htmlspecialchars($ticket['created_at'] ?? '', ENT_QUOTES, 'UTF-8');
        $t_updated_at = htmlspecialchars($ticket['updated_at'] ?? '—', ENT_QUOTES, 'UTF-8');
        $t_description = nl2br(htmlspecialchars($ticket['description'] ?? '', ENT_QUOTES, 'UTF-8'));

        $html = <<<HTML
    <!doctype html>
    <html lang="es">
    <head>
    <meta charset="utf-8">
    <title>Informe Ticket #{$t_id}</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;margin:30px;color:#222}
        .header{border-bottom:2px solid #333;margin-bottom:20px;padding-bottom:10px}
        .brand{font-size:22px;font-weight:700}
        .meta{font-size:13px;color:#555;margin-top:6px}
        .section{margin-top:18px}
        .section h2{font-size:16px;border-bottom:1px solid #ddd;padding-bottom:6px}
        table{border-collapse:collapse;width:100%;margin-top:8px}
        th,td{border:1px solid #ddd;padding:6px;text-align:left;vertical-align:top;font-size:13px}
        th{background:#f4f4f4}
        .comment{border:1px solid #eee;padding:10px;margin-bottom:8px;background:#fafafa}
        .muted{color:#666;font-size:12px}
    </style>
    </head>
    <body>
    <div class="header">
        <div class="brand">SecureDesk DAM</div>
        <div class="meta">
        <strong>Informe de Ticket:</strong> #{$t_id} &nbsp;&nbsp;
        <strong>Generado por:</strong> {$generatedBy} &nbsp;&nbsp;
        <strong>Fecha:</strong> {$now}
        </div>
    </div>

    <div class="section">
        <h2>Datos del Ticket</h2>
        <table>
        <tr><th>ID</th><td>{$t_id}</td></tr>
        <tr><th>Título</th><td>{$t_title}</td></tr>
        <tr><th>Estado</th><td>{$t_status}</td></tr>
        <tr><th>Prioridad</th><td>{$t_priority}</td></tr>
        <tr><th>Asignado a</th><td>{$t_assigned}</td></tr>
        <tr><th>Creado por</th><td>{$t_created_by}</td></tr>
        <tr><th>Creado en</th><td>{$t_created_at}</td></tr>
        <tr><th>Última actualización</th><td>{$t_updated_at}</td></tr>
        </table>
    </div>

    <div class="section">
        <h2>Descripción</h2>
        <div>{$t_description}</div>
    </div>
    HTML;

        // Adjuntos
        $html .= '<div class="section"><h2>Adjuntos</h2>';
        if (empty($attachments)) {
            $html .= '<div class="muted">No hay adjuntos.</div>';
        } else {
            $html .= '<table><thead><tr><th>ID</th><th>Nombre</th><th>Subido por</th><th>Tamaño (bytes)</th><th>Fecha</th></tr></thead><tbody>';
            foreach ($attachments as $a) {
                $a_id = (int)($a['id'] ?? 0);
                $a_name = htmlspecialchars($a['original_name'] ?? '', ENT_QUOTES, 'UTF-8');
                $a_user = htmlspecialchars($a['uploaded_username'] ?? '', ENT_QUOTES, 'UTF-8');
                $a_size = (int)($a['size_bytes'] ?? 0);
                $a_date = htmlspecialchars($a['created_at'] ?? '', ENT_QUOTES, 'UTF-8');
                $html .= "<tr><td>{$a_id}</td><td>{$a_name}</td><td>{$a_user}</td><td>{$a_size}</td><td>{$a_date}</td></tr>";
            }
            $html .= '</tbody></table>';
        }
        $html .= '</div>';

        // Comentarios
        $html .= '<div class="section"><h2>Comentarios</h2>';
        if (empty($comments)) {
            $html .= '<div class="muted">No hay comentarios.</div>';
        } else {
            foreach ($comments as $c) {
                $c_id = (int)($c['id'] ?? 0);
                $c_user = htmlspecialchars($c['username'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
                $c_date = htmlspecialchars($c['created_at'] ?? '', ENT_QUOTES, 'UTF-8');
                $c_text = nl2br(htmlspecialchars($c['comment'] ?? '', ENT_QUOTES, 'UTF-8'));
                $html .= "<div class=\"comment\"><div style=\"font-weight:700\">{$c_user} <span class=\"muted\">({$c_date})</span></div><div style=\"margin-top:6px\">{$c_text}</div></div>";
            }
        }
        $html .= '</div>';

        // Historial
        $html .= '<div class="section"><h2>Historial de cambios</h2>';
        if (empty($changes)) {
            $html .= '<div class="muted">No hay cambios registrados.</div>';
        } else {
            $html .= '<table><thead><tr><th>Fecha</th><th>Usuario</th><th>Campo</th><th>Antes</th><th>Ahora</th></tr></thead><tbody>';
            foreach ($changes as $ch) {
                $ch_date = htmlspecialchars($ch['created_at'] ?? '', ENT_QUOTES, 'UTF-8');
                $ch_user = htmlspecialchars($ch['username'] ?? '', ENT_QUOTES, 'UTF-8');
                $ch_field = htmlspecialchars($ch['field'] ?? '', ENT_QUOTES, 'UTF-8');
                $ch_old = htmlspecialchars($ch['old_value'] ?? '', ENT_QUOTES, 'UTF-8');
                $ch_new = htmlspecialchars($ch['new_value'] ?? '', ENT_QUOTES, 'UTF-8');
                $html .= "<tr><td>{$ch_date}</td><td>{$ch_user}</td><td>{$ch_field}</td><td>{$ch_old}</td><td>{$ch_new}</td></tr>";
            }
            $html .= '</tbody></table>';
        }
        $html .= '</div>';

        $html .= '</body></html>';

        // Guardar temporal (útil para depuración)
        @file_put_contents(sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sdesk_ticket_{$t_id}.html", $html);

        // --- Output según formato ---
        if ($format === 'html') {
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: attachment; filename="ticket_' . $ticketId . '_export_' . date('Ymd_His') . '.html"');
            echo $html;
            exit;
        }

        if ($format === 'pdf') {
            // evitar salidas previas
            while (ob_get_level()) { ob_end_clean(); }

            if (!class_exists(\Dompdf\Dompdf::class)) {
                // fallback: devolver HTML para que el usuario pueda imprimir
                header('Content-Type: text/html; charset=utf-8');
                header('X-Notice: dompdf-not-installed');
                echo $html;
                exit;
            }

            try {
                $options = new \Dompdf\Options();
                $options->set('isRemoteEnabled', true);
                $options->set('isHtml5ParserEnabled', true);

                $dompdf = new \Dompdf\Dompdf($options);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->render();

                $pdfOutput = $dompdf->output();
                if ($pdfOutput === false || strlen($pdfOutput) === 0) {
                    header('Content-Type: text/html; charset=utf-8');
                    header('X-Notice: dompdf-empty-output');
                    echo $html;
                    exit;
                }

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="ticket_' . $ticketId . '_export_' . date('Ymd_His') . '.pdf"');
                header('Content-Length: ' . strlen($pdfOutput));
                echo $pdfOutput;
                exit;

            } catch (\Throwable $e) {
                header('Content-Type: text/html; charset=utf-8');
                header('X-Notice: dompdf-exception');
                header('X-Error-Message: ' . substr($e->getMessage(), 0, 200));
                echo "<h2>Error al generar PDF</h2>";
                echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
                echo $html;
                exit;
            }
        }

        // fallback safety (no debería llegar aquí)
        setFlash('danger', 'Error en la exportación del ticket.');
        header('Location: ?page=ticket&id=' . $ticketId);
        exit;

    break;

    case 'users':
        requireAdmin();
        $title = 'Usuarios - SecureDesk DAM';
        $content = '<h1>Gestión de usuarios</h1><p>Solo administradores.</p>';
        break;
 
    case 'account':
        requireAuth();
        $title = 'Mi cuenta - SecureDesk DAM';
        ob_start();
        require __DIR__ . '/../views/account_view.php';
        $content = ob_get_clean();
        break;
 
    default:
        header('Location: ?page=login');
        exit;
}
 
require __DIR__ . '/../views/layout_view.php';