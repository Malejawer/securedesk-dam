<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'SecureDesk DAM', ENT_QUOTES, 'UTF-8') ?></title>
 
    <!-- Bootstrap 5 (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
 
    <style>
        body { background: #f6f7fb; }
        .app-shell { min-height: 100vh; }
    </style>
</head>
<body>
 
<div class="app-shell d-flex flex-column">
 
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="?page=home">SecureDesk</a>
 
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
                <span class="navbar-toggler-icon"></span>
            </button>
 
            <div class="collapse navbar-collapse" id="navMain">
                <!-- IZQUIERDA: navegación principal -->
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php if (!function_exists('isLoggedIn') || !isLoggedIn()): ?>
                        <li class="nav-item"><a class="nav-link" href="?page=login">Login</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="?page=home">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="?page=tickets">Tickets</a></li>
 
                        <?php
                            $user = function_exists('currentUser') ? currentUser() : null;
                            $role = is_array($user) ? ($user['role'] ?? null) : null;
                        ?>
 
                        <?php if ($role === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="?page=users">Usuarios</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
 
                <!-- DERECHA: menú de usuario (pro) -->
                <?php if (function_exists('isLoggedIn') && isLoggedIn() && function_exists('currentUser') && currentUser()): ?>
                    <?php $u = currentUser(); ?>
                    <div class="dropdown">
                        <button class="btn btn-dark dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="text-white-50">
                                <?= htmlspecialchars($u['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span class="badge text-bg-secondary">
                                <?= htmlspecialchars($u['role'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </button>
 
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="?page=account">Mi cuenta</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="?page=logout">Cerrar sesión</a></li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
 
    <main class="container py-4 flex-grow-1">
 
        <?php if (function_exists('getFlash') && ($flash = getFlash())): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
 
        <?= $content ?>
    </main>
 
    <footer class="py-3 border-top bg-white">
        <div class="container text-center text-muted small">
            SecureDesk DAM · SQLite · PHP
        </div>
    </footer>
</div>
 
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
 
