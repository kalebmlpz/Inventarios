<?php
require_once __DIR__ . '/auth.php';
require_access(['Administrador']);
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
    require __DIR__ . '/config.php';
    $conn = new PDO("mysql:host={$db['host']};dbname={$db['db']};charset=utf8mb4", $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    // El historial de ventas se conserva; se elimina solo la ficha del catálogo.
    $stmt = $conn->prepare('DELETE FROM articulo WHERE id_articulo = ?');
    $stmt->execute([$id]);
    if (!$stmt->rowCount()) {
        http_response_code(404);
        exit('El producto ya no existe');
    }
    header('Location: ../Articulos/consulta.php?eliminado=1', true, 303);
} catch (PDOException $e) {
    http_response_code(500);
    echo 'No se pudo eliminar el producto. Inténtalo de nuevo.';
}
