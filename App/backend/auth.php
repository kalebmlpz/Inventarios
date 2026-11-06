<?php
function require_access($roles = ['Administrador', 'Cajero'], $api = true) {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    header('Cache-Control: no-store');
    try {
        require __DIR__ . '/config.php';
        $conn = new PDO("mysql:host={$db['host']};dbname={$db['db']};charset=utf8mb4", $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $stmt = $conn->prepare('SELECT id, username, rol FROM usuarios WHERE id = ?');
        $stmt->execute([$_SESSION['user_id'] ?? 0]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        http_response_code(503);
        exit('Servicio temporalmente no disponible. Comprueba la actualización de la base de datos.');
    }
    if (!$user) {
        if (!$api) {
            $base = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/');
            if (basename($_SERVER['SCRIPT_NAME']) === 'menu.php' || basename($_SERVER['SCRIPT_NAME']) === 'usuarios.php') $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
            header('Location: ' . $base . '/login.html');
        } else {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Inicia sesión']);
        }
        exit;
    }
    if (!in_array($user['rol'], $roles, true)) {
        http_response_code(403);
        exit('No tienes permiso para acceder a esta opción.');
    }
    return $user;
}
