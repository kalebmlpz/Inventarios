<?php
    include "config.php";
    include "utils.php";

    header('Access-Control-Allow-Origin: *');
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

    $dbConn =  connect($db);

    if ($_SERVER['REQUEST_METHOD'] == 'GET')
    {
        // CONSULTAR FECHA
        if(isset($_GET['fecha']))
        {
            // Mostrar los posts
            $sql = $dbConn->prepare("SELECT * FROM registros WHERE fecha=:fecha");
            $sql->bindValue(':fecha', $_GET['fecha']);
            $sql->execute();
            $results = $sql->fetchAll(PDO::FETCH_ASSOC);
            header("HTTP/1.1 200 OK");
            echo json_encode($results);
            exit();
        }

        // //  LISTA DE ARTICULOS
        // $sql = $dbConn->prepare("SELECT * FROM registros");
        // $sql->execute();
        // $sql->setFetchMode(PDO::FETCH_ASSOC);
        // header("HTTP/1.1 200 OK");
        // echo json_encode( $sql->fetchAll()  );
        // exit();

    }


    header("HTTP/1.1 400 Bad Request");
?>