<?php

require dirname(__DIR__) . '/src/bootstrap.php';

$user = require_permission($pdo, 'payments.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/inscripciones.php');
}

verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$action = (string) ($_POST['accion'] ?? '');
$observations = trim((string) ($_POST['observaciones'] ?? ''));

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT
            i.id,
            i.evento_id,
            i.codigo,
            i.estado,
            e.cupos
         FROM inscripciones i
         JOIN eventos e ON e.id = i.evento_id
         WHERE i.id = ?
         FOR UPDATE'
    );
    $stmt->execute([$id]);
    $registration = $stmt->fetch();

    if (!$registration || $registration['estado'] !== 'pago_pendiente') {
        throw new RuntimeException('La inscripción ya fue procesada.');
    }

    if ($action === 'confirmar') {
        if ($registration['cupos'] !== null) {
            $count = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM inscripciones
                 WHERE evento_id = ?
                   AND estado IN ('pago_confirmado', 'kit_entregado')"
            );
            $count->execute([$registration['evento_id']]);

            if ((int) $count->fetchColumn() >= (int) $registration['cupos']) {
                throw new RuntimeException('No quedan cupos disponibles.');
            }
        }

        $pdo->prepare(
            "UPDATE pagos
             SET estado = 'confirmado',
                 validado_por = ?,
                 fecha_validacion = NOW(),
                 observaciones = NULL
             WHERE inscripcion_id = ?"
        )->execute([$user['id'], $id]);

        $pdo->prepare(
            "UPDATE inscripciones
             SET estado = 'pago_confirmado'
             WHERE id = ?"
        )->execute([$id]);

        audit(
            $pdo,
            (int) $user['id'],
            'confirmar_pago',
            'inscripcion',
            $id,
            ['codigo' => $registration['codigo']]
        );

        $message = 'Pago confirmado y cupo reservado.';
    } elseif ($action === 'rechazar') {
        if ($observations === '') {
            throw new RuntimeException('Indique el motivo del rechazo.');
        }

        $pdo->prepare(
            "UPDATE pagos
             SET estado = 'rechazado',
                 validado_por = ?,
                 fecha_validacion = NOW(),
                 observaciones = ?
             WHERE inscripcion_id = ?"
        )->execute([$user['id'], $observations, $id]);

        $pdo->prepare(
            "UPDATE inscripciones
             SET estado = 'pago_rechazado'
             WHERE id = ?"
        )->execute([$id]);

        audit(
            $pdo,
            (int) $user['id'],
            'rechazar_pago',
            'inscripcion',
            $id,
            [
                'codigo' => $registration['codigo'],
                'motivo' => $observations,
            ]
        );

        $message = 'Comprobante rechazado.';
    } else {
        throw new RuntimeException('Acción no válida.');
    }

    $pdo->commit();
    flash('success', $message);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flash('danger', $exception->getMessage());
}

redirect('admin/ver.php?id=' . $id);

