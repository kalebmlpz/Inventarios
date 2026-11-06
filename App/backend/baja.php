<?php
require_once __DIR__ . '/auth.php';
require_access(['Administrador']);
http_response_code(410);
header('Content-Type: application/json');
echo json_encode(['message'=>'Usa Productos y existencias para modificar el catálogo.']);
