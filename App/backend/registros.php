<?php
require_once __DIR__ . '/auth.php';
$currentUser = require_access(['Administrador', 'Cajero']);
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
    $query = 'SELECT r.sku, r.cantidad, r.fecha, a.nombre FROM registros r LEFT JOIN (SELECT sku, MAX(nombre) AS nombre FROM articulo GROUP BY sku) a ON a.sku = r.sku WHERE r.fecha = :fecha';
    $params = ['fecha' => $fecha];
    if ($currentUser['rol'] === 'Cajero') {
        $query .= ' AND r.usuario_id = :usuario_id';
        $params['usuario_id'] = $currentUser['id'];
    }
    $statement = $conn->prepare($query);
    $statement->execute($params);
    echo json_encode($statement->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'No se pudieron consultar los registros']);
}
