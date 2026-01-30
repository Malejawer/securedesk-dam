<?php
declare(strict_types=1);
 
require_once __DIR__ . '/../app/database/Database.php';
require_once __DIR__ . '/../app/services/HealthCheckService.php';
require_once __DIR__ . '/../app/database/QueryBuilder.php'; 

// Cargar config
$config = require __DIR__ . '/../app/config/config.php';

// Conexión BD + estado
$db = Database::connect($config);
$service = new HealthCheckService($db);
$qb = new QueryBuilder($db); 

$status = $service->check()
    ? '✅ SQLite creada y conexión funcionando en local'
    : '❌ Error en la conexión';
 
// Página solicitada (?page=...)
$page = $_GET['page'] ?? 'home';
 
// Variables para el layout
$title = 'SecureDesk DAM';
$content = '';
 
// Navegación mínima (aún no funcional)
switch ($page) {
    case 'login':
        $title = 'Login - SecureDesk DAM';
        $content = '<h1>Login</h1><p>Pantalla de login (pendiente).</p>';
        break;
 
    case 'tickets':
        $title = 'Tickets - SecureDesk DAM';
        $content = '<h1>Tickets</h1><p>Listado de tickets (pendiente).</p>';
        break;
 
    case 'home':
    default:
        $title = 'Home - SecureDesk DAM';
        ob_start();
        require __DIR__ . '/../views/home.php';
        $content = ob_get_clean();
        break;
}
 
// Cargar layout (pinta $title y $content)
require __DIR__ . '/../views/layout.php';