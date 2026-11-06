<?php
require_once __DIR__ . '/auth.php';
$user = require_access(['Administrador']);
header('Content-Type: text/plain; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método no permitido');
}
if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    http_response_code(403);
    exit('Solicitud no permitida');
}
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(400);
    exit('Selecciona un producto válido');
}
try {
    require_once __DIR__ . '/inventario.php';
    $conn = inventoryDb(); inventoryLock($conn);
    $product = inventoryProduct($conn, $id);
    $record = recordOperation($conn, $product, -(int)$product['stock'], 'Baja del catálogo', $user['id'], date('Y-m-d'));
    if ((int)$product['stock'] > 0) changeStock($conn, $product, -(int)$product['stock'], $record);
    // El historial y los nombres originales se conservan tras la eliminación.
    $stmt = $conn->prepare('DELETE FROM articulo WHERE id_articulo = ?');
    $stmt->execute([$id]);
    if (!$stmt->rowCount()) {
        $conn->rollBack();
        http_response_code(404);
        exit('El producto ya no existe');
    }
    $conn->commit();
    header('Location: ../Articulos/consulta.php?eliminado=1', true, 303);
} catch (DomainException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    http_response_code(404);
    echo 'El producto ya no existe.';
} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    http_response_code(409);
    echo 'No se pudo eliminar: si el material se usa en un arreglo, retíralo primero de sus componentes.';
}
