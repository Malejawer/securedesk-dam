<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../app/database/Database.php';
require_once __DIR__ . '/../app/services/HealthCheckService.php';
require_once __DIR__ . '/../app/database/QueryBuilder.php';
require_once __DIR__ . '/../app/helpers/auth.php';

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

switch ($page) {

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

        ob_start();
        require __DIR__ . '/../views/tickets.php';
        $content = ob_get_clean();
        break;

    case 'ticket_new':
        // Solo Admin y Técnico pueden crear
        requireRole(['admin', 'tecnico']);

        $title = 'Nuevo ticket - SecureDesk DAM';

        $user = currentUser();
        $currentUserId = (int)($user['id'] ?? 0);

        // Asignables: Admin + Técnico
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
            'assigned_to' => '', // Por defecto: sin asignar
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $old['title'] = trim($_POST['title'] ?? '');
            $old['description'] = trim($_POST['description'] ?? '');
            $old['category'] = trim($_POST['category'] ?? '');
            $old['priority'] = $_POST['priority'] ?? 'media';
            $old['assigned_to'] = $_POST['assigned_to'] ?? '';

            // Validación mínima: título obligatorio
            if ($old['title'] === '') {
                $errors['title'] = 'El título es obligatorio.';
            }

            // Validar prioridad
            $allowedPriority = ['baja','media','alta','critica'];
            if (!in_array($old['priority'], $allowedPriority, true)) {
                $old['priority'] = 'media';
            }

            // Resolver assigned_to ('' => NULL, id => int)
            $assignedTo = null;
            if ($old['assigned_to'] !== '') {
                if (!ctype_digit($old['assigned_to'])) {
                    $errors['assigned_to'] = 'Asignación no válida.';
                } else {
                    $assignedTo = (int)$old['assigned_to'];

                    // Validar que el usuario asignado sea admin o técnico
                    $check = $db->prepare("SELECT id FROM users WHERE id = :id AND role IN ('admin','tecnico') LIMIT 1");
                    $check->execute([':id' => $assignedTo]);
                    if (!$check->fetch()) {
                        $errors['assigned_to'] = 'Solo se puede asignar a Admin o Técnico.';
                    }
                }
            }

            $createdBy = $currentUserId;
            if ($createdBy <= 0) {
                $errors['general'] = 'No se pudo identificar el usuario autenticado.';
            }

            if (!$errors) {
                $qb->table('tickets')->insert([
                    'title' => $old['title'],
                    'description' => $old['description'] !== '' ? $old['description'] : '',
                    'category' => $old['category'] !== '' ? $old['category'] : null,
                    'status' => 'nuevo',
                    'priority' => $old['priority'],
                    'created_by' => $createdBy,
                    'assigned_to' => $assignedTo,
                    'updated_at' => null,
                ]);

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
        // Detalle visible para todos
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

        // Cargar ticket (incluye creador y asignado)
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

        // Asignables: Admin + Técnico
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

            // assigned_to: '' => NULL, o id de admin/técnico
            $assignedTo = null;
            if ($assignedRaw !== '') {
                if (!ctype_digit($assignedRaw)) {
                    $errors['assigned_to'] = 'Asignación no válida.';
                } else {
                    $assignedTo = (int)$assignedRaw;

                    // Validar rol permitido
                    $check = $db->prepare("SELECT id FROM users WHERE id = :id AND role IN ('admin','tecnico') LIMIT 1");
                    $check->execute([':id' => $assignedTo]);
                    if (!$check->fetch()) {
                        $errors['assigned_to'] = 'Solo se puede asignar a Admin o Técnico.';
                    }
                }
            }

            if (!$errors) {
                $now = date('Y-m-d H:i:s');

                $upd = $db->prepare("
                    UPDATE tickets
                    SET
                        description = :description,
                        status = :status,
                        priority = :priority,
                        assigned_to = :assigned_to,
                        updated_at = :updated_at
                    WHERE id = :id
                ");

                $upd->execute([
                    ':description' => $newDescription,
                    ':status' => $newStatus,
                    ':priority' => $newPriority,
                    ':assigned_to' => $assignedTo,
                    ':updated_at' => $now,
                    ':id' => $ticketId,
                ]);

                setFlash('success', 'Ticket actualizado correctamente.');
                header('Location: ?page=ticket&id=' . $ticketId);
                exit;
            }
        }

        // Recargar ticket
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

        $title = 'Ticket #' . $ticketId . ' - SecureDesk DAM';

        ob_start();
        require __DIR__ . '/../views/ticket_detail.php';
        $content = ob_get_clean();
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
        require __DIR__ . '/../views/account.php';
        $content = ob_get_clean();
        break;

    default:
        header('Location: ?page=login');
        exit;
}

require __DIR__ . '/../views/layout.php';