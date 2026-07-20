<?php
require __DIR__ . '/src/bootstrap.php';
$event = $pdo->query("SELECT * FROM eventos WHERE slug='carrera-5k-policia-2026' LIMIT 1")->fetch();
if (!$event) { exit('Evento no configurado.'); }
$categories = $pdo->prepare('SELECT * FROM categorias WHERE evento_id=? ORDER BY edad_min');
$categories->execute([$event['id']]);
$categories = $categories->fetchAll();
$confirmed = $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE evento_id=? AND estado IN ('pago_confirmado','kit_entregado')");
$confirmed->execute([$event['id']]);
$confirmedCount = (int)$confirmed->fetchColumn();
$available = $event['cupos'] !== null ? max(0, (int)$event['cupos'] - $confirmedCount) : null;
$flashes = pull_flashes();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#064f91"><title><?= e($event['nombre']) ?></title><link rel="stylesheet" href="assets/css/app.css"><link rel="stylesheet" href="assets/css/sports-theme.css"></head>
<body class="event-public">
<header class="sports-hero">
<div class="speed-lines" aria-hidden="true"></div>
<div class="container sports-hero-grid">
<figure class="official-poster"><div class="poster-glow" aria-hidden="true"></div><img src="assets/img/tour-firmeza-25-julio.webp" alt="Afiche oficial Tour Firmeza San Miguelito, carrera y caminata familiar del 25 de julio en los estacionamientos de Metro Mall"><figcaption>Afiche oficial del evento</figcaption></figure>
<div class="sports-hero-content">
<span class="sports-kicker">Tour Firmeza · San Miguelito</span>
<h1><?= e($event['nombre']) ?></h1>
<p class="sports-lead">Carrera y caminata familiar en los estacionamientos de Metro Mall. Inscríbete, paga por Yappy y carga tu comprobante para reservar el cupo.</p>
<div class="sports-facts">
<div><span>Fecha</span><strong><?= e(format_event_date($event['fecha_evento'])) ?></strong></div>
<div><span>Inscripción</span><strong><?= e(format_money($event['precio'])) ?></strong></div>
<div><span>Modalidad</span><strong>5K familiar</strong></div>
</div>
<div class="hero-actions"><a class="btn sports-primary" href="#inscripcion">Inscribirme ahora</a><a class="btn sports-secondary" href="estado.php">Consultar inscripción</a></div>
<div class="yappy-strip"><span>Pago por Yappy</span><strong><?= e($event['yappy_numero']) ?></strong><small>El cupo se confirma después de validar el pago.</small></div>
</div>
</div>
</header>
<main class="container main-space sports-main">
<?php foreach ($flashes as $flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endforeach; ?>
<section class="info-grid sports-info-grid">
<article class="info-card"><span class="icon">📅</span><h3>Fecha</h3><p><?= e(format_event_date($event['fecha_evento'])) ?></p></article>
<article class="info-card"><span class="icon">⏱️</span><h3>Hora de salida</h3><p><?= $event['hora_confirmada'] ? e(date('g:i a', strtotime($event['hora_salida']))) : 'Por confirmar' ?></p></article>
<article class="info-card"><span class="icon">🎽</span><h3>Entrega de kits</h3><p><?= e($event['entrega_kit_texto']) ?></p></article>
<article class="info-card"><span class="icon">📍</span><h3>Lugar del evento</h3><p>Estacionamientos de Metro Mall</p></article>
</section>
<section class="split-section sports-split"><div class="card categories-card"><span class="section-kicker">Categorías</span><h2>Compite en tu categoría</h2><div class="category-list"><?php foreach ($categories as $cat): ?><div><strong><?= e($cat['nombre']) ?></strong><span><?= $cat['edad_max'] ? e($cat['edad_min'].'–'.$cat['edad_max'].' años') : e($cat['edad_min'].' años o más') ?></span></div><?php endforeach; ?></div><p class="muted">La categoría se asigna automáticamente según la edad cumplida el día de la carrera. Edad mínima: 18 años.</p><?php if ($available !== null): ?><div class="capacity"><strong><?= $available ?></strong> cupos disponibles</div><?php endif; ?></div>
<div class="card steps-card"><span class="section-kicker">Inscripción</span><h2>Tres pasos para asegurar tu cupo</h2><ol class="steps"><li><span>1</span><div><strong>Completa tus datos</strong><p>La plataforma determina tu categoría automáticamente.</p></div></li><li><span>2</span><div><strong>Paga al Yappy <?= e($event['yappy_numero']) ?></strong><p>Adjunta una captura clara del comprobante.</p></div></li><li><span>3</span><div><strong>Espera la validación</strong><p>El encargado confirma el pago y reserva tu cupo.</p></div></li></ol></div></section>
<section id="inscripcion" class="card form-card sports-form-card"><div class="section-heading"><div><span class="section-kicker">Formulario público</span><h2>Inscripción a la carrera</h2><p class="form-intro">Completa todos los campos y adjunta el comprobante de Yappy.</p></div><span class="secure-pill">🔒 Datos protegidos</span></div>
<?php if (!(bool)$event['inscripciones_abiertas']): ?><div class="alert alert-warning">Las inscripciones se encuentran cerradas.</div>
<?php else: ?><form action="guardar_inscripcion.php" method="post" enctype="multipart/form-data" class="form-grid" id="registrationForm"><?= csrf_field() ?><input type="hidden" id="eventDate" value="<?= e($event['fecha_evento']) ?>">
<div class="form-section-title"><span>01</span> Datos personales</div>
<label>Número de cédula o pasaporte<input name="identificacion" required maxlength="35" autocomplete="off"></label>
<label>Primer nombre<input name="primer_nombre" required maxlength="80"></label>
<label>Segundo nombre<input name="segundo_nombre" maxlength="80"></label>
<label>Primer apellido<input name="primer_apellido" required maxlength="80"></label>
<label>Segundo apellido<input name="segundo_apellido" maxlength="80"></label>
<label>Fecha de nacimiento<input type="date" name="fecha_nacimiento" id="birthDate" required max="<?= e($event['fecha_evento']) ?>"><small id="categoryResult">La categoría aparecerá aquí.</small></label>
<label>Sexo<select name="sexo"><option value="No indica">Prefiero no indicar</option><option value="F">Femenino</option><option value="M">Masculino</option><option value="Otro">Otro</option></select></label>
<label>Correo electrónico<input type="email" name="correo" required maxlength="160"></label>
<label>Teléfono<input name="telefono" required maxlength="30"></label>
<label>Talla de camiseta<select name="talla_camiseta" required><option value="">Seleccione</option><?php foreach (['XS','S','M','L','XL','2XL','3XL'] as $s): ?><option><?= $s ?></option><?php endforeach; ?></select></label>
<label>Contacto de emergencia<input name="contacto_emergencia" required maxlength="160"></label>
<label>Teléfono de emergencia<input name="telefono_emergencia" required maxlength="30"></label>
<div class="form-section-title"><span>02</span> Información del pago</div>
<div class="payment-box full"><strong>Realice el pago por Yappy al <?= e($event['yappy_numero']) ?>.</strong><span>En la descripción coloque su nombre y número de identificación.</span></div>
<label>Nombre del titular del Yappy<input name="nombre_titular" required maxlength="160"></label>
<label>Referencia de la transacción <span class="optional">opcional</span><input name="referencia" maxlength="80"></label>
<label>Fecha del pago<input type="date" name="fecha_pago" required value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
<label>Monto pagado<input type="number" name="monto" min="0.01" step="0.01" required value="<?= $event['precio'] !== null ? e($event['precio']) : '' ?>" <?= $event['precio'] !== null ? 'readonly' : '' ?>></label>
<label class="full upload-label">Captura del comprobante<input type="file" name="comprobante" accept="image/jpeg,image/png,application/pdf" required><small>JPG, PNG o PDF. Máximo 5 MB.</small></label>
<label class="check-label full"><input type="checkbox" name="acepta_reglamento" value="1" required><span>Declaro que los datos son correctos y acepto las condiciones del evento y el tratamiento de los datos necesarios para gestionar mi inscripción.</span></label>
<button class="btn btn-large full sports-submit" type="submit">Enviar inscripción y comprobante</button></form><?php endif; ?>
</section></main><footer class="sports-footer"><div class="container"><strong>Tour Firmeza · San Miguelito</strong><span>Carrera 5K Policía Nacional · Plataforma oficial de inscripción</span></div></footer>
<script>
const birth = document.getElementById('birthDate');
const result = document.getElementById('categoryResult');
const eventDate = document.getElementById('eventDate')?.value;
function ageAt(dateBirth, dateEvent){const b=new Date(dateBirth+'T00:00:00'),e=new Date(dateEvent+'T00:00:00');let a=e.getFullYear()-b.getFullYear();const m=e.getMonth()-b.getMonth();if(m<0||(m===0&&e.getDate()<b.getDate()))a--;return a;}
birth?.addEventListener('change',()=>{if(!birth.value)return;const age=ageAt(birth.value,eventDate);if(age<18){result.textContent='No cumple la edad mínima de 18 años.';result.className='field-error';}else if(age<=39){result.textContent='Categoría: 18 a 39 años ('+age+' años).';result.className='field-ok';}else{result.textContent='Categoría: 40 años en adelante ('+age+' años).';result.className='field-ok';}});
</script></body></html>