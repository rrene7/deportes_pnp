<?php

require dirname(__DIR__) . '/src/bootstrap.php';

$user = require_admin();
$pageTitle = 'Panel principal';

$event = $pdo->query(
    "SELECT *
     FROM eventos
     WHERE slug = 'carrera-5k-policia-2026'
     LIMIT 1"
)->fetch();

$stats = [];

foreach ([
    'total' => 'SELECT COUNT(*) FROM inscripciones WHERE evento_id = ?',
    'pendientes' => "SELECT COUNT(*) FROM inscripciones
                     WHERE evento_id = ?
                       AND estado = 'pago_pendiente'",
    'confirmados' => "SELECT COUNT(*) FROM inscripciones
                      WHERE evento_id = ?
                        AND estado IN ('pago_confirmado', 'kit_entregado')",
    'rechazados' => "SELECT COUNT(*) FROM inscripciones
                     WHERE evento_id = ?
                       AND estado = 'pago_rechazado'",
    'kits' => "SELECT COUNT(*) FROM inscripciones
               WHERE evento_id = ?
                 AND estado = 'kit_entregado'",
] as $key => $sql) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$event['id']]);
    $stats[$key] = (int) $stmt->fetchColumn();
}

$categories = $pdo->prepare(
    "SELECT c.nombre, COUNT(i.id) AS total
     FROM categorias c
     LEFT JOIN inscripciones i
       ON i.categoria_id = c.id
      AND i.estado IN ('pago_confirmado', 'kit_entregado')
     WHERE c.evento_id = ?
     GROUP BY c.id
     ORDER BY c.edad_min"
);
$categories->execute([$event['id']]);
$categoryStats = $categories->fetchAll();

$recent = $pdo->prepare(
    'SELECT
        i.id,
        i.codigo,
        i.primer_nombre,
        i.primer_apellido,
        i.estado,
        i.creado_en,
        c.nombre AS categoria
     FROM inscripciones i
     JOIN categorias c ON c.id = i.categoria_id
     WHERE i.evento_id = ?
     ORDER BY i.id DESC
     LIMIT 8'
);
$recent->execute([$event['id']]);

require __DIR__ . '/_header.php';
?>

<div class="admin-heading">
    <div>
        <span class="section-kicker">Resumen operativo</span>
        <h1><?= e($event['nombre']) ?></h1>
        <p>
            <?= e(format_event_date($event['fecha_evento'])) ?> ·
            Hora
            <?= $event['hora_confirmada']
                ? e(date('g:i a', strtotime($event['hora_salida'])))
                : 'por confirmar' ?>
        </p>
    </div>

    <a
        class="btn"
        href="inscripciones.php<?= can_permission('payments.manage', $user)
            ? '?estado=pago_pendiente'
            : '' ?>"
    >
        <?= can_permission('payments.manage', $user)
            ? 'Revisar pagos'
            : 'Ver inscripciones' ?>
    </a>
</div>

<section class="stats-grid">
    <div class="stat-card">
        <span>Total de solicitudes</span>
        <strong><?= $stats['total'] ?></strong>
    </div>
    <div class="stat-card warning">
        <span>Pagos pendientes</span>
        <strong><?= $stats['pendientes'] ?></strong>
    </div>
    <div class="stat-card success">
        <span>Cupos confirmados</span>
        <strong><?= $stats['confirmados'] ?></strong>
    </div>
    <div class="stat-card">
        <span>Kits entregados</span>
        <strong><?= $stats['kits'] ?></strong>
    </div>
</section>

<section class="admin-grid">
    <div class="card">
        <h2>Confirmados por categoría</h2>

        <?php foreach ($categoryStats as $row): ?>
            <div class="metric-row">
                <span><?= e($row['nombre']) ?></span>
                <strong><?= (int) $row['total'] ?></strong>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2>Sesión actual</h2>

        <dl class="summary">
            <div>
                <dt>Usuario</dt>
                <dd><?= e($user['usuario']) ?></dd>
            </div>
            <div>
                <dt>Nombre</dt>
                <dd><?= e($user['nombre']) ?></dd>
            </div>
            <div>
                <dt>Rol</dt>
                <dd><?= e(admin_role_label($user['rol'])) ?></dd>
            </div>
            <div>
                <dt>Autenticación</dt>
                <dd>Usuario y contraseña local</dd>
            </div>
        </dl>

        <?php if (can_permission('event.manage', $user)): ?>
            <a href="evento.php">Editar evento →</a>
        <?php endif; ?>
    </div>
</section>

<section class="card table-card">
    <div class="table-header">
        <h2>Inscripciones recientes</h2>
        <a href="inscripciones.php">Ver todas</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Participante</th>
                    <th>Categoría</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $registration): ?>
                    <tr>
                        <td>
                            <a href="ver.php?id=<?= (int) $registration['id'] ?>">
                                <?= e($registration['codigo']) ?>
                            </a>
                        </td>
                        <td>
                            <?= e(
                                $registration['primer_nombre']
                                . ' '
                                . $registration['primer_apellido']
                            ) ?>
                        </td>
                        <td><?= e($registration['categoria']) ?></td>
                        <td>
                            <span class="badge <?= e(
                                badge_class($registration['estado'])
                            ) ?>">
                                <?= e(payment_status_label(
                                    $registration['estado']
                                )) ?>
                            </span>
                        </td>
                        <td><?= e(date(
                            'd/m/Y H:i',
                            strtotime($registration['creado_en'])
                        )) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/_footer.php'; ?>

