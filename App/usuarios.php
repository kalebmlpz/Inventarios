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
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear usuario · Inventarios</title>
    <link rel="stylesheet" href="./css/usuarios.css">
</head>
<body>
<main class="users-page">
    <a class="back-link" href="./menu.php"><span aria-hidden="true">←</span> Volver al menú</a>
    <section class="user-card" aria-labelledby="form-title">
        <div class="card-content">
            <form method="post" class="user-form">
                <h1 id="form-title">Crear usuario</h1>
                <?php if ($message !== ''): ?>
                <p class="notice <?= $message === 'Usuario creado correctamente.' ? 'notice-success' : 'notice-error' ?>" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                <div class="field">
                    <label for="new-username">Nombre de usuario</label>
                    <input id="new-username" name="username" maxlength="50" required autocomplete="off" placeholder="Ej. cajero01">
                    <small>Será el nombre que usará para iniciar sesión.</small>
                </div>
                <div class="field">
                    <label for="new-password">Contraseña</label>
                    <input id="new-password" type="password" name="password" minlength="8" required autocomplete="new-password" placeholder="Al menos 8 caracteres" aria-describedby="password-hint">
                    <small id="password-hint">Combina letras, números y símbolos.</small>
                </div>
                <div class="field">
                    <label for="new-role">Rol del usuario</label>
                    <select id="new-role" name="rol" aria-describedby="role-hint"><option>Cajero</option><option>Administrador</option></select>
                    <small id="role-hint">Asigna solo los permisos que necesita.</small>
                </div>
                <button type="submit">Crear usuario <span aria-hidden="true">→</span></button>
            </form>

        </div>
    </section>
</main>
</body>
</html>
