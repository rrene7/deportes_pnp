<?php

require dirname(__DIR__) . '/src/bootstrap.php';

require_permission($pdo, 'audit.view');
$pageTitle = 'Auditoría del sistema';

$userFilter = (int) ($_GET['usuario_id'] ?? 0);
$actionFilter = trim((string) ($_GET['accion'] ?? ''));
$dateFrom = trim((string) ($_GET['desde'] ?? ''));
$dateTo = trim((string) ($_GET['hasta'] ?? ''));

$sql = 'SELECT
            a.id,
            a.usuario_id,
            a.accion,
            a.entidad,
            a.entidad_id,
            a.detalle,
            a.ip,
            a.creado_en,
            u.nombre AS usuario_nombre,
            u.usuario AS usuario_cuenta,
            u.rol AS usuario_rol
        FROM auditoria a
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        WHERE 1 = 1';

$params = [];

if ($userFilter > 0) {
    $sql .= ' AND a.usuario_id = ?';
    $params[] = $userFilter;
}

if ($actionFilter !== '') {
    $sql .= ' AND a.accion = ?';
    $params[] = $actionFilter;
}

if ($dateFrom !== '') {
    $sql .= ' AND a.creado_en >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $sql .= ' AND a.creado_en <= ?';
    $params[] = $dateTo . ' 23:59:59';
}

$sql .= ' ORDER BY a.id DESC LIMIT 500';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$users = $pdo->query(
    'SELECT id, nombre, usuario
     FROM usuarios
     ORDER BY nombre'
)->fetchAll();

$actions = $pdo->query(
    'SELECT DISTINCT accion
     FROM auditoria
     ORDER BY accion'
)->fetchAll(PDO::FETCH_COLUMN);

$actionLabels = [
    'inicio_sesion' => 'Inicio de sesión',
    'inicio_sesion_fallido' => 'Intento de acceso fallido',
    'cierre_sesion' => 'Cierre de sesión',
    'sesion_revocada' => 'Sesión revocada',
    'acceso_denegado' => 'Acceso denegado',
    'crear_usuario' => 'Creó un usuario',
    'actualizar_usuario' => 'Actualizó un usuario',
    'confirmar_pago' => 'Confirmó un pago',
    'rechazar_pago' => 'Rechazó un pago',
    'entregar_kit' => 'Entregó un kit',
    'actualizar_evento' => 'Actualizó el evento',
    'exportar_inscripciones' => 'Exportó las inscripciones',
    'ver_comprobante' => 'Abrió un comprobante',
];

require __DIR__ . '/_header.php';
?>

<div class="admin-heading">
    <div>
        <span class="section-kicker">Trazabilidad</span>
        <h1>Auditoría del sistema</h1>
        <p>
            Consulte quién realizó cada operación, la fecha, la dirección IP
            y el registro afectado.
        </p>
    </div>
</div>

<form class="audit-filter card" method="get">
    <label>
        Usuario
        <select name="usuario_id">
            <option value="0">Todos</option>
            <?php foreach ($users as $user): ?>
                <option
                    value="<?= (int) $user['id'] ?>"
                    <?= $userFilter === (int) $user['id'] ? 'selected' : '' ?>
                >
                    <?= e($user['nombre'] . ' (' . $user['usuario'] . ')') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        Acción
        <select name="accion">
            <option value="">Todas</option>
            <?php foreach ($actions as $action): ?>
                <option
                    value="<?= e($action) ?>"
                    <?= $actionFilter === $action ? 'selected' : '' ?>
                >
                    <?= e($actionLabels[$action] ?? $action) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        Desde
        <input type="date" name="desde" value="<?= e($dateFrom) ?>">
    </label>

    <label>
        Hasta
        <input type="date" name="hasta" value="<?= e($dateTo) ?>">
    </label>

    <button class="btn" type="submit">Filtrar</button>
    <a class="btn btn-outline" href="auditoria.php">Limpiar</a>
</form>

<section class="card table-card">
    <div class="table-header">
        <div>
            <h2>Actividad registrada</h2>
            <p class="muted"><?= count($logs) ?> resultados mostrados</p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="audit-table">
            <thead>
                <tr>
                    <th>Fecha y hora</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Registro</th>
                    <th>Detalle</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $details = [];
                    if ($log['detalle']) {
                        $decoded = json_decode($log['detalle'], true);
                        $details = is_array($decoded) ? $decoded : [];
                    }
                    ?>
                    <tr>
                        <td>
                            <?= e(date(
                                'd/m/Y H:i:s',
                                strtotime($log['creado_en'])
                            )) ?>
                        </td>
                        <td>
                            <?php if ($log['usuario_nombre']): ?>
                                <strong><?= e($log['usuario_nombre']) ?></strong>
                                <small class="audit-subline">
                                    <?= e($log['usuario_cuenta']) ?> ·
                                    <?= e(admin_role_label($log['usuario_rol'])) ?>
                                </small>
                            <?php else: ?>
                                <span>Sistema o usuario no identificado</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e($actionLabels[$log['accion']] ?? $log['accion']) ?>
                        </td>
                        <td>
                            <?= e($log['entidad']) ?>
                            <?= $log['entidad_id'] !== null
                                ? '#' . (int) $log['entidad_id']
                                : '' ?>
                        </td>
                        <td>
                            <?php if ($details): ?>
                                <details>
                                    <summary>Ver detalle</summary>
                                    <pre><?= e(json_encode(
                                        $details,
                                        JSON_PRETTY_PRINT
                                        | JSON_UNESCAPED_UNICODE
                                        | JSON_UNESCAPED_SLASHES
                                    )) ?></pre>
                                </details>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= e($log['ip'] ?? 'No registrada') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/_footer.php'; ?>

