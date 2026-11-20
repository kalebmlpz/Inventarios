<?php
require_once __DIR__.'/../backend/auth.php';
$user=require_access(['Administrador','Cajero'],false);
require_once __DIR__.'/../backend/pedidos.php';

function esc($value) { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }

$conn=inventoryDb();
$error='';
$order=null;
try {
    $id=orderNumber($_GET['id']??null);
    $candidate=loadOrderDetails($conn,$id);
    orderAccess($candidate,$user);
    $order=$candidate;
} catch (DomainException $e) {
    http_response_code(404);
    $error='Pedido no disponible.';
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=operationToken();
if ($order && $_SERVER['REQUEST_METHOD']==='POST') {
    csrfCheck();
    try {
        updateOrderDetails($conn,$user,$id,$_POST);
        $_SESSION['pedido_message']=($_POST['accion']==='editar')?'Pedido actualizado.':'Pago registrado.';
        header('Location: gestionar.php?id='.$id,true,303);
        exit;
    } catch (DomainException $e) {
        http_response_code(422);
        $error=$e->getMessage();
        $order=loadOrderDetails($conn,$id);
    } catch (PDOException $e) {
        http_response_code(500);
        $error='No se pudo guardar el cambio.';
    }
}
$message=$_SESSION['pedido_message']??'';
unset($_SESSION['pedido_message']);
$payments=[];
if ($order) {
    $stmt=$conn->prepare('SELECT p.importe,p.creado_en,u.username FROM pedidos_pagos p LEFT JOIN usuarios u ON u.id=p.usuario_id WHERE p.pedido_id=? ORDER BY p.id DESC');
    $stmt->execute([$id]);
    $payments=$stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Detalle del pedido · Florería</title>
    <?php foreach (['usuarios','catalogo','pedidos'] as $style): ?>
    <link rel="stylesheet" href="../css/<?= $style ?>.css?v=<?= filemtime(__DIR__.'/../css/'.$style.'.css') ?>">
    <?php endforeach; ?>
</head>
<body>
<main class="catalog-page clean-workspace">
<a class="back-link" href="pedidos.php?vista=agenda">Volver a la agenda</a>
<header class="catalog-header"><div>
<h1><?= $order?'Pedido #'.$id:'Detalle del pedido' ?></h1>
<?php if ($order): ?><p><?= esc($order['cliente']) ?> · <?= esc($order['estado']) ?> · <?= esc($order['nombre_snapshot']) ?></p><?php endif; ?>
</div></header>
<?php if ($error): ?><p class="notice notice-error" role="alert"><?= esc($error) ?></p><?php endif; ?>
<?php if ($order): ?>
<?php if ($message): ?><p class="notice notice-success" role="status"><?= esc($message) ?></p><?php endif; ?>
<?php if ($order['estado']==='Pendiente'): ?>
<section class="user-card catalog-list">
<div class="list-heading"><h2>Editar pedido</h2></div>
<form method="post" class="product-form">
<input type="hidden" name="accion" value="editar">
<input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>">
<input type="hidden" name="version" value="<?= (int)$order['version'] ?>">
<input type="hidden" name="operacion_id" value="<?= esc(operationToken()) ?>">
<div class="form-grid">
<div class="field"><label for="cantidad">Cantidad de arreglos</label><input id="cantidad" name="cantidad" type="number" min="1" max="2147483647" value="<?= (int)$order['cantidad'] ?>" required></div>
<div class="field"><label for="fecha_entrega">Fecha y hora de entrega</label><input id="fecha_entrega" name="fecha_entrega" type="datetime-local" value="<?= esc(date('Y-m-d\TH:i',strtotime($order['fecha_entrega']))) ?>" required><small>Hora de Ciudad de México.</small></div>
</div>
<p class="field-note">Precio acordado por arreglo: <?= esc(moneyAmount($order['precio_unitario'])) ?> MXN.</p>
<div class="form-actions"><button>Guardar cambios</button></div>
</form>
</section>
<?php endif; ?>
<section class="user-card catalog-list">
<div class="list-heading"><h2>Anticipos y pagos</h2></div>
<div class="orders-note">
<p>Total acordado: <strong><?= esc(moneyAmount($order['importe'])) ?> MXN</strong> · Pagado: <strong><?= esc(moneyAmount($order['pagado'])) ?> MXN</strong>
<?php if (orderCanReceivePayment($order)): ?> · Saldo pendiente: <strong><?= esc(moneyAmount($order['saldo'])) ?> MXN</strong><?php endif; ?></p>
<?php if (!orderCanReceivePayment($order)): ?><p>El historial conserva los importes recibidos. Cualquier devolución de dinero se gestiona por separado.</p><?php endif; ?>
</div>
<?php if ($payments): ?>
<ul class="order-payment-list">
<?php foreach ($payments as $payment): ?>
<li><?= esc(moneyAmount($payment['importe'])) ?> MXN · <?= esc(date('d/m/Y H:i',strtotime($payment['creado_en']))) ?> · <?= esc($payment['username']??'Usuario anterior') ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php if (orderCanReceivePayment($order) && $order['saldo']!=='0.00'): ?>
<form method="post" class="product-form">
<input type="hidden" name="accion" value="pago">
<input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>">
<input type="hidden" name="version" value="<?= (int)$order['version'] ?>">
<input type="hidden" name="operacion_id" value="<?= esc(operationToken()) ?>">
<div class="field"><label for="importe">Importe recibido (MXN)</label><input id="importe" name="importe" type="number" min="0.01" step="0.01" required></div>
<div class="form-actions"><button>Registrar pago</button></div>
</form>
<?php endif; ?>
</section>
<?php endif; ?>
</main>
</body>
</html>
