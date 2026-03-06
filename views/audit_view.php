<?php
// Variables esperadas:
// $logs, $users, $actions, $filterUserId, $filterAction
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="mb-1">Auditoría</h1>
        <div class="text-muted">Consulta los logs de auditoría del sistema</div>
    </div>
</div>

<form method="GET" class="row g-3 mb-4">
    <input type="hidden" name="page" value="audit">

    <div class="col-md-4">
        <label class="form-label">Usuario</label>
        <select name="user_id" class="form-select">
            <option value="">Todos</option>
            <?php foreach ($users as $u): ?>
                <?php
                    $uid = (int)($u['id'] ?? 0);
                    $uname = (string)($u['username'] ?? '');
                    $selected = ($filterUserId !== null && $filterUserId === $uid) ? 'selected' : '';
                ?>
                <option value="<?= $uid ?>" <?= $selected ?>>
                    <?= htmlspecialchars($uname, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">Acción</label>
        <select name="action" class="form-select">
            <option value="">Todas</option>
            <?php foreach ($actions as $a): ?>
                <?php
                    $action = (string)($a['action'] ?? '');
                    $selected = ($filterAction !== null && $filterAction === $action) ? 'selected' : '';
                ?>
                <option value="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                    <?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-2 align-self-end">
        <button class="btn btn-primary w-100">Filtrar</button>
    </div>

    <div class="col-md-2 align-self-end">
        <a class="btn btn-outline-secondary w-100" href="?page=audit">Limpiar</a>
    </div>
</form>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 180px;">Fecha</th>
                        <th style="width: 160px;">Usuario</th>
                        <th style="width: 200px;">Acción</th>
                        <th style="width: 140px;">Entidad</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                No hay registros con esos filtros.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $row): ?>
                            <?php
                                $date = (string)($row['created_at'] ?? '');
                                $username = (string)($row['username'] ?? '—');
                                $action = (string)($row['action'] ?? '');
                                $entity = (string)($row['entity'] ?? '—');
                                $entityId = $row['entity_id'] ?? null;
                                $details = (string)($row['details'] ?? '');
                                $entityLabel = $entity;

                                if ($entityId !== null && $entityId !== '') {
                                    $entityLabel .= ' #' . (int)$entityId;
                                }
                            ?>
                            <tr>
                                <td class="text-muted small">
                                    <?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <span class="fw-semibold">
                                        <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-dark">
                                        <?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="text-muted">
                                    <?= htmlspecialchars($entityLabel, ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <?= nl2br(htmlspecialchars($details !== '' ? $details : '—', ENT_QUOTES, 'UTF-8')) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>