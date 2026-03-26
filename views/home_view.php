<h1 class="mb-4">Dashboard</h1>

<?php
$criticalUrl = '?page=tickets&priority=critica';
$unassignedUrl = '?page=tickets&assigned=unassigned';
?>

<div class="row g-4">

    <div class="col-md-3">
        <div class="card shadow-sm border-0 text-center">
            <div class="card-body">
                <div class="text-muted">Total tickets</div>
                <div class="display-6 fw-bold"><?= (int)$totalTickets ?></div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm border-0 text-center">
            <div class="card-body">
                <div class="text-muted">Nuevos</div>
                <div class="display-6 fw-bold"><?= (int)$statusCounts['nuevo'] ?></div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm border-0 text-center">
            <div class="card-body">
                <div class="text-muted">En proceso</div>
                <div class="display-6 fw-bold"><?= (int)$statusCounts['en_proceso'] ?></div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm border-0 text-center">
            <div class="card-body">
                <div class="text-muted">Resueltos</div>
                <div class="display-6 fw-bold"><?= (int)$statusCounts['resuelto'] ?></div>
            </div>
        </div>
    </div>

</div>

<hr class="my-4">

<h4 class="mb-3">Prioridades</h4>

<div class="row g-4 mb-4">

    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted">Baja</div>
                <div class="fw-bold"><?= (int)$priorityCounts['baja'] ?></div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted">Media</div>
                <div class="fw-bold"><?= (int)$priorityCounts['media'] ?></div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted">Alta</div>
                <div class="fw-bold"><?= (int)$priorityCounts['alta'] ?></div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted">Crítica</div>
                <div class="fw-bold"><?= (int)$priorityCounts['critica'] ?></div>
            </div>
        </div>
    </div>

</div>

<div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h4 class="mb-3">Distribución por categoría</h4>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Categoría</th>
                                <th class="text-end">Tickets</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Phishing</td>
                                <td class="text-end fw-semibold"><?= (int)$categoryCounts['phishing'] ?></td>
                            </tr>
                            <tr>
                                <td>Malware</td>
                                <td class="text-end fw-semibold"><?= (int)$categoryCounts['malware'] ?></td>
                            </tr>
                            <tr>
                                <td>Permisos</td>
                                <td class="text-end fw-semibold"><?= (int)$categoryCounts['permisos'] ?></td>
                            </tr>
                            <tr>
                                <td>Otros</td>
                                <td class="text-end fw-semibold"><?= (int)$categoryCounts['otros'] ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h4 class="mb-3">Distribución por estado</h4>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Estado</th>
                                <th class="text-end">Tickets</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Nuevo</td>
                                <td class="text-end fw-semibold"><?= (int)$statusCounts['nuevo'] ?></td>
                            </tr>
                            <tr>
                                <td>En proceso</td>
                                <td class="text-end fw-semibold"><?= (int)$statusCounts['en_proceso'] ?></td>
                            </tr>
                            <tr>
                                <td>Resuelto</td>
                                <td class="text-end fw-semibold"><?= (int)$statusCounts['resuelto'] ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<hr class="my-4">

<h4 class="mb-3">Vistas rápidas</h4>

<div class="row g-4">
    <div class="col-12 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="fw-bold mb-2">Prioridad crítica</h5>
                <p class="text-muted mb-3">
                    Acceso rápido a los tickets que requieren atención inmediata.
                </p>
                <a href="<?= htmlspecialchars($criticalUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-danger">
                    Ver tickets críticos
                </a>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="fw-bold mb-2">Sin asignar</h5>
                <p class="text-muted mb-3">
                    Acceso rápido a tickets pendientes de asignación.
                </p>
                <a href="<?= htmlspecialchars($unassignedUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-warning">
                    Ver tickets sin asignar
                </a>
            </div>
        </div>
    </div>
</div>

<hr class="my-4">

<div class="text-muted">
    Última actualización: <strong><?= htmlspecialchars((string)$lastUpdate, ENT_QUOTES, 'UTF-8') ?></strong>
</div>