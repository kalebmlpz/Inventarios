<?php
require_once __DIR__.'/inventario.php';
function orderStates() { return ['Pendiente','En preparación','Listo','Entregado','Cancelado']; }
function orderToken($input) {
    $token=$input['operacion_id']??null;
    if (!is_string($token) || !preg_match('/^[a-zA-Z0-9-]{16,64}$/',$token)) throw new DomainException('Recarga el formulario e inténtalo de nuevo.');
    return $token;
}
function orderNumber($value) {
    $number=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
    if (!$number) throw new DomainException('Revisa el arreglo y la cantidad.');
    return $number;
}
function orderAccess($order,$user) {
    if ($user['rol']!=='Administrador' && (int)$order['usuario_id']!==(int)$user['id']) throw new DomainException('Pedido no disponible.');
}
function orderDelivery($delivery) {
    $parsed=is_string($delivery) && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}\z/',$delivery) ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$delivery) : false;
    if (!$parsed || $parsed->format('Y-m-d\TH:i')!==$delivery) throw new DomainException('Revisa la fecha y hora de entrega.');
    return $parsed;
}
function createOrder($conn,$user,$input) {
    $token=orderToken($input);
    $customer=is_string($input['cliente']??null)?trim($input['cliente']):'';
    $phone=is_string($input['telefono']??null)?trim($input['telefono']):'';
    if (!preg_match('/\A[^\x00-\x1f]{1,100}\z/u',$customer)) throw new DomainException('Escribe el cliente (hasta 100 caracteres).');
    if (!preg_match('/^[+0-9() .-]{7,30}$/',$phone) || strlen(preg_replace('/\D/','',$phone))<7) throw new DomainException('Escribe un teléfono válido de 7 a 30 caracteres.');
    $productId=orderNumber($input['articulo_id']??null); $quantity=orderNumber($input['cantidad']??null);
    $delivery=$input['fecha_entrega']??null;
    $parsed=orderDelivery($delivery);
    $hash=hash('sha256',json_encode([$customer,$phone,$productId,$quantity,$delivery]));
    try {
        inventoryLock($conn);
        $stmt=$conn->prepare('SELECT * FROM pedidos WHERE operacion_id=?');$stmt->execute([$token]);$existing=$stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            if ((int)$existing['usuario_id']!==(int)$user['id'] || !hash_equals($existing['solicitud_hash'],$hash)) throw new DomainException('Esta solicitud ya se utilizó con otros datos.');
            $conn->commit(); return $existing['id'];
        }
        if ($parsed < new DateTimeImmutable('now')) throw new DomainException('La entrega debe ser una fecha y hora futura.');
        $product=inventoryProduct($conn,$productId);
        if ($product['categoria']!=='Arreglos' || !in_array($product['modalidad'],['Por encargo','Ambas'],true)) throw new DomainException('Elige un arreglo habilitado para encargos.');
        $stmt=$conn->prepare('INSERT INTO pedidos(cliente,telefono,articulo_id,nombre_snapshot,cantidad,precio_unitario,fecha_entrega,usuario_id,creado_en,actualizado_en,operacion_id,solicitud_hash) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
        $now=date('Y-m-d H:i:s');
        $stmt->execute([$customer,$phone,$productId,$product['nombre'],$quantity,$product['precio'],$parsed->format('Y-m-d H:i:s'),$user['id'],$now,$now,$token,$hash]);
        $id=$conn->lastInsertId();$conn->commit();return $id;
    } catch (Throwable $e) { if ($conn->inTransaction()) $conn->rollBack(); throw $e; }
}
function transitionOrder($conn,$user,$input) {
    $token=orderToken($input);$id=orderNumber($input['pedido_id']??null);
    $target=$input['estado']??null;
    $version=filter_var($input['version']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
    if ($version===false || !in_array($target,orderStates(),true)) throw new DomainException('Revisa el estado del pedido.');
    $hash=hash('sha256',json_encode([$id,$version,$target]));
    try {
        inventoryLock($conn);
        $stmt=$conn->prepare('SELECT * FROM pedidos WHERE id=?');$stmt->execute([$id]);$order=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) throw new DomainException('Pedido no disponible.');
        orderAccess($order,$user);
        $stmt=$conn->prepare('SELECT * FROM pedidos_eventos WHERE operacion_id=?');$stmt->execute([$token]);$event=$stmt->fetch(PDO::FETCH_ASSOC);
        if ($event) {
            if ((int)$event['usuario_id']!==(int)$user['id'] || !hash_equals($event['solicitud_hash'],$hash)) throw new DomainException('Esta solicitud ya se utilizó con otros datos.');
            $conn->commit();return;
        }
        if ((int)$order['version']!==$version) throw new DomainException('El pedido cambió. Recarga antes de continuar.');
        $allowed=['Pendiente'=>['En preparación','Cancelado'],'En preparación'=>['Listo','Cancelado'],'Listo'=>['Entregado','Cancelado'],'Entregado'=>[],'Cancelado'=>[]];
        if (!in_array($target,$allowed[$order['estado']],true)) throw new DomainException('El cambio de estado no está permitido.');
        if (in_array($target,['En preparación','Listo'],true) && $user['rol']!=='Administrador') throw new DomainException('Solo el Administrador puede preparar el encargo.');
        $sale=$order['venta_id'];
        if ($target==='Cancelado' && $sale) {
            if ($user['rol']!=='Administrador') throw new DomainException('Solo el Administrador puede cerrar un pedido corregido.');
            $stmt=$conn->prepare("SELECT COALESCE(SUM(cantidad),0) FROM registros WHERE venta_origen_id=? AND tipo IN ('Cancelación','Devolución')");$stmt->execute([$sale]);
            if ((int)$stmt->fetchColumn()!==(int)$order['cantidad']) throw new DomainException('Corrige primero toda la venta desde Registros antes de cerrar el pedido.');
        }
        if ($target==='En preparación') {
            if ($sale) throw new DomainException('Este pedido ya tiene una venta registrada.');
            $product=inventoryProduct($conn,$order['articulo_id']);
            if ($product['categoria']!=='Arreglos' || !in_array($product['modalidad'],['Por encargo','Ambas'],true)) throw new DomainException('El arreglo ya no admite encargos. Revisa el catálogo.');
            // El precio y nombre acordados se conservan aunque cambie el catálogo.
            $product['nombre']=$order['nombre_snapshot'];
            $sale=recordOperation($conn,$product,(int)$order['cantidad'],'Venta',$user['id'],date('Y-m-d'),operationToken(),'Por encargo');
            recordSalePrice($conn,$sale,$order['precio_unitario'],saleCurrency());
            consumeRecipe($conn,$product,(int)$order['cantidad'],$sale);
        }
        if ($sale && in_array($target,['Listo','Entregado'],true)) {
            $stmt=$conn->prepare('SELECT COUNT(*) FROM registros WHERE venta_origen_id=?');$stmt->execute([$sale]);
            if ($stmt->fetchColumn()) throw new DomainException('La venta tiene correcciones. Revisa sus registros antes de entregar; este pedido no puede continuar.');
        }
        $now=date('Y-m-d H:i:s');
        $stmt=$conn->prepare('UPDATE pedidos SET estado=?,version=version+1,venta_id=?,actualizado_en=? WHERE id=?');$stmt->execute([$target,$sale,$now,$id]);
        $stmt=$conn->prepare('INSERT INTO pedidos_eventos(pedido_id,usuario_id,estado,creado_en,operacion_id,solicitud_hash) VALUES(?,?,?,?,?,?)');$stmt->execute([$id,$user['id'],$target,$now,$token,$hash]);
        $conn->commit();
    } catch (Throwable $e) { if ($conn->inTransaction()) $conn->rollBack(); throw $e; }
}

