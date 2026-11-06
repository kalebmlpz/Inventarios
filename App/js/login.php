<?php
include "config.php";
include "utils.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

$dbConn = connect($db);

/*
  buscar usuario y contraseña en la base de datos
 */
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['username']) && isset($_GET['password'])) {
    $username = $_GET['username'];
    $password = $_GET['password'];

    $sql = $dbConn->prepare("SELECT * FROM usuarios WHERE username = :username AND password = :password");
    $sql->bindParam(':username', $username);
    $sql->bindParam(':password', $password);
    $sql->execute();
    
    $result = $sql->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        header("HTTP/1.1 200 OK");
        echo json_encode(array("message" => "OK"));
        exit();
    }
    // else
}

// En caso de que no se encuentre la coincidencia o no se hayan proporcionado los parámetros adecuados
header("HTTP/1.1 400 Bad Request");
?>
