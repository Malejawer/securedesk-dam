<?php
// Variables: $errors, $old, $assignees
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">Nuevo ticket</h1>
        <div class="text-muted">Crea una incidencia nueva</div>
    </div>
    <a href="?page=tickets" class="btn btn-outline-secondary">Volver</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors['attachment'])): ?>
            <div class="alert alert-warning">
                <?= htmlspecialchars($errors['attachment'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="?page=ticket_new" enctype="multipart/form-data" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-12">
                <label class="form-label">Título <span class="text-danger">*</span></label>
                <input
                    type="text"
                    name="title"
                    class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                    value="<?= htmlspecialchars($old['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
                <?php if (isset($errors['title'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['title'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label">Descripción</label>
                <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($old['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="col-md-6">
                <label class="form-label">Categoría</label>
                <input
                    type="text"
                    name="category"
                    class="form-control"
                    placeholder="Ej: Hardware, Software, Acceso..."
                    value="<?= htmlspecialchars($old['category'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                >
            </div>

            <div class="col-md-3">
                <label class="form-label">Prioridad</label>
                <select name="priority" class="form-select">
                    <?php foreach (['baja','media','alta','critica'] as $p): ?>
                        <option value="<?= $p ?>" <?= (($old['priority'] ?? 'media') === $p) ? 'selected' : '' ?>>
                            <?= ucfirst($p) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Asignar a</label>
                <select name="assigned_to" class="form-select <?= isset($errors['assigned_to']) ? 'is-invalid' : '' ?>">
                    <option value="" <?= (($old['assigned_to'] ?? '') === '') ? 'selected' : '' ?>>Sin asignar</option>

                    <?php foreach ($assignees as $a): ?>
                        <option
                            value="<?= (int)$a['id'] ?>"
                            <?= (($old['assigned_to'] ?? '') === (string)$a['id']) ? 'selected' : '' ?>
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

            <div class="col-12">
                <label class="form-label">Adjuntar evidencia (opcional)</label>
                <input type="file" name="attachment" class="form-control">
                <div class="form-text">Máx. 10MB. Solo se guarda si el ticket se crea correctamente.</div>
            </div>

            <div class="col-12 d-flex gap-2 mt-2">
                <button class="btn btn-primary">Crear ticket</button>
                <a href="?page=tickets" class="btn btn-outline-secondary">Cancelar</a>
            </div>

            <div class="text-muted small mt-3">
                * El estado inicial se guarda como <strong>nuevo</strong>.
            </div>

        </form>
    </div>
</div>