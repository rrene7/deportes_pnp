<?php

require __DIR__ . '/src/bootstrap.php';

$user = require_permission($pdo, 'payments.manage');

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT
        p.archivo_comprobante,
        p.nombre_original,
        p.mime_type,
        p.inscripcion_id,
        i.codigo
     FROM pagos p
     JOIN inscripciones i ON i.id = p.inscripcion_id
     WHERE p.id = ?
     LIMIT 1'
);
$stmt->execute([$id]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$path = APP_ROOT . '/storage/uploads/' . $file['archivo_comprobante'];

if (!is_file($path)) {
    http_response_code(404);
    exit('Archivo no disponible.');
}

audit(
    $pdo,
    (int) $user['id'],
    'ver_comprobante',
    'inscripcion',
    (int) $file['inscripcion_id'],
    ['codigo' => $file['codigo']]
);

header('Content-Type: ' . $file['mime_type']);
header('Content-Length: ' . filesize($path));
header(
    'Content-Disposition: inline; filename="'
    . rawurlencode($file['nombre_original'])
    . '"'
);
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;

