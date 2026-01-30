<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'SecureDesk DAM', ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f4f4f4;
        }
 
        header {
            background-color: #222;
            padding: 15px;
        }
 
        header a {
            color: #ffffff;
            text-decoration: none;
            margin-right: 20px;
            font-weight: bold;
        }
 
        header a:hover {
            text-decoration: underline;
        }
 
        main {
            padding: 20px;
            background-color: #ffffff;
            min-height: calc(100vh - 50px);
        }
    </style>
</head>
<body>
 
<header>
    <a href="?page=home">Home</a>
    <a href="?page=login">Login</a>
    <a href="?page=tickets">Tickets</a>
</header>
 
<main>
    <?= $content ?>
</main>
 
</body>
</html>