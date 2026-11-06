<?php
    include "config.php";
    include "utils.php";

    // header("HTTP/1.1 200");

    header('Access-Control-Allow-Origin: *');
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
    
    $dbConn =  connect($db);

    // Dar de alta
    if ($_SERVER['REQUEST_METHOD'] == 'POST')
    {
        $input = $_POST;
        $sql = "INSERT INTO registros
            (sku, cantidad, fecha)
            VALUES
            (:sku, :cantidad, :fecha)";
        $statement = $dbConn->prepare($sql);
        bindAllValues($statement, $input);
        $statement->execute();
        $postId = $dbConn->lastInsertId();

        if($postId)
        {
        $input['id'] = $postId;
        header("HTTP/1.1 200 OK");
        echo json_encode($input);
        exit();
        }
    }
    
?>