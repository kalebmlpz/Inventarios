<?php
require_once __DIR__ . '/inventario.php';
function saleForCorrection($conn, $id) {
    $stmt = $conn->prepare("SELECT r.*, (SELECT COALESCE(SUM(c.cantidad),0) FROM registros c WHERE c.venta_origen_id=r.id AND c.tipo IN ('Cancelación','Devolución')) AS cantidad_corregida FROM registros r WHERE r.id=? AND r.tipo='Venta'");
    $stmt->execute([$id]); $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sale || (int)$sale['cantidad'] < 1) throw new DomainException('La venta seleccionada no existe o no permite correcciones.');
    $sale['pendientes'] = (int)$sale['cantidad'] - (int)$sale['cantidad_corregida'];
    return $sale;
}
function correctSale($conn, $actor, $saleId, $input) {
    $quantity = filter_var($input['cantidad'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2147483647]]);
    $type = $input['tipo'] ?? '';
    $treatment = $input['tratamiento'] ?? '';
    $reason = is_string($input['motivo'] ?? null) ? trim($input['motivo']) : '';
    $token = $input['operacion_id'] ?? '';
    if (!$quantity || !in_array($type,['Cancelación','Devolución'],true) || !in_array($treatment,['sin_stock','producto','captura'],true)) throw new DomainException('Revisa el tipo, la cantidad y el tratamiento de existencias.');
    if ($reason === '' || strlen($reason) > 400) throw new DomainException('Escribe un motivo de hasta 400 caracteres; si es demasiado largo, reduce el texto.');
    if (($input['confirmar'] ?? '') !== '1') throw new DomainException('Confirma que revisaste la operación y la mercancía.');
    if ($type === 'Devolución' && $treatment === 'captura') throw new DomainException('Una devolución no puede devolver componentes que ya se usaron.');
    if (!is_string($token) || !preg_match('/^[a-zA-Z0-9-]{16,64}$/',$token)) throw new DomainException('Recarga la página e inténtalo de nuevo.');
    $hash = hash('sha256',json_encode([$saleId,$quantity,$type,$treatment,$reason]));
    try {
        inventoryLock($conn);
        $existing = existingOperation($conn,$token,$actor);
        if ($existing) {
            if (!hash_equals($existing['solicitud_hash'] ?? '',$hash)) throw new DomainException('Esta operación ya se utilizó con otros datos. Abre un nuevo formulario.');
        } else {
            $sale = saleForCorrection($conn,$saleId);
            if ($quantity > $sale['pendientes']) throw new DomainException('La cantidad supera las unidades pendientes de esta venta: '.$sale['pendientes'].'.');
            $original = ['id_articulo'=>$sale['articulo_id'],'sku'=>$sale['sku'],'nombre'=>$sale['nombre_snapshot'] ?? 'Producto de la venta original','unidad'=>$sale['unidad_snapshot'],'categoria'=>$sale['categoria_snapshot']];
            $record = recordOperation($conn,$original,$quantity,$type,$actor,date('Y-m-d'),$token,$sale['modo']);
            if ($sale['precio_unitario']!==null && $sale['importe']!==null && $sale['moneda']!==null) recordSalePrice($conn,$record,$sale['precio_unitario'],$sale['moneda']);
            $stmt = $conn->prepare('UPDATE registros SET venta_origen_id=?,tratamiento=?,motivo=?,solicitud_hash=? WHERE id=?');
            $stmt->execute([$saleId,$treatment,$reason,$hash,$record]);
            if ($treatment === 'producto') {
                if (!$sale['articulo_id']) throw new DomainException('Esta venta es anterior a la identificación de productos. Usa Sin reintegro y registra por separado únicamente la mercancía que compruebes.');
                $product = restorableProduct($conn,$sale['articulo_id'],$sale['unidad_snapshot'],$sale['categoria_snapshot']);
                changeStock($conn,$product,$quantity,$record);
            } elseif ($treatment === 'captura') {
                $stmt = $conn->prepare('SELECT * FROM movimientos WHERE registro_id=? ORDER BY articulo_id');
                $stmt->execute([$saleId]); $movements=$stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!$movements) throw new DomainException('Esta venta no tiene movimientos identificados para revertir. Usa Sin reintegro y registra por separado únicamente la mercancía que compruebes.');
                foreach ($movements as $movement) {
                    if (!$movement['articulo_id'] || (int)$movement['cantidad'] >= 0 || abs((int)$movement['cantidad']) % (int)$sale['cantidad'] !== 0) throw new DomainException('No se puede reconstruir el consumo de esta venta de forma segura. Usa Sin reintegro.');
                    $product = restorableProduct($conn,$movement['articulo_id'],$movement['unidad_snapshot'],$movement['categoria_snapshot']);
                    $units = intdiv(abs((int)$movement['cantidad']),(int)$sale['cantidad']) * $quantity;
                    if ($units > 2147483647) throw new DomainException('La cantidad supera el límite permitido.');
                    changeStock($conn,$product,$units,$record);
                }
            }
        }
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $e;
    }
}

function restorableProduct($conn, $id, $unit, $category) {
    $product = inventoryProduct($conn, $id);
    if ($unit === null || $category === null || $product['unidad'] !== $unit || $product['categoria'] !== $category) throw new DomainException('La unidad o categoría del producto cambió. No se reintegrarán cantidades automáticamente; comprueba la mercancía antes de registrar una entrada.');
    if ($product['categoria'] === 'Arreglos' && !in_array($product['modalidad'], ['Preparado','Ambas'], true)) throw new DomainException('Para reintegrar un arreglo terminado, configura su modalidad como Ambas. No se reintegran sus flores sueltas.');
    return $product;
}
