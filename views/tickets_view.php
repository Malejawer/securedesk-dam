<?php
$user = function_exists('currentUser') ? currentUser() : null;
$canCreate = userCan($user, 'tickets.create');
$canExport = userCan($user, 'reports.export');
$searchValue = (string)($_GET['q'] ?? '');
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="mb-1">Listado de tickets</h1>
        <div class="text-muted">Filtra por estado, prioridad, asignación y búsqueda</div>
    </div>

    <div class="d-flex gap-2">
        <?php
        $filters = [];
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (!empty($_GET['priority'])) $filters['priority'] = $_GET['priority'];
        if (!empty($_GET['assigned'])) $filters['assigned'] = $_GET['assigned'];
        if (!empty($_GET['q'])) $filters['q'] = $_GET['q'];

        $qs = function(array $extra = []) use ($filters) {
            $params = array_merge($filters, $extra);
            if (empty($params)) return '';
            return '&' . http_build_query($params);
        };

        $csvUrl  = '?page=export&format=csv'  . $qs();
        $htmlUrl = '?page=export&format=html' . $qs();
        ?>

        <?php if ($canCreate): ?>
            <a href="?page=ticket_new" class="btn btn-primary">+ Nuevo ticket</a>
        <?php endif; ?>

        <?php if ($canExport): ?>
            <div class="btn-group" role="group" aria-label="Exportar">
                <a href="<?= htmlspecialchars($csvUrl, ENT_QUOTES, 'UTF-8') ?>"
                   class="btn btn-outline-secondary">
                    Exportar CSV
                </a>

                <a href="<?= htmlspecialchars($htmlUrl, ENT_QUOTES, 'UTF-8') ?>"
                   class="btn btn-outline-secondary"
                   title="Exportar HTML">
                    Exportar HTML
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<form method="GET" class="row g-3 mb-4">
    <input type="hidden" name="page" value="tickets">

    <div class="col-md-3">
        <label class="form-label">Buscar</label>
        <input
            type="text"
            name="q"
            class="form-control"
            placeholder="Título o descripción"
            value="<?= htmlspecialchars($searchValue, ENT_QUOTES, 'UTF-8') ?>"
        >
    </div>

    <div class="col-md-3">
        <label class="form-label">Estado</label>
        <select name="status" class="form-select">
            <option value="">Todos</option>
            <?php foreach (['nuevo','en_proceso','resuelto'] as $s): ?>
                <option value="<?= $s ?>" <?= (($_GET['status'] ?? '') === $s) ? 'selected' : '' ?>>
                    <?= ucfirst(str_replace('_',' ',$s)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-2">
        <label class="form-label">Prioridad</label>
        <select name="priority" class="form-select">
            <option value="">Todas</option>
            <?php foreach (['baja','media','alta','critica'] as $p): ?>
                <option value="<?= $p ?>" <?= (($_GET['priority'] ?? '') === $p) ? 'selected' : '' ?>>
                    <?= ucfirst($p) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-2">
        <label class="form-label">Asignación</label>
        <select name="assigned" class="form-select">
            <option value="">Todas</option>
            <option value="unassigned" <?= (($_GET['assigned'] ?? '') === 'unassigned') ? 'selected' : '' ?>>
                Sin asignar
            </option>
        </select>
    </div>

    <div class="col-md-1 align-self-end">
        <button class="btn btn-primary w-100">Filtrar</button>
    </div>

    <div class="col-md-1 align-self-end">
        <a class="btn btn-outline-secondary w-100" href="?page=tickets">Limpiar</a>
    </div>
</form>

<div class="d-flex gap-2 flex-wrap mb-4">
    <a href="?page=tickets&priority=critica" class="btn btn-outline-danger btn-sm">
        Vista rápida: Críticos
    </a>
    <a href="?page=tickets&assigned=unassigned" class="btn btn-outline-warning btn-sm">
        Vista rápida: Sin asignar
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Título</th>
                    <th>Estado</th>
                    <th>Prioridad</th>
                    <th>Creado por</th>
                    <th>Asignado</th>
                    <th class="text-center">Comentarios</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No hay tickets disponibles con esos filtros</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td>
                                <a class="text-decoration-none fw-semibold" href="?page=ticket&id=<?= (int)$t['id'] ?>">
                                    <?= htmlspecialchars($t['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?= htmlspecialchars($t['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $priority = $t['priority'] ?? '';

                                $priorityClasses = [
                                    'baja'    => 'bg-success',
                                    'media'   => 'bg-primary',
                                    'alta'    => 'bg-warning text-dark',
                                    'critica' => 'bg-danger',
                                ];

                                $class = $priorityClasses[$priority] ?? 'bg-secondary';
                                ?>
                                <span class="badge <?= $class ?>">
                                    <?= htmlspecialchars(ucfirst($priority), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($t['created_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($t['assigned_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">
                                    <?= (int)($t['comments_count'] ?? 0) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($t['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>