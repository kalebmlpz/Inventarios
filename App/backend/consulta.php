<?php
    include "config.php";
    include "utils.php";

    header('Access-Control-Allow-Origin: *');
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

    $dbConn =  connect($db);



    if ($_SERVER['REQUEST_METHOD'] == 'GET')
    {
        //  CONSULTAR SKU
        if(isset($_GET['sku']))
        {
            //Mostrar un post
            $sql = $dbConn->prepare("SELECT * FROM articulo where sku=:sku");
            $sql->bindValue(':sku', $_GET['sku']);
            $sql->execute();
            header("HTTP/1.1 200 OK");
            echo json_encode(  $sql->fetch(PDO::FETCH_ASSOC)  );
            exit();
        }


        //  CONSULTAR ID ARTICULO

        if (isset($_GET['id_articulo']))
        {
            //Mostrar un articulo
            $sql = $dbConn->prepare("SELECT * FROM articulo where id_articulo=:id_articulo");
            $sql->bindValue(':id_articulo', $_GET['id_articulo']);
            $sql->execute();
            header("HTTP/1.1 200 OK");
            echo json_encode(  $sql->fetch(PDO::FETCH_ASSOC)  );
            exit();
        }
        
        //  CONSULTAR DEPARTAMENTOS

        if(isset($_GET['lista_departamentos']))
        {
            $sql = $dbConn->prepare("SELECT * FROM departamentos");
            $sql->execute();
            $sql->setFetchMode(PDO::FETCH_ASSOC);
            header("HTTP/1.1 200 OK");
            echo json_encode( $sql->fetchAll()  );
            exit();
        }

        //  CONSULTAR FAMILIA
        if(isset($_GET['departamento']) && isset($_GET['clase']) && isset($_GET['familia']))
        {
            $sql = $dbConn->prepare("SELECT * FROM familias WHERE num_familia=:familia AND num_clase=:clase AND id_dep=:departamento");
            $sql->bindValue(':clase', $_GET['clase']);
            $sql->bindValue(':departamento', $_GET['departamento']);
            $sql->bindValue(':familia', $_GET['familia']);
            $sql->execute();
            $result_familias = $sql->fetchAll(PDO::FETCH_ASSOC);
        
            $sql = $dbConn->prepare("SELECT * FROM clases WHERE num_clase=:clase AND id_dep=:departamento");
            $sql->bindValue(':clase', $_GET['clase']);
            $sql->bindValue(':departamento', $_GET['departamento']);
            $sql->execute();
            $result_clases = $sql->fetchAll(PDO::FETCH_ASSOC);
        
            $sql = $dbConn->prepare("SELECT * FROM departamentos WHERE id_dep=:departamento");
            $sql->bindValue(':departamento', $_GET['departamento']);
            $sql->execute();
            $result_departamentos = $sql->fetchAll(PDO::FETCH_ASSOC);
        
            $result = array(
                "familias" => $result_familias,
                "clases" => $result_clases,
                "departamentos" => $result_departamentos
            );
        
            header("HTTP/1.1 200 OK");
            echo json_encode($result);
            exit();
        }

        //  CONSULTAR FAMILIAS
        if(isset($_GET['departamento']) && isset($_GET['clase']))
        {
            $sql = $dbConn->prepare("SELECT * FROM familias WHERE num_clase=:clase AND id_dep=:departamento");
            $sql->bindValue(':clase', $_GET['clase']);
            $sql->bindValue(':departamento', $_GET['departamento']);
            $sql->execute();
            header("HTTP/1.1 200 OK");
            echo json_encode(  $sql->fetchAll(PDO::FETCH_ASSOC)  );
            exit();
        }

        //  CONSULTAR CLASES
        if(isset($_GET['departamento']))
        {
            $sql = $dbConn->prepare("SELECT * FROM clases WHERE id_dep=:id_dep");
            $sql->bindValue(':id_dep', $_GET['departamento']);
            $sql->execute();
            header("HTTP/1.1 200 OK");
            echo json_encode(  $sql->fetchAll(PDO::FETCH_ASSOC)  );
            exit();
        }

        //  LISTA DE ARTICULOS
        $sql = $dbConn->prepare("SELECT * FROM articulo");
        $sql->execute();
        $sql->setFetchMode(PDO::FETCH_ASSOC);
        header("HTTP/1.1 200 OK");
        echo json_encode( $sql->fetchAll()  );
        exit();

    }


    header("HTTP/1.1 400 Bad Request");
?>