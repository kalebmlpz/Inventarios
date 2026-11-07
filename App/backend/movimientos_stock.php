<?php
require_once __DIR__ . '/inventario.php';
function movementReasons($type) {
    return $type === 'entrada' ? ['Compra', 'Recepción de mercancía', 'Otro'] : ['Marchitez', 'Daño', 'Desperdicio', 'Otro'];
}
function registerStockMovement($conn, $actor, $type, $input) {
    if (!in_array($type, ['entrada', 'merma'], true)) throw new DomainException('Tipo de movimiento inválido.');
    $id = filter_var($input['producto_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $quantity = filter_var($input['cantidad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
    $reason = is_string($input['motivo'] ?? null) ? trim($input['motivo']) : '';
    $detail = is_string($input['detalle'] ?? null) ? trim($input['detalle']) : '';
    $token = $input['operacion_id'] ?? '';
    if (!$id || !$quantity || !in_array($reason, movementReasons($type), true)) throw new DomainException('Selecciona un producto, una cantidad entera mayor a cero y un motivo.');
    if (strlen($detail) > 400) throw new DomainException('El detalle es demasiado largo. Reduce el texto.');
    if ($reason === 'Otro' && $detail === '') throw new DomainException('Describe el motivo en el campo Detalle.');
    if (!is_string($token) || !preg_match('/^[a-zA-Z0-9-]{16,64}$/', $token)) throw new DomainException('Recarga la página e inténtalo de nuevo.');
    $description = $reason . ($detail !== '' ? ': ' . $detail : '');
    $hash = hash('sha256', json_encode([$id, $quantity, $type, $reason, $detail]));
    $recordType = $type === 'entrada' ? 'Entrada' : 'Merma';
    try {
        inventoryLock($conn);
        $existing = existingOperation($conn, $token, $actor);
        if ($existing) {
            if ($existing['tipo'] !== $recordType || !hash_equals($existing['solicitud_hash'] ?? '', $hash)) throw new DomainException('La operación ya se utilizó con otros datos. Abre un nuevo formulario.');
        } else {
            $product = inventoryProduct($conn, $id);
            if ($type === 'entrada' && $product['categoria'] === 'Arreglos') throw new DomainException('Para sumar arreglos terminados, usa Componentes / Preparar.');
            $record = recordOperation($conn, $product, $quantity, $recordType, $actor, date('Y-m-d'), $token);
            $stmt = $conn->prepare('UPDATE registros SET motivo=?, solicitud_hash=? WHERE id=?');
            $stmt->execute([$description, $hash, $record]);
            changeStock($conn, $product, $type === 'entrada' ? $quantity : -$quantity, $record);
        }
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $e;
    }
}
