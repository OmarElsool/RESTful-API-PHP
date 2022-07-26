<?php

require_once("db.php");
require_once("../model/Response.php");

try{

    $writeDB = DB::connectWriteDB();

}catch(PDOException $ex){
    error_log("Connection Error : ".$ex,0);

    $response = new Response();

    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->setMessage("Database connection error");
    $response->send();
    exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    $response = new Response();

    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->setMessage("Request Method Not Allowed");
    $response->send();
    exit;
}

if($_SERVER['CONTENT_TYPE'] !== 'application/json'){
    $response = new Response();

    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    $response->setMessage("Content Type header is not set to json");
    $response->send();
    exit;
}

$rawPostData = file_get_contents('php://input'); // php://input allow us to inspect the body of request

if(!$jsonData = json_decode($rawPostData)){
    $response = new Response();

    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    $response->setMessage("Request Body is not valid json");
    $response->send();
    exit;
}

if(!isset($jsonData->fullname) || !isset($jsonData->username) || !isset($jsonData->password)){
    $response = new Response();

    $response->setHttpStatusCode(400);
    $response->setSuccess(false);

    (!isset($jsonData->fullname) ? $response->setMessage("fullname can not be empty") : false);
    (!isset($jsonData->username) ? $response->setMessage("username can not be empty") : false);
    (!isset($jsonData->password) ? $response->setMessage("password can not be empty") : false);
    
    $response->send();
    exit;
}

if(strlen($jsonData->fullname) < 1 || strlen($jsonData->fullname) > 255 || strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255){
    $response = new Response();

    $response->setHttpStatusCode(400);
    $response->setSuccess(false);

    (strlen($jsonData->fullname) < 1 ? $response->setMessage("fullname can not be empty") : false);
    (strlen($jsonData->fullname) > 255 ? $response->setMessage("fullname can not be greater than 255 char") : false);
    (strlen($jsonData->username) < 1 ? $response->setMessage("username can not be empty") : false);
    (strlen($jsonData->username) > 255 ? $response->setMessage("username can not be greater than 255 char") : false);
    (strlen($jsonData->password) < 1 ? $response->setMessage("password can not be empty") : false);
    (strlen($jsonData->password) > 255 ? $response->setMessage("password can not be greater than 255 char") : false);
    
    $response->send();
    exit;
}

$fullname = trim($jsonData->fullname);
$username = trim($jsonData->username);
$password = $jsonData->password;

try{

    $query = $writeDB->prepare("SELECT id FROM tblusers WHERE username = :username");
    $query->bindParam(":username", $username, PDO::PARAM_STR);
    $query->execute();

    $rowCount = $query->rowCount();

    if($rowCount !== 0){
        $response = new Response();

        $response->setHttpStatusCode(409);
        $response->setSuccess(false);
        $response->setMessage("Username is already exist");
        $response->send();
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $query = $writeDB->prepare("INSERT INTO tblusers (fullname, username, password) VALUES (:fullname, :username, :password)");
    $query->bindParam(":fullname", $fullname, PDO::PARAM_STR);
    $query->bindParam(":username", $username, PDO::PARAM_STR);
    $query->bindParam(":password", $hashed_password, PDO::PARAM_STR);
    $query->execute();

    $rowCount = $query->rowCount();

    if($rowCount === 0){
        $response = new Response();

        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->setMessage("failed to create account");
        $response->send();
        exit;
    }

    $lastUserID = $writeDB->lastInsertId();

    $returnData = array();
    $returnData['user_id'] = $lastUserID;
    $returnData['fullname'] = $fullname;
    $returnData['username'] = $username;

    $response = new Response();

    $response->setHttpStatusCode(201);
    $response->setSuccess(true);
    $response->setMessage("account created successfully");
    $response->setData($returnData);
    $response->send();
    exit;

}catch(PDOException $ex){
    error_log("Connection Error : ".$ex,0);

    $response = new Response();

    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->setMessage("error creating user account");
    $response->send();
    exit;
}