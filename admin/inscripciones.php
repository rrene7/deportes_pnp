<?php
require dirname(__DIR__) . '/src/bootstrap.php';
require_admin();
$pageTitle='Inscripciones';
$event=$pdo->query("SELECT * FROM eventos WHERE slug='carrera-5k-policia-2026' LIMIT 1")->fetch();
$status=trim((string)($_GET['estado']??''));$q=trim((string)($_GET['q']??''));
$sql='SELECT i.*,c.nombre categoria,p.estado pago_estado,p.monto,p.fecha_pago FROM inscripciones i JOIN categorias c ON c.id=i.categoria_id JOIN pagos p ON p.inscripcion_id=i.id WHERE i.evento_id=:event';$params=['event'=>$event['id']];
if($status!==''){$sql.=' AND i.estado=:status';$params['status']=$status;}
if($q!==''){$sql.=' AND (i.codigo LIKE :q OR i.identificacion LIKE :q OR CONCAT(i.primer_nombre," ",i.primer_apellido) LIKE :q)';$params['q']='%'.$q.'%';}
$sql.=' ORDER BY i.id DESC LIMIT 500';$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
require __DIR__.'/_header.php';
?>
<div class="admin-heading"><div><span class="section-kicker">Gestión</span><h1>Inscripciones</h1><p><?=count($rows)?> resultados mostrados</p></div></div>
<form class="filter-bar" method="get"><input name="q" value="<?=e($q)?>" placeholder="Buscar nombre, código o identificación"><select name="estado"><option value="">Todos los estados</option><?php foreach(['pago_pendiente'=>'Pago pendiente','pago_confirmado'=>'Pago confirmado','pago_rechazado'=>'Pago rechazado','kit_entregado'=>'Kit entregado','cancelada'=>'Cancelada'] as $v=>$l):?><option value="<?=e($v)?>" <?=$status===$v?'selected':''?>><?=e($l)?></option><?php endforeach;?></select><button class="btn" type="submit">Filtrar</button></form>
<section class="card table-card"><div class="table-wrap"><table><thead><tr><th>Código</th><th>Participante</th><th>Identificación</th><th>Categoría</th><th>Monto</th><th>Estado</th><th>Acción</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=e($r['codigo'])?></td><td><?=e($r['primer_nombre'].' '.$r['primer_apellido'])?></td><td><?=e($r['identificacion'])?></td><td><?=e($r['categoria'])?></td><td><?=e(format_money($r['monto']))?></td><td><span class="badge <?=e(badge_class($r['estado']))?>"><?=e(payment_status_label($r['estado']))?></span></td><td><a class="table-link" href="ver.php?id=<?=$r['id']?>">Revisar</a></td></tr><?php endforeach;?></tbody></table></div></section>
<?php require __DIR__.'/_footer.php'; ?>
