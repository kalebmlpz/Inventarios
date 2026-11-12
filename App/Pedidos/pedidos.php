<?php
require_once __DIR__.'/../backend/auth.php';
$user=require_access(['Administrador','Cajero'],false);
require_once __DIR__.'/../backend/pedidos.php';
function esc($value) { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=operationToken();
$conn=inventoryDb();$error='';
$values=['cliente'=>'','telefono'=>'','articulo_id'=>'','cantidad'=>'1','fecha_entrega'=>'','operacion_id'=>operationToken()];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrfCheck();
    try {
        if (($_POST['accion']??'')==='crear') {
            foreach ($values as $key=>$value) $values[$key]=is_string($_POST[$key]??null)?$_POST[$key]:'';
            $id=createOrder($conn,$user,$_POST);
            $_SESSION['pedido_message']='Pedido #'.$id.' registrado. Todavía no se descontaron existencias.';
        } elseif (($_POST['accion']??'')==='estado') {
            transitionOrder($conn,$user,$_POST);
            $_SESSION['pedido_message']='Estado del pedido actualizado.';
        } else throw new DomainException('Acción no permitida.');
        header('Location: pedidos.php',true,303);exit;
    } catch (DomainException $e) { http_response_code(422);$error=$e->getMessage(); }
      catch (PDOException $e) { http_response_code(500);$error='No se pudo guardar el pedido. Comprueba la actualización de la base de datos.'; }
}
$message=$_SESSION['pedido_message']??'';unset($_SESSION['pedido_message']);
$filter=is_string($_GET['estado']??null)?$_GET['estado']:'';
if ($filter!=='' && !in_array($filter,orderStates(),true)) { http_response_code(400);$error='Filtro de estado no válido.';$filter=''; }
$products=$conn->query("SELECT id_articulo,nombre,precio FROM articulo WHERE categoria='Arreglos' AND modalidad IN ('Por encargo','Ambas') ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$where=[];$params=[];
if ($user['rol']!=='Administrador') { $where[]='p.usuario_id=?';$params[]=$user['id']; }
if ($filter!=='') { $where[]='p.estado=?';$params[]=$filter; }
$stmt=$conn->prepare('SELECT p.*,CAST(p.precio_unitario*p.cantidad AS DECIMAL(20,2)) AS importe,u.username, (SELECT COALESCE(SUM(r.cantidad),0) FROM registros r WHERE r.venta_origen_id=p.venta_id AND r.tipo IN (\'Cancelación\',\'Devolución\')) AS corregidas FROM pedidos p LEFT JOIN usuarios u ON u.id=p.usuario_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY p.fecha_entrega,p.id');
$stmt->execute($params);$orders=$stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pedidos por encargo · Florería</title><link rel="stylesheet" href="../css/usuarios.css"><link rel="stylesheet" href="../css/catalogo.css"><link rel="stylesheet" href="../css/pedidos.css"></head>
<body><main class="catalog-page">
<a class="back-link" href="../menu.php">Volver al menú</a>
<header class="catalog-header"><div><span class="eyebrow">FLORERÍA / ENCARGOS</span><h1>Pedidos por encargo<span class="title-dot">.</span></h1><p>Organiza los arreglos y sus entregas. <?= $user['rol']==='Administrador'?'Consulta todos los pedidos.':'Consulta los pedidos que registraste.' ?></p></div></header>
<?php if ($message): ?><p class="notice notice-success" role="status"><?= esc($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice notice-error" role="alert"><?= esc($error) ?></p><?php endif; ?>
<section class="user-card catalog-list"><div class="list-heading"><h2>Nuevo pedido</h2></div>
<form method="post" class="product-form">
<input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>"><input type="hidden" name="accion" value="crear"><input type="hidden" name="operacion_id" value="<?= esc($values['operacion_id']) ?>">
<div class="form-grid">
<div class="field"><label for="cliente">Cliente</label><input id="cliente" name="cliente" maxlength="100" autocomplete="name" value="<?= esc($values['cliente']) ?>" required></div>
<div class="field"><label for="telefono">Teléfono</label><input id="telefono" name="telefono" type="tel" maxlength="30" autocomplete="tel" value="<?= esc($values['telefono']) ?>" required></div>
<div class="field"><label for="articulo_id">Arreglo</label><select id="articulo_id" name="articulo_id" required><option value="">Selecciona un arreglo</option><?php foreach ($products as $product): ?><option value="<?= (int)$product['id_articulo'] ?>" <?= (string)$product['id_articulo']===$values['articulo_id']?'selected':'' ?>><?= esc($product['nombre'].' · '.moneyAmount($product['precio']).' MXN por unidad') ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="cantidad">Cantidad de arreglos</label><input id="cantidad" name="cantidad" type="number" min="1" max="2147483647" step="1" value="<?= esc($values['cantidad']) ?>" required></div>
<div class="field"><label for="fecha_entrega">Fecha y hora de entrega</label><input id="fecha_entrega" name="fecha_entrega" type="datetime-local" value="<?= esc($values['fecha_entrega']) ?>" required><small>Hora de Ciudad de México.</small></div>
</div>
<p class="field-note">Al registrar se guarda el precio actual. El pedido no reserva materiales. El Administrador inicia la preparación: registra la venta y descuenta componentes una sola vez. No registres este encargo nuevamente desde Ventas.</p>
<div class="form-actions"><button <?= !$products?'disabled':'' ?>>Registrar pedido pendiente</button></div>
<?php if (!$products): ?><p>No hay arreglos para encargos. Configúralos desde Productos y existencias.</p><?php endif; ?>
</form></section>
<section class="user-card catalog-list"><div class="list-heading"><h2>Agenda de entregas</h2></div>
<form method="get" class="search"><label for="estado">Estado del pedido</label><div><select id="estado" name="estado"><option value="">Todos los estados</option><?php foreach (orderStates() as $state): ?><option <?= $state===$filter?'selected':'' ?>><?= esc($state) ?></option><?php endforeach; ?></select><button>Filtrar</button></div></form>
<div class="table-wrap"><table><thead><tr><th>Pedido / cliente</th><th>Arreglo</th><th>Entrega</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($orders as $order): ?>
<tr><td><strong>#<?= (int)$order['id'] ?> · <?= esc($order['cliente']) ?></strong><small><?= esc($order['telefono']) ?></small><small>Registró: <?= esc($order['username']??'Usuario anterior') ?></small></td>
<td><?= esc($order['nombre_snapshot']) ?><small><?= (int)$order['cantidad'] ?> arreglo(s) · <?= esc(moneyAmount($order['precio_unitario'])) ?> MXN c/u</small><small>Total acordado: <?= esc(moneyAmount($order['importe'])) ?> MXN</small></td>
<td><?= esc(date('d/m/Y H:i',strtotime($order['fecha_entrega']))) ?><small>Ciudad de México</small></td>
<td><span class="order-state"><?= esc($order['estado']) ?></span><?php if ($order['venta_id']): ?><small>Venta #<?= (int)$order['venta_id'] ?> · existencias ya descontadas</small><?php endif; ?><?php if ($order['corregidas']): ?><small>Unidades corregidas: <?= (int)$order['corregidas'] ?></small><?php endif; ?></td>
<td><div class="order-actions">
<?php
$actions=[];
if ($order['estado']==='Pendiente') {
    if ($user['rol']==='Administrador') $actions['En preparación']='Preparar y registrar venta';
    $actions['Cancelado']='Cancelar pedido';
} elseif ($order['estado']==='En preparación' && $user['rol']==='Administrador' && !$order['corregidas']) $actions['Listo']='Marcar listo';
elseif ($order['estado']==='Listo' && !$order['corregidas']) $actions['Entregado']='Marcar entregado';
if ($user['rol']==='Administrador' && in_array($order['estado'],['En preparación','Listo'],true) && (int)$order['corregidas']===(int)$order['cantidad']) $actions['Cancelado']='Cerrar pedido corregido';
foreach ($actions as $target=>$label): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>"><input type="hidden" name="accion" value="estado"><input type="hidden" name="pedido_id" value="<?= (int)$order['id'] ?>"><input type="hidden" name="version" value="<?= (int)$order['version'] ?>"><input type="hidden" name="operacion_id" value="<?= esc(operationToken()) ?>"><input type="hidden" name="estado" value="<?= esc($target) ?>"><button><?= esc($label) ?></button></form>
<?php endforeach; ?>
<?php if ($order['venta_id'] && $user['rol']==='Administrador'): ?><a class="edit-link" href="../Registros/devolucion.php?id=<?= (int)$order['venta_id'] ?>">Revisar / corregir venta</a><?php endif; ?>
<?php if (!$actions && !$order['venta_id']): ?><small>Sin acciones pendientes.</small><?php endif; ?>
</div></td></tr>
<?php endforeach; ?>
<?php if (!$orders): ?><tr><td colspan="5">No hay pedidos para esta consulta.</td></tr><?php endif; ?>
</tbody></table></div>
<p class="orders-note">Listo y Entregado no descuentan existencias. Para cancelar después de iniciar la preparación, el Administrador debe corregir toda la venta desde Registros y después cerrar el pedido. El cierre no devuelve materiales por sí mismo.</p>
</section></main></body></html>
