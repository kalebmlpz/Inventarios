<?php
require 'config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$fecha = $_GET['fecha'] ?? '';
$parsed = is_string($fecha) ? DateTimeImmutable::createFromFormat('!Y-m-d', $fecha) : false;
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !$parsed || $parsed->format('Y-m-d') !== $fecha) {
    http_response_code(400);
    echo json_encode(['message' => 'Selecciona una fecha válida']);
    exit;
}
try {
    $conn = new PDO("mysql:host={$db['host']};dbname={$db['db']};charset=utf8mb4", $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $statement = $conn->prepare('SELECT r.sku, r.cantidad, r.fecha, a.nombre FROM registros r LEFT JOIN (SELECT sku, MAX(nombre) AS nombre FROM articulo GROUP BY sku) a ON a.sku = r.sku WHERE r.fecha = :fecha');
    $statement->execute(['fecha' => $fecha]);
    echo json_encode($statement->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'No se pudieron consultar los registros']);
}
