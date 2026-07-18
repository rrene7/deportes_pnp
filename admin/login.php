<?php
require dirname(__DIR__) . '/src/bootstrap.php';
if (admin_user()) { redirect('admin/index.php'); }
$error = null;
if ($_SERVER['REQUEST_METHOD']==='POST') { verify_csrf(); if (admin_login($pdo, trim((string)($_POST['usuario']??'')), (string)($_POST['password']??''))) { redirect('admin/index.php'); } $error='Usuario o contraseña incorrectos.'; }
$flashes=pull_flashes();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Acceso administrativo</title><link rel="stylesheet" href="<?=e(url('assets/css/app.css'))?>"></head><body class="page-bg"><main class="container narrow"><section class="card login-card"><div class="brand-mark">5K</div><h1>Panel administrativo</h1><p>Acceso exclusivo para personal autorizado.</p><?php foreach($flashes as $f):?><div class="alert alert-<?=e($f['type'])?>"><?=e($f['message'])?></div><?php endforeach;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><form method="post" class="form-grid one-column"><?=csrf_field()?><label>Usuario<input name="usuario" autocomplete="username" required></label><label>Contraseña<input type="password" name="password" autocomplete="current-password" required></label><button class="btn" type="submit">Iniciar sesión</button></form><p class="center"><a href="<?=e(url('index.php'))?>">← Volver a la página pública</a></p></section></main></body></html>
