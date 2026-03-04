<?php
declare(strict_types=1);
 
session_start();
 
require_once __DIR__ . '/../app/database/Database.php';
require_once __DIR__ . '/../app/services/HealthCheckService.php';
require_once __DIR__ . '/../app/database/QueryBuilder.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/audit.php';
 
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
        require __DIR__ . '/../views/login.php';
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
        ob_start();
        require __DIR__ . '/../views/home.php';
        $content = ob_get_clean();
        break;
 
    case 'tickets':
        requireRole(['admin', 'tecnico', 'lector']);
 
        $title = 'Tickets - SecureDesk DAM';
 
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
 
        // JOIN + subquery -> prepare()
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
        require __DIR__ . '/../views/tickets.php';
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
 
                setFlash('success', 'Ticket creado correctamente.');
                header('Location: ?page=tickets');
                exit;
            }
        }
 
        ob_start();
        require __DIR__ . '/../views/ticket_new.php';
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
        require __DIR__ . '/../views/ticket_detail.php';
        $content = ob_get_clean();
        break;
 
    case 'ticket_comment_add':
        requireRole(['admin', 'tecnico']);
 
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?page=tickets');
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
 
    case 'users':
        requireAdmin();
        $title = 'Usuarios - SecureDesk DAM';
        $content = '<h1>Gestión de usuarios</h1><p>Solo administradores.</p>';
        break;
 
    case 'account':
        requireAuth();
        $title = 'Mi cuenta - SecureDesk DAM';
        ob_start();
        require __DIR__ . '/../views/account.php';
        $content = ob_get_clean();
        break;
 
    default:
        header('Location: ?page=login');
        exit;
}
 
require __DIR__ . '/../views/layout.php';