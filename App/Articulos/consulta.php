<?php
require_once __DIR__ . '/../backend/auth.php';
$currentUser = require_access(['Administrador', 'Cajero'], false);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>CONSULTA</title>

        <!-- CDN de Boostrap -->
        <link rel="stylesheet" href="./bootstrap/bootstrap.min.css">
        <link rel="stylesheet" href="./css/styles.css">
    </head>

    <body>
        <div class="text-center mt-5">
            <h1 class="h1">Punto de venta</h1>
        </div>
        <!-- Navegación -->
        <nav class="navbar">
            <div class="container">
<?php if ($currentUser['rol'] === 'Administrador'): ?>                <button type="button" class="btn btn-success w-22 p-0"><a class="nav-link p-3" href="./alta.php">ALTA</a></button><?php endif; ?>
<?php if ($currentUser['rol'] === 'Administrador'): ?>                <button type="button" class="btn btn-danger w-22 p-0"><a class="nav-link p-3" href="./baja.php">BAJA</a></button><?php endif; ?>
<?php if ($currentUser['rol'] === 'Administrador'): ?>                <button type="button" class="btn btn-warning w-22 p-0"><a class="nav-link p-3" href="./cambio.php">CAMBIO</a></button><?php endif; ?>
                <button type="button" class="btn btn-info w-22 p-0"><a class="nav-link p-3" href="./consulta.php">CONSULTA</a></button>
                <button type="button" class="btn w-12 p-0"><a class="nav-link p-3" href="../menu.php">&LongLeftArrow;</a></button>
            </div>
        </nav>

        <div class="container-sm">
            <!-- Aplicación -->
            <div id="App" class="row pt-5 card">
                <div>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4 class="text-center">Consulta Artículo</h4>
                        </div>

                        <form id="formulario-articulos" class="card-body d-grid gap-2">

                            <div class="form-group">
                                <input type="number" id="sku" placeholder="SKU" class="form-control mb-2">
                            </div>

                            <input type="submit" value="Validar" class="btn btn-primary mt-2">

                        </form>
                    </div>

                <div class="card-body" id="lista-productos"></div>
        </div>

        <!-- Sripts -->
        <script src="./js/app.js"></script>
        <script src="./js/consulta.js"></script>
    </body>
</html>
