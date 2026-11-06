<?php
require_once __DIR__ . '/backend/auth.php';
$currentUser = require_access(['Administrador'], false);
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403);
        exit('Solicitud inválida');
    }
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? '';
    if ($username === '' || strlen($username) > 50 || !is_string($password) || strlen($password) < 8 || !in_array($rol, ['Administrador', 'Cajero'], true)) {
        $message = 'Introduce un usuario, una contraseña de al menos 8 caracteres y un rol válido.';
    } else {
        require __DIR__ . '/backend/config.php';
        try {
            $conn = new PDO("mysql:host={$db['host']};dbname={$db['db']};charset=utf8mb4", $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $stmt = $conn->prepare('SELECT id FROM usuarios WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $message = 'Ese nombre de usuario ya existe.';
            } else {
                $stmt = $conn->prepare('INSERT INTO usuarios (username, password, rol) VALUES (?, ?, ?)');
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $rol]);
                $message = 'Usuario creado correctamente.';
            }
        } catch (PDOException $e) {
            $message = 'No se pudo crear el usuario.';
        }
    }
}
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>Usuarios</title><link rel="stylesheet" href="./css/bootstrap.min.css"></head>
<body><main class="container mt-5"><h1>Crear usuario</h1>
<p>Asigna los permisos del usuario. Los cajeros solo consultan existencias, registran ventas y ven sus propias operaciones.</p>
<p role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<form method="post">
<input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
<label class="d-block">Nombre de usuario<input class="form-control" name="username" maxlength="50" required autocomplete="off"></label>
<label class="d-block">Contraseña<input class="form-control" type="password" name="password" minlength="8" required autocomplete="new-password"></label>
<label class="d-block">Rol<select class="form-control" name="rol"><option>Cajero</option><option>Administrador</option></select></label>
<button class="btn btn-primary my-3">Crear usuario</button>
</form><a href="./menu.php">Volver al menú</a></main></body></html>
