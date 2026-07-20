<?php
$currentUser = admin_user();
$flashes = pull_flashes();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($pageTitle ?? 'Administración') ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/admin-access.css')) ?>">
</head>
<body class="admin-body">
<header class="admin-top">
    <a class="admin-brand" href="<?= e(url('admin/index.php')) ?>">
        <span>5K</span>
        <strong>Administración</strong>
    </a>

    <nav>
        <a href="<?= e(url('admin/index.php')) ?>">Panel</a>
        <a href="<?= e(url('admin/inscripciones.php')) ?>">Inscripciones</a>

        <?php if (can_permission('event.manage')): ?>
            <a href="<?= e(url('admin/evento.php')) ?>">Evento</a>
        <?php endif; ?>

        <?php if (can_permission('export.manage')): ?>
            <a href="<?= e(url('admin/exportar.php')) ?>">Exportar</a>
        <?php endif; ?>

        <?php if (can_permission('users.manage')): ?>
            <a href="<?= e(url('admin/usuarios.php')) ?>">Usuarios</a>
        <?php endif; ?>

        <?php if (can_permission('audit.view')): ?>
            <a href="<?= e(url('admin/auditoria.php')) ?>">Auditoría</a>
        <?php endif; ?>

        <a href="<?= e(url('admin/logout.php')) ?>">Salir</a>
    </nav>

    <div class="admin-user">
        <strong><?= e($currentUser['nombre'] ?? '') ?></strong>
        <span class="admin-role">
            <?= e(admin_role_label((string) ($currentUser['rol'] ?? ''))) ?>
        </span>
    </div>
</header>

<main class="admin-main container">
    <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endforeach; ?>

