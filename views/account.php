<?php
$user = currentUser();
 
$username = $user['username'] ?? '';
$role = $user['role'] ?? '';
 
$initial = strtoupper(mb_substr($username, 0, 1, 'UTF-8'));
$roleLabel = match ($role) {
    'admin' => 'Administrador',
    'tecnico' => 'Técnico',
    'lector' => 'Lector',
    default => $role,
};
 
$roleBadgeClass = match ($role) {
    'admin' => 'text-bg-danger',
    'tecnico' => 'text-bg-primary',
    'lector' => 'text-bg-secondary',
    default => 'text-bg-secondary',
};
?>
 
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">Mi cuenta</h1>
        <div class="text-muted">Gestiona tu información y la sesión</div>
    </div>
</div>
 
<div class="row g-4">
    <!-- Perfil -->
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
 
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-dark text-white"
                         style="width: 52px; height: 52px; font-size: 20px; font-weight: 700;">
                        <?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?>
                    </div>
 
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h2 class="h5 fw-bold mb-0">
                                <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
                            </h2>
                            <span class="badge <?= htmlspecialchars($roleBadgeClass, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                        <div class="text-muted small">
                            Sesión activa en SecureDesk
                        </div>
                    </div>
                </div>
 
                <div class="row g-3">
                    <div class="col-12">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="text-muted small mb-1">Usuario</div>
                            <div class="fw-semibold">
                                <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    </div>
 
                    <div class="col-12">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="text-muted small mb-1">Rol</div>
                            <div class="fw-semibold">
                                <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    </div>
                </div>
 
                <hr class="my-4">
 
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <a href="?page=home" class="btn btn-outline-secondary">
                        Volver al dashboard
                    </a>
                    <a href="?page=logout" class="btn btn-danger ms-sm-auto">
                        Cerrar sesión
                    </a>
                </div>
 
            </div>
        </div>
    </div>
 
    <!-- Acciones / Info -->
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h3 class="h6 fw-bold mb-3">Acciones rápidas</h3>
 
                <div class="list-group">
                    <a href="?page=tickets" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        Ir a Tickets
                        <span class="text-muted">›</span>
                    </a>
 
                    <?php if (($role ?? '') === 'admin'): ?>
                        <a href="?page=users" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            Gestión de Usuarios
                            <span class="text-muted">›</span>
                        </a>
                    <?php endif; ?>
                </div>
 
                <div class="mt-4 p-3 rounded-3 border bg-light">
                    <div class="fw-semibold mb-1">Consejo</div>
                    <div class="text-muted small">
                        Si compartes equipo, recuerda cerrar sesión al terminar.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
 