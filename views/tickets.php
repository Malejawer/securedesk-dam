<?php
$user = function_exists('currentUser') ? currentUser() : null;
$role = is_array($user) ? ($user['role'] ?? null) : null;
$canCreate = in_array($role, ['admin', 'tecnico'], true);
?>
 
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="mb-1">Listado de tickets</h1>
        <div class="text-muted">Filtra por estado y prioridad</div>
    </div>
 
    <?php if ($canCreate): ?>
        <a href="?page=ticket_new" class="btn btn-primary">+ Nuevo ticket</a>
    <?php endif; ?>
</div>
 
<form method="GET" class="row g-3 mb-4">
    <input type="hidden" name="page" value="tickets">
 
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
 
    <div class="col-md-3">
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
 
    <div class="col-md-2 align-self-end">
        <button class="btn btn-primary w-100">Filtrar</button>
    </div>
 
    <div class="col-md-2 align-self-end">
        <a class="btn btn-outline-secondary w-100" href="?page=tickets">Limpiar</a>
    </div>
</form>
 
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
                <?php if (!$tickets): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No hay tickets disponibles</td>
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