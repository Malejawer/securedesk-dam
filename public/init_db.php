<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/database/Database.php';
$config = require __DIR__ . '/../app/config/config.php';

require_once __DIR__ . '/../app/database/db_init.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = Database::connect($config);

    $messages = db_init($pdo);

    echo "Inicialización completada:\n";
    echo implode("\n", $messages) . "\n";

    echo "\n Ya puedes volver a: /public/index.php\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "❌ Error inicializando la BD: " . $e->getMessage();
}