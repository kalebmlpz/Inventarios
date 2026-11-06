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
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['Gabriel']) && isset($_GET['password'])) {
    $Gabriel = $_GET['Gabriel'];
    $password = $_GET['password'];

    $sql = $dbConn->prepare("SELECT * FROM usuarios WHERE Gabriel = :Gabriel AND password = :password");
    $sql->bindParam(':Gabriel', $Gabriel);
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
