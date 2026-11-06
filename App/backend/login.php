<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['message' => 'Método no permitido']);
    exit;
}

$username = $_POST['username'] ?? null;
$password = $_POST['password'] ?? null;
if (!is_string($username) || !is_string($password) || $username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['message' => 'Usuario y contraseña requeridos']);
    exit;
}

try {
    require 'config.php';
    $conn = new PDO("mysql:host={$db['host']};dbname={$db['db']};charset=utf8mb4", $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $sql = $conn->prepare('SELECT id, password, rol FROM usuarios WHERE username = :username');
    $sql->execute(['username' => $username]);
    $user = $sql->fetch(PDO::FETCH_ASSOC);
    if (!$user || !(password_verify($password, $user['password']) || hash_equals($user['password'], $password)) || !in_array($user['rol'], ['Administrador', 'Cajero'], true)) {
        http_response_code(401);
        echo json_encode(['message' => 'Usuario o contraseña incorrectos']);
        exit;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    http_response_code(200);
    echo json_encode(['message' => 'OK']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'No se pudo iniciar sesión. Inténtalo más tarde.']);
}
