<?php

require dirname(__DIR__) . '/src/bootstrap.php';

$user = require_permission($pdo, 'kits.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/inscripciones.php');
}

verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$size = trim((string) ($_POST['talla'] ?? ''));
$observations = trim((string) ($_POST['observaciones'] ?? '')) ?: null;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT estado, codigo
         FROM inscripciones
         WHERE id = ?
         FOR UPDATE'
    );
    $stmt->execute([$id]);
    $registration = $stmt->fetch();

    if (!$registration || $registration['estado'] !== 'pago_confirmado') {
        throw new RuntimeException(
            'Solo puede entregar kits de pagos confirmados.'
        );
    }

    $pdo->prepare(
        'INSERT INTO entrega_kits
            (inscripcion_id, talla_entregada, entregado_por, observaciones)
         VALUES (?, ?, ?, ?)'
    )->execute([$id, $size, $user['id'], $observations]);

    $pdo->prepare(
        "UPDATE inscripciones
         SET estado = 'kit_entregado'
         WHERE id = ?"
    )->execute([$id]);

    audit(
        $pdo,
        (int) $user['id'],
        'entregar_kit',
        'inscripcion',
        $id,
        [
            'codigo' => $registration['codigo'],
            'talla' => $size,
        ]
    );

    $pdo->commit();
    flash('success', 'Kit entregado correctamente.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flash('danger', $exception->getMessage());
}

redirect('admin/ver.php?id=' . $id);

