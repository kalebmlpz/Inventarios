<?php
require_once __DIR__ . '/auth.php';
$currentUser = require_access(['Administrador', 'Cajero']);
require 'config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['message' => 'Método no permitido']);
    exit;
}
$sku = $_POST['sku'] ?? null;
$cantidad = filter_var($_POST['cantidad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$fecha = $_POST['fecha'] ?? '';
$parsed = is_string($fecha) ? DateTimeImmutable::createFromFormat('!Y-m-d', $fecha) : false;
if (!is_string($sku) || !ctype_digit($sku) || !$cantidad || !$parsed || $parsed->format('Y-m-d') !== $fecha) {
    http_response_code(400);
    echo json_encode(['message' => 'SKU, cantidad o fecha inválidos']);
    exit;
}
try {
    $conn = new PDO("mysql:host={$db['host']};dbname={$db['db']};charset=utf8mb4", $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $statement = $conn->prepare('INSERT INTO registros (sku, cantidad, fecha, usuario_id) VALUES (:sku, :cantidad, :fecha, :usuario_id)');
    $statement->execute(['sku' => $sku, 'cantidad' => $cantidad, 'fecha' => $fecha, 'usuario_id' => $currentUser['id']]);
    http_response_code(201);
    echo json_encode(['message' => 'OK', 'sku' => $sku, 'cantidad' => $cantidad, 'fecha' => $fecha, 'usuario_id' => $currentUser['id']]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'No se pudo guardar la venta']);
}
