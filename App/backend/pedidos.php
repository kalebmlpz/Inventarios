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
function createOrder($conn,$user,$input) {
    $token=orderToken($input);
    $customer=is_string($input['cliente']??null)?trim($input['cliente']):'';
    $phone=is_string($input['telefono']??null)?trim($input['telefono']):'';
    if (!preg_match('/\A[^\x00-\x1f]{1,100}\z/u',$customer)) throw new DomainException('Escribe el cliente (hasta 100 caracteres).');
    if (!preg_match('/^[+0-9() .-]{7,30}$/',$phone) || strlen(preg_replace('/\D/','',$phone))<7) throw new DomainException('Escribe un teléfono válido de 7 a 30 caracteres.');
    $productId=orderNumber($input['articulo_id']??null); $quantity=orderNumber($input['cantidad']??null);
    $delivery=$input['fecha_entrega']??null;
    $parsed=is_string($delivery) && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}\z/',$delivery) ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$delivery) : false;
    if (!$parsed || $parsed->format('Y-m-d\TH:i')!==$delivery) throw new DomainException('Revisa la fecha y hora de entrega.');
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
