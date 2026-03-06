<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">

        <div class="text-center mb-4">
            <h1 class="h3 fw-bold mb-1">SecureDesk</h1>
            <div class="text-muted">Accede al panel</div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success" role="alert">
                        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="?page=login" class="vstack gap-3">
                    <?= csrf_field() ?>

                    <div>
                        <label class="form-label">Usuario</label>
                        <input
                            type="text"
                            name="username"
                            class="form-control"
                            placeholder="admin"
                            value="<?= htmlspecialchars($oldUsername ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            required
                            autofocus
                        >
                    </div>

                    <div>
                        <label class="form-label">Contraseña</label>
                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            placeholder="••••••••"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-semibold">
                        Entrar
                    </button>

                    <div class="text-center text-muted small">
                        Tip: prueba con <strong>admin / admin123</strong>
                    </div>
                </form>

            </div>
        </div>

        <div class="text-center mt-3 text-muted small">
            Proyecto de prácticas DAM
        </div>

    </div>
</div>