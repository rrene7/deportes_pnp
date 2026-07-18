<?php

declare(strict_types=1);

const APP_ROOT = __DIR__;
$configFile = APP_ROOT . '/config/config.php';
$lockFile = APP_ROOT . '/storage/installed.lock';
$message = null;
$error = null;

if (!is_file($configFile)) {
    $error = 'Primero copie config/config.example.php como config/config.php.';
} else {
    $config = require $configFile;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && !is_file($lockFile)) {
    $name = trim((string)($_POST['nombre'] ?? ''));
    $username = trim((string)($_POST['usuario'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($name === '' || $username === '' || strlen($password) < 8) {
        $error = 'Complete todos los campos. La contraseña debe tener al menos 8 caracteres.';
    } else {
        try {
            $db = $config['db'];
            if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$db['name'])) {
                throw new RuntimeException('El nombre configurado para la base de datos no es válido.');
            }

            $serverDsn = sprintf('mysql:host=%s;port=%d;charset=%s', $db['host'], $db['port'], $db['charset']);
            $server = new PDO($serverDsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $server->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                $db['name']
            ));

            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset']);
            $pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
            ]);
            $schema = file_get_contents(APP_ROOT . '/database/schema.sql');
            if ($schema === false) {
                throw new RuntimeException('No fue posible leer database/schema.sql.');
            }
            $pdo->exec($schema);
            $count = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
            if ($count > 0) {
                throw new RuntimeException('Ya existe un usuario administrador. El instalador no modificó sus credenciales.');
            }
            $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, usuario, password_hash, rol) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), 'administrador']);
            file_put_contents($lockFile, date(DATE_ATOM));
            $message = 'Instalación completada. Ya puede abrir el formulario público y el panel administrativo.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalación Carrera 5K</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="page-bg"><main class="container narrow"><section class="card install-card"><div class="brand-mark">5K</div><h1>Instalación local</h1>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><p><a class="btn" href="index.php">Abrir inscripción</a> <a class="btn btn-outline" href="admin/login.php">Abrir administración</a></p>
<?php elseif (is_file($lockFile)): ?><div class="alert alert-warning">El sistema ya fue instalado. Para mayor seguridad, mantenga bloqueado este instalador.</div><p><a class="btn" href="index.php">Abrir inscripción</a></p>
<?php else: ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<p>Este proceso crea la base de datos, las tablas, el evento y el primer administrador.</p>
<form method="post" class="form-grid one-column"><label>Nombre del administrador<input name="nombre" required></label><label>Usuario<input name="usuario" required autocomplete="username"></label><label>Contraseña<input name="password" type="password" minlength="8" required autocomplete="new-password"></label><button class="btn" type="submit">Instalar sistema</button></form>
<?php endif; ?></section></main></body></html>
