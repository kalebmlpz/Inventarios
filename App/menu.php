<?php
require_once __DIR__ . '/backend/auth.php';
$currentUser = require_access(['Administrador', 'Cajero'], false);
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Menu</title>

        <!-- <link rel="stylesheet" href="./css/bootstrap.min.css"> -->
        <link rel="stylesheet" href="./css/styles.css">
        <link rel="stylesheet" href="./css/menu.css?v=<?= filemtime(__DIR__ . '/css/menu.css') ?>">
    </head>
    <body>
        <div class="display-center">
            <div class="menu-panel <?= $currentUser['rol'] === 'Administrador' ? 'menu-panel-admin' : '' ?>">
                <h1 class="text-primary text-center text-menu">Menú</h1>
                <p class="text-center"><?= $currentUser['rol'] ?></p>
                <div class="box">
                    <div class="menu <?= $currentUser['rol'] === 'Administrador' ? 'menu-admin' : '' ?>">
    
                        <a href="./Articulos/consulta.php">
                            <div class="menu-item">
                                <div class="dis-block">
                                    <div id="articulos"></div>
                                    <h3 class="text-center">Productos y existencias</h3>
                                </div>
                            </div>
                        </a>
    
                        <a href="./Ventas/ventas.php">
                            <div class="menu-item">
                                <div class="dis-block">
                                    <div id="ventas"></div>
                                    <h3 class="text-center">Ventas</h3>
                                </div>
                            </div>
                        </a>
    
                        <a href="./Registros/registros.php">
                            <div class="menu-item">
                                <div class="dis-block">
                                    <div id="registros"></div>
                                    <h3 class="text-center"><?= $currentUser['rol'] === 'Cajero' ? 'Mis operaciones' : 'Todos los registros' ?></h3>
                                </div>
                            </div>
                        </a>
    
                        <?php if ($currentUser['rol'] === 'Administrador'): ?>
                        <a href="./Reportes/reportes.php">
                            <div class="menu-item">
                                <div class="dis-block">
                                    <svg class="reports-icon" viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                        <path d="M22 8h39l18 18v64H22zM61 8v19h18"/>
                                        <path d="M34 76V61h10v15M50 76V48h10v28M66 76V38M33 35h16M33 44h8"/>
                                    </svg>
                                    <h3 class="text-center">Reportes</h3>
                                </div>
                            </div>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="menu-actions">
                <a class="menu-action" href="./Pedidos/pedidos.php">Pedidos por encargo</a>
                <?php if ($currentUser['rol'] === 'Administrador'): ?><a class="menu-action" href="./usuarios.php">Administrar usuarios</a><?php endif; ?>
                <a class="menu-action" href="./backend/logout.php">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </body>
</html>