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

$_SESSION = [];

$username = $_POST['username'] ?? null;
$password = $_POST['password'] ?? null;
if (!is_string($username) || !is_string($password) || $username === '' || $password === '' || str_contains($password, "\0")) {
    http_response_code(400);
    echo json_encode(['message' => 'Usuario y contraseña requeridos']);
    exit;
}

try {
    require_once __DIR__ . '/cuentas.php';
    $user = loginAccount(inventoryDb(), $username, $password);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['message' => 'Usuario o contraseña incorrectos']);
        exit;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['version_sesion'] = (int)$user['version_sesion'];
    http_response_code(200);
    echo json_encode(['message' => 'OK']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'No se pudo iniciar sesión. Comprueba la actualización de la base.']);
}
