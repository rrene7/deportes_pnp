<?php
require __DIR__ . '/src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php'); }
verify_csrf();

$eventStmt = $pdo->query("SELECT * FROM eventos WHERE slug='carrera-5k-policia-2026' LIMIT 1");
$event = $eventStmt->fetch();
if (!$event || !(bool)$event['inscripciones_abiertas']) {
    flash('warning', 'Las inscripciones no están disponibles.');
    redirect('index.php');
}

$required = ['identificacion','primer_nombre','primer_apellido','fecha_nacimiento','correo','telefono','contacto_emergencia','telefono_emergencia','talla_camiseta','nombre_titular','fecha_pago','monto'];
foreach ($required as $field) {
    if (trim((string)($_POST[$field] ?? '')) === '') {
        flash('danger', 'Complete todos los campos obligatorios.');
        redirect('index.php#inscripcion');
    }
}
if (empty($_POST['acepta_reglamento'])) {
    flash('danger', 'Debe aceptar las condiciones del evento.');
    redirect('index.php#inscripcion');
}

$sexAllowed = ['F','M','Otro','No indica'];
$sizeAllowed = ['XS','S','M','L','XL','2XL','3XL'];
if (!in_array($_POST['sexo'] ?? 'No indica', $sexAllowed, true) || !in_array($_POST['talla_camiseta'] ?? '', $sizeAllowed, true)) {
    flash('danger', 'Seleccione opciones válidas en el formulario.');
    redirect('index.php#inscripcion');
}
if (!filter_var($_POST['correo'], FILTER_VALIDATE_EMAIL)) {
    flash('danger', 'El correo electrónico no es válido.');
    redirect('index.php#inscripcion');
}
if (!is_numeric($_POST['monto']) || (float)$_POST['monto'] <= 0) {
    flash('danger', 'El monto pagado debe ser mayor que cero.');
    redirect('index.php#inscripcion');
}
try {
    $paymentDate = new DateTimeImmutable((string)$_POST['fecha_pago']);
    $today = new DateTimeImmutable('today');
    if ($paymentDate > $today) { throw new RuntimeException(); }
} catch (Throwable) {
    flash('danger', 'La fecha del pago no es válida.');
    redirect('index.php#inscripcion');
}

try {
    $age = calculate_age((string)$_POST['fecha_nacimiento'], $event['fecha_evento']);
} catch (Throwable) {
    flash('danger', 'La fecha de nacimiento no es válida.');
    redirect('index.php#inscripcion');
}
$category = event_category($pdo, (int)$event['id'], $age);
if (!$category) {
    flash('danger', 'La persona debe tener al menos 18 años el día de la carrera.');
    redirect('index.php#inscripcion');
}

$upload = $_FILES['comprobante'] ?? null;
if (!$upload || $upload['error'] !== UPLOAD_ERR_OK) {
    flash('danger', 'Debe adjuntar una captura válida del comprobante.');
    redirect('index.php#inscripcion');
}
$maxBytes = (int)($config['app']['max_upload_bytes'] ?? 5242880);
if ((int)$upload['size'] > $maxBytes) {
    flash('danger', 'El comprobante supera el máximo de 5 MB.');
    redirect('index.php#inscripcion');
}
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($upload['tmp_name']);
$allowed = ['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
if (!isset($allowed[$mime])) {
    flash('danger', 'El comprobante debe ser JPG, PNG o PDF.');
    redirect('index.php#inscripcion');
}

$storedName = bin2hex(random_bytes(18)) . '.' . $allowed[$mime];
$storagePath = APP_ROOT . '/storage/uploads/' . $storedName;
$reference = trim((string)($_POST['referencia'] ?? '')) ?: null;
$moved = false;

try {
    $pdo->beginTransaction();
    $tempCode = 'TMP-' . bin2hex(random_bytes(8));
    $stmt = $pdo->prepare('INSERT INTO inscripciones
        (evento_id,categoria_id,codigo,identificacion,primer_nombre,segundo_nombre,primer_apellido,segundo_apellido,fecha_nacimiento,edad_evento,sexo,correo,telefono,contacto_emergencia,telefono_emergencia,talla_camiseta,acepta_reglamento,ip_registro)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $event['id'], $category['id'], $tempCode,
        trim($_POST['identificacion']), trim($_POST['primer_nombre']), trim($_POST['segundo_nombre'] ?? '') ?: null,
        trim($_POST['primer_apellido']), trim($_POST['segundo_apellido'] ?? '') ?: null,
        $_POST['fecha_nacimiento'], $age, $_POST['sexo'] ?? 'No indica', strtolower(trim($_POST['correo'])), trim($_POST['telefono']),
        trim($_POST['contacto_emergencia']), trim($_POST['telefono_emergencia']), $_POST['talla_camiseta'], 1, $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    $registrationId = (int)$pdo->lastInsertId();
    $code = 'PN5K-' . str_pad((string)$registrationId, 6, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE inscripciones SET codigo=? WHERE id=?')->execute([$code, $registrationId]);

    if (!move_uploaded_file($upload['tmp_name'], $storagePath)) {
        throw new RuntimeException('No se pudo guardar el comprobante.');
    }
    $moved = true;
    $pay = $pdo->prepare('INSERT INTO pagos (inscripcion_id,yappy_numero,nombre_titular,referencia,fecha_pago,monto,archivo_comprobante,nombre_original,mime_type,tamano_bytes) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $pay->execute([$registrationId,$event['yappy_numero'],trim($_POST['nombre_titular']),$reference,$_POST['fecha_pago'],(float)$_POST['monto'],$storedName,$upload['name'],$mime,(int)$upload['size']]);
    audit($pdo, null, 'registro_publico', 'inscripcion', $registrationId, ['codigo'=>$code]);
    $pdo->commit();
    $_SESSION['last_registration_code'] = $code;
    redirect('confirmacion.php');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    if ($moved && is_file($storagePath)) { unlink($storagePath); }
    $message = str_contains($e->getMessage(), 'uq_evento_identificacion') ? 'Ya existe una inscripción para esta identificación.' : (str_contains($e->getMessage(), 'referencia') ? 'La referencia de pago ya fue registrada.' : 'No se pudo guardar la inscripción. Revise los datos e inténtelo nuevamente.');
    flash('danger', $message);
    redirect('index.php#inscripcion');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    if ($moved && is_file($storagePath)) { unlink($storagePath); }
    flash('danger', $e->getMessage());
    redirect('index.php#inscripcion');
}