function orderPaymentTotalSql() {
    return '(SELECT COALESCE(SUM(importe),0) FROM pedidos_pagos WHERE pedido_id=p.id)';
}

function orderCanReceivePayment($order) {
    return $order['estado']!=='Cancelado' && !(int)$order['corregidas'];
}

function updateOrderDetails($conn,$user,$id,$input) {
    $token=orderToken($input);
    $action=$input['accion']??null;
    $editing=$action==='editar';
    if (!$editing && $action!=='pago') throw new DomainException('Acción no permitida.');
    $version=filter_var($input['version']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
    if ($version===false) throw new DomainException('Recarga el pedido antes de continuar.');
    $amount='0.00';
    if ($editing) {
        $quantity=orderNumber($input['cantidad']??null);
        $delivery=orderDelivery($input['fecha_entrega']??null);
        $hash=hash('sha256',json_encode(['editar',$id,$version,$quantity,$delivery->format('Y-m-d H:i:s')]));
    } else {
        $raw=$input['importe']??null;
        if (!is_string($raw) || !preg_match('/\A(\d{1,18})(?:\.(\d{1,2}))?\z/',$raw,$parts)) {
            throw new DomainException('Escribe un importe con hasta dos decimales.');
        }
        $amount=(ltrim($parts[1],'0') ?: '0').'.'.str_pad($parts[2]??'',2,'0');
        if ($amount==='0.00') throw new DomainException('El pago debe ser mayor que cero.');
    }

    try {
        inventoryLock($conn);
        $order=loadOrderDetails($conn,$id);
        orderAccess($order,$user);
        $table=$editing?'pedidos_eventos':'pedidos_pagos';
        $stmt=$conn->prepare("SELECT * FROM $table WHERE operacion_id=?");
        $stmt->execute([$token]);
        $receipt=$stmt->fetch(PDO::FETCH_ASSOC);
        if ($receipt) {
            $sameRequest=$editing ? hash_equals($receipt['solicitud_hash'],$hash) : $receipt['importe']===$amount;
            if ((int)$receipt['pedido_id']!==$id || (int)$receipt['usuario_id']!==(int)$user['id'] || !$sameRequest) {
                throw new DomainException('Esta solicitud ya se utilizó con otros datos.');
            }
            $conn->commit();
            return;
        }
        if ((int)$order['version']!==$version) throw new DomainException('El pedido cambió. Recarga antes de continuar.');
        if ($editing && $order['estado']!=='Pendiente') throw new DomainException('Solo se pueden editar pedidos pendientes.');
        if (!$editing && !orderCanReceivePayment($order)) throw new DomainException('Este pedido no admite pagos.');
        if ($editing && $delivery->format('Y-m-d H:i:s')!==$order['fecha_entrega'] && $delivery<=new DateTimeImmutable('now')) {
            throw new DomainException('La nueva fecha de entrega debe ser futura.');
        }
        $paid=orderPaymentTotalSql();
        $stmt=$conn->prepare("SELECT precio_unitario * CAST(? AS SIGNED) >= $paid + CAST(? AS DECIMAL(20,2)) FROM pedidos p WHERE p.id=?");
        $stmt->execute([$quantity??$order['cantidad'],$amount,$id]);
        if (!(int)$stmt->fetchColumn()) {
            throw new DomainException($editing?'El nuevo total es menor que los pagos registrados.':'El pago supera el saldo pendiente.');
        }
        $now=date('Y-m-d H:i:s');
        if ($editing) {
            $stmt=$conn->prepare('UPDATE pedidos SET cantidad=?,fecha_entrega=?,version=version+1,actualizado_en=? WHERE id=?');
            $stmt->execute([$quantity,$delivery->format('Y-m-d H:i:s'),$now,$id]);
            $stmt=$conn->prepare("INSERT INTO pedidos_eventos(pedido_id,usuario_id,estado,creado_en,operacion_id,solicitud_hash) VALUES(?,?,'Pendiente',?,?,?)");
            $stmt->execute([$id,$user['id'],$now,$token,$hash]);
        } else {
            $stmt=$conn->prepare('INSERT INTO pedidos_pagos(pedido_id,usuario_id,importe,creado_en,operacion_id) VALUES(?,?,?,?,?)');
            $stmt->execute([$id,$user['id'],$amount,$now,$token]);
            $stmt=$conn->prepare('UPDATE pedidos SET version=version+1,actualizado_en=? WHERE id=?');
            $stmt->execute([$now,$id]);
        }
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $e;
    }
}

function loadOrderDetails($conn,$id) {
    $paid=orderPaymentTotalSql();
    $stmt=$conn->prepare("SELECT p.*, CAST(p.precio_unitario*p.cantidad AS DECIMAL(20,2)) AS importe,
        $paid AS pagado, CAST(p.precio_unitario*p.cantidad-$paid AS DECIMAL(20,2)) AS saldo,
        (SELECT COUNT(*) FROM registros WHERE venta_origen_id=p.venta_id) AS corregidas
        FROM pedidos p WHERE p.id=?");
    $stmt->execute([$id]);
    $order=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new DomainException('Pedido no disponible.');
    return $order;
}
