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
    
                        <a href="./Pedidos/pedidos.php">
                            <div class="menu-item">
                                <div class="dis-block">
                                    <svg class="reports-icon" viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                        <rect x="12" y="18" width="76" height="72" rx="5"/>
                                        <path d="M12 36h76M30 10v16M70 10v16M50 73v10M50 80l-10-5"/>
                                        <path d="M50 51c-13-15-24 2-12 9-12 10 3 23 12 11 9 12 24-1 12-11 12-7 1-24-12-9z"/>
                                        <circle cx="50" cy="62" r="5"/>
                                    </svg>
                                    <h3 class="text-center">Pedidos por encargo</h3>
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
                <?php if ($currentUser['rol'] === 'Administrador'): ?><a class="menu-action users-action" href="./usuarios.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 4a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 4v3"/></svg>
                    <span>Administrar usuarios</span>
                </a><?php endif; ?>
                <a class="menu-action logout-action" href="./backend/logout.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M10 4H4v16h6M9 12h12m-4-4 4 4-4 4"/></svg>
                    <span>Cerrar sesión</span>
                </a>
                </div>
            </div>
        </div>
    </body>
</html>