<?php
require_once __DIR__ . '/auth.php';
$currentUser = require_access(['Administrador']);
    include "config.php";
    include "utils.php";

    // header("HTTP/1.1 200");

    header('Access-Control-Allow-Origin: *');
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

    $dbConn =  connect($db);

    //  Borrar
    if ($_SERVER['REQUEST_METHOD'] == 'DELETE')
    {
        $sku = $_GET['sku'];
        $statement = $dbConn->prepare("DELETE FROM articulo where sku=:sku");
        $statement->bindValue(':sku', $sku);
        $statement->execute();
        header("HTTP/1.1 200 OK");
        exit();
    }
    else
    {
        //    Mostrar lista de post
        $sql = $dbConn->prepare("SELECT * FROM articulo");
        $sql->execute();
        $sql->setFetchMode(PDO::FETCH_ASSOC);
        header("HTTP/1.1 200 OK");
        echo json_encode( $sql->fetchAll()  );
        exit();
	}

    header("HTTP/1.1 400 Bad Request");

?>