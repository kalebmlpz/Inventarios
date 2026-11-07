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
        <link rel="stylesheet" href="./css/menu.css">
    </head>
    <body>
        <div class="display-center">
            <div class="menu-panel">
                <h1 class="text-primary text-center text-menu">Menú</h1>
                <p class="text-center"><?= $currentUser['rol'] ?></p>
                <div class="box">
                    <div class="menu">
    
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
    
                    </div>
                </div>
                <div class="menu-actions">
                <?php if ($currentUser['rol'] === 'Administrador'): ?><a class="menu-action" href="./usuarios.php">Administrar usuarios</a><a class="menu-action" href="./Reportes/reportes.php">Reportes</a><?php endif; ?>
                <a class="menu-action" href="./backend/logout.php">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </body>
</html>