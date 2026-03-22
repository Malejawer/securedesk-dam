<?php
$user = currentUser();
$role = (string)($user['role'] ?? '');
$canEdit = in_array($role, ['admin', 'tecnico'], true);
$canExport = in_array($role, ['admin', 'tecnico'], true);

$allowedStatus = ['nuevo','en_proceso','resuelto'];
$allowedPriority = ['baja','media','alta','critica'];

function formatBytes(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    $kb = $bytes / 1024;
    if ($kb < 1024) return number_format($kb, 1) . ' KB';
    $mb = $kb / 1024;
    if ($mb < 1024) return number_format($mb, 1) . ' MB';
    $gb = $mb / 1024;
    return number_format($gb, 1) . ' GB';
}

$attachments = $attachments ?? [];
$comments = $comments ?? [];

$formatValue = function (string $value): string {
    $value = str_replace('_', ' ', $value);
    return ucfirst($value);
};

$ticketStatusRaw = (string)($ticket['status'] ?? '');
$ticketPriorityRaw = (string)($ticket['priority'] ?? '');
$statusLabel = $formatValue($ticketStatusRaw);
$priorityLabel = $formatValue($ticketPriorityRaw);

// URLs de exportación para este ticket
$baseQs = function(array $extra = []) use ($ticket) {
    $params = array_merge(['id' => (int)$ticket['id']], $extra);
    return '?' . http_build_query(array_merge(['page' => 'export_ticket'], $params));
};
$csvUrl  = $baseQs(['format' => 'csv']);
$htmlUrl = $baseQs(['format' => 'html']);
$pdfUrl  = $baseQs(['format' => 'pdf']);
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">Ticket #<?= (int)$ticket['id'] ?></h1>
        <div class="text-muted">
            <?= htmlspecialchars($ticket['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

    <div class="d-flex gap-2 align-items-center">
        <a href="?page=tickets" class="btn btn-outline-secondary">Volver</a>

        <!-- Botones de exportación del ticket -->
        <?php if ($canExport): ?>
        <!-- Botones de exportación del ticket -->
        <div class="btn-group" role="group" aria-label="Exportar ticket">
            <a href="<?= htmlspecialchars($htmlUrl, ENT_QUOTES, 'UTF-8') ?>"
            class="btn btn-outline-secondary"
            title="Exportar ticket a HTML">
                Exportar HTML
            </a>

            <a href="<?= htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') ?>"
            class="btn btn-outline-secondary"
            title="Exportar ticket a PDF">
                Exportar PDF
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Información + Edición -->
<div class="row g-4">
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">

                <div class="mb-3">
                    <div class="text-muted small">Creado por</div>
                    <div class="fw-semibold"><?= htmlspecialchars($ticket['created_username'] ?? '—') ?></div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Asignado</div>
                    <div class="fw-semibold"><?= htmlspecialchars($ticket['assigned_username'] ?? '—') ?></div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Estado</div>
                    <div class="fw-semibold"><?= htmlspecialchars($statusLabel ?? '') ?></div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Prioridad</div>
                    <div class="fw-semibold"><?= htmlspecialchars($priorityLabel ?? '') ?></div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Creado el</div>
                    <div class="fw-semibold"><?= htmlspecialchars($ticket['created_at'] ?? '') ?></div>
                </div>

                <div>
                    <div class="text-muted small">Actualizado el</div>
                    <div class="fw-semibold"><?= htmlspecialchars($ticket['updated_at'] ?? '—') ?></div>
                </div>

            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">

                <h2 class="h5 fw-bold mb-3">Detalle</h2>

                <?php if ($canEdit): ?>
                    <form method="POST" action="?page=ticket&id=<?= (int)$ticket['id'] ?>" class="row g-3">
                        <?= csrf_field() ?>

                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea name="description" class="form-control" rows="5"><?= htmlspecialchars($ticket['description'] ?? '') ?></textarea>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <select name="status" class="form-select" required>
                                <?php foreach ($allowedStatus as $s): ?>
                                    <option value="<?= $s ?>" <?= ($ticketStatusRaw === $s) ? 'selected' : '' ?>>
                                        <?= ucfirst(str_replace('_',' ',$s)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Prioridad</label>
                            <select name="priority" class="form-select" required>
                                <?php foreach ($allowedPriority as $p): ?>
                                    <option value="<?= $p ?>" <?= ($ticketPriorityRaw === $p) ? 'selected' : '' ?>>
                                        <?= ucfirst($p) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Asignar a</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">Sin asignar</option>
                                <?php foreach ($assignees as $a): ?>
                                    <option value="<?= (int)$a['id'] ?>"
                                        <?= ((string)($ticket['assigned_to'] ?? '') === (string)$a['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($a['username']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <button class="btn btn-primary">Guardar cambios</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="p-3 bg-light border rounded-3">
                        <?= nl2br(htmlspecialchars($ticket['description'] ?? '')) ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<!-- Adjuntos -->
<div class="card shadow-sm border-0 mt-4">
    <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h2 class="h5 fw-bold mb-0">Adjuntos</h2>
            <?php if ($canEdit): ?>
                <span class="badge bg-light text-dark border">Solo Admin/Técnico</span>
            <?php endif; ?>
        </div>

        <?php if ($canEdit): ?>
            <form method="POST"
                action="?page=attachment_upload"
                enctype="multipart/form-data"
                class="row g-2 align-items-center mb-3">

                <?= csrf_field() ?>
                <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">

                <div class="col-md-9">
                    <input type="file" name="attachment" class="form-control" required>
                </div>

                <div class="col-md-3 text-md-end">
                    <button class="btn btn-primary">
                        Subir evidencia
                    </button>
                </div>

            </form>
        <?php endif; ?>

        <?php if (empty($attachments)): ?>
            <div class="text-muted">No hay adjuntos en este ticket.</div>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($attachments as $a): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="fw-semibold"><?= htmlspecialchars($a['original_name']) ?></div>
                            <?php if (isset($a['size_bytes'])): ?>
                                <div class="text-muted small"><?= htmlspecialchars(formatBytes((int)$a['size_bytes'])) ?></div>
                            <?php endif; ?>
                        </div>
                        <a class="btn btn-sm btn-outline-primary"
                           href="?page=attachment_download&id=<?= (int)$a['id'] ?>">
                            Descargar
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php $changes = $changes ?? []; ?>

<div class="card shadow-sm border-0 mt-5">
    <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-3">Historial de cambios</h2>

        <?php if (empty($changes)): ?>
            <div class="text-muted mb-0">
                Todavía no hay cambios registrados en este ticket.
            </div>
        <?php else: ?>

            <?php
            $formatValue = function (string $value): string {
                $value = str_replace('_', ' ', $value);
                return ucfirst($value);
            };
            ?>

            <div class="list-group list-group-flush">
                <?php foreach ($changes as $ch): ?>
                    <?php
                        $who = (string)($ch['username'] ?? 'Usuario');
                        $when = (string)($ch['created_at'] ?? '');
                        $field = (string)($ch['field'] ?? '');
                        $old = (string)($ch['old_value'] ?? '');
                        $new = (string)($ch['new_value'] ?? '');

                        $label = match ($field) {
                            'status' => 'Estado',
                            'priority' => 'Prioridad',
                            'assigned_to' => 'Asignado a',
                            default => 'Campo',
                        };

                        if ($field === 'status' || $field === 'priority') {
                            $old = $formatValue($old);
                            $new = $formatValue($new);
                        }
                    ?>
                    <div class="list-group-item px-0 py-3">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div>
                                <div class="fw-semibold">
                                    <?= htmlspecialchars($who, ENT_QUOTES, 'UTF-8') ?>
                                    <span class="text-muted fw-normal">cambió</span>
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>

                                <div class="text-muted small mt-1">
                                    Antes:
                                    <span class="fw-semibold text-dark">
                                        <?= htmlspecialchars($old, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    &nbsp;→&nbsp;
                                    Ahora:
                                    <span class="fw-semibold text-dark">
                                        <?= htmlspecialchars($new, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                            </div>

                            <div class="text-muted small">
                                <?= htmlspecialchars($when, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>
</div>

<!-- COMENTARIOS -->
<div class="card shadow-sm border-0 mt-5">
    <div class="card-body p-4">

        <h2 class="h5 fw-bold mb-3">Comentarios</h2>

        <?php if (empty($comments)): ?>
            <div class="text-muted mb-3">Todavía no hay comentarios en este ticket.</div>
        <?php else: ?>
            <div class="d-flex flex-column gap-3 mb-4">
            <?php foreach ($comments as $c): ?>
                <?php
                    $username = (string)($c['username'] ?? 'Usuario');
                    $initial = mb_strtoupper(mb_substr($username, 0, 1));
                    $createdAt = (string)($c['created_at'] ?? '');
                ?>
                <div class="d-flex gap-3">
                    <!-- Avatar -->
                    <div class="flex-shrink-0">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-sm"
                            style="width: 40px; height: 40px; font-weight: 700;">
                            <?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>

                    <!-- Comment bubble -->
                    <div class="flex-grow-1">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-3 p-md-4">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-semibold">
                                            <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>

                                    <div class="text-muted small">
                                        <?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </div>

                                <div class="text-body">
                                    <?= nl2br(htmlspecialchars((string)($c['comment'] ?? ''), ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($canEdit): ?>
            <form method="POST" action="?page=ticket_comment_add">
                <?= csrf_field() ?>
                <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">

                <div class="mb-3">
                    <label class="form-label">Añadir comentario</label>
                    <textarea name="comment" class="form-control" rows="3" required></textarea>
                    <div class="form-text">Máximo 2000 caracteres.</div>
                </div>

                <button class="btn btn-primary">Publicar comentario</button>
            </form>
        <?php else: ?>
            <div class="alert alert-info mb-0">
                Solo Admin y Técnico pueden escribir comentarios.
            </div>
        <?php endif; ?>

    </div>
</div>