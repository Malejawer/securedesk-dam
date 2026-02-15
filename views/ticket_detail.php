<?php
$user = currentUser();
$role = (string)($user['role'] ?? '');
$canEdit = in_array($role, ['admin', 'tecnico'], true);

$allowedStatus = ['nuevo','en_proceso','resuelto'];
$allowedPriority = ['baja','media','alta','critica'];
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">Ticket #<?= (int)$ticket['id'] ?></h1>
        <div class="text-muted">
            <?= htmlspecialchars($ticket['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

    <div class="d-flex gap-2">
        <a href="?page=tickets" class="btn btn-outline-secondary">Volver</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="mb-3">
                    <div class="text-muted small">Creado por</div>
                    <div class="fw-semibold"><?= htmlspecialchars($ticket['created_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Asignado</div>
                    <div class="fw-semibold"><?= htmlspecialchars($ticket['assigned_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Estado</div>
                    <div class="fw-semibold"><?= htmlspecialchars($ticket['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Prioridad</div>
                    <div class="fw-semibold"><?= htmlspecialchars($ticket['priority'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Creado el</div>
                    <div class="fw-semibold"><?= htmlspecialchars($ticket['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="mb-0">
                    <div class="text-muted small">Actualizado el</div>
                    <div class="fw-semibold"><?= htmlspecialchars($ticket['updated_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">

                <h2 class="h5 fw-bold mb-3">Detalle</h2>

                <?php if (!$canEdit): ?>
                    <div class="alert alert-info">
                        Este ticket es visible para tu rol, pero la edición está restringida a Admin y Técnico.
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        Revisa los campos marcados, hay datos no válidos.
                    </div>
                <?php endif; ?>

                <?php if ($canEdit): ?>
                    <form method="POST" action="?page=ticket&id=<?= (int)$ticket['id'] ?>" class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea name="description" class="form-control" rows="5"><?= htmlspecialchars($ticket['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <select name="status" class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>">
                                <?php foreach ($allowedStatus as $s): ?>
                                    <option value="<?= $s ?>" <?= (($ticket['status'] ?? '') === $s) ? 'selected' : '' ?>>
                                        <?= ucfirst(str_replace('_', ' ', $s)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['status'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['status'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Prioridad</label>
                            <select name="priority" class="form-select <?= isset($errors['priority']) ? 'is-invalid' : '' ?>">
                                <?php foreach ($allowedPriority as $p): ?>
                                    <option value="<?= $p ?>" <?= (($ticket['priority'] ?? '') === $p) ? 'selected' : '' ?>>
                                        <?= ucfirst($p) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['priority'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['priority'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Asignar a (Admin/Técnico)</label>
                            <select name="assigned_to" class="form-select <?= isset($errors['assigned_to']) ? 'is-invalid' : '' ?>">
                                <option value="" <?= empty($ticket['assigned_to']) ? 'selected' : '' ?>>Sin asignar</option>

                                <?php foreach ($assignees as $a): ?>
                                    <option
                                        value="<?= (int)$a['id'] ?>"
                                        <?= ((string)($ticket['assigned_to'] ?? '') === (string)$a['id']) ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($a['username'], ENT_QUOTES, 'UTF-8') ?>
                                        (<?= htmlspecialchars($a['role'], ENT_QUOTES, 'UTF-8') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['assigned_to'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['assigned_to'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 d-flex gap-2 mt-2">
                            <button class="btn btn-primary">Guardar cambios</button>
                            <a href="?page=ticket&id=<?= (int)$ticket['id'] ?>" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="p-3 bg-light border rounded-3">
                        <?= nl2br(htmlspecialchars($ticket['description'] ?? '', ENT_QUOTES, 'UTF-8')) ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>