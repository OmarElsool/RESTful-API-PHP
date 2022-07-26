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

if(array_key_exists("sessionid",$_GET)){ // /session.php?sessionid=3

    $sessionid = $_GET['sessionid'];

    if($sessionid == '' || !is_numeric($sessionid)){
        $response = new Response();

        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->setMessage("Session ID must be a number");
        $response->send();
        exit;
    }

    if(!isset( $_SERVER['HTTP_AUTHORIZATION'] ) || strlen( $_SERVER['HTTP_AUTHORIZATION'] ) < 1 ){
        $response = new Response();

        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->setMessage("access token is missing from the header");
        $response->send();
        exit;
    }

    $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

    if($_SERVER['REQUEST_METHOD'] === 'DELETE'){

        try{

            $query = $writeDB->prepare("DELETE FROM tblsessions WHERE id = :sessionid AND accesstoken = :accesstoken");
            $query->bindParam(":sessionid", $sessionid, PDO::PARAM_INT);
            $query->bindParam(":accesstoken", $accesstoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if($rowCount === 0){
                $response = new Response();

                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->setMessage("failed to logging out using access token");
                $response->send();
                exit;
            }

            $returnData = array();
            $returnData['session_id'] = intval($sessionid);

            $response = new Response();

            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->setMessage("logged out");
            $response->setData($returnData);
            $response->send();
            exit;

        }catch(PDOException $ex){ // handle database error
            $response = new Response();
        
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessage("There is an issue logging out - try again");
            $response->send();
            exit;
        }

    }elseif($_SERVER['REQUEST_METHOD'] === 'PATCH'){

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

        if(!isset($jsonData->refresh_token) || strlen($jsonData->refresh_token) < 1){
            $response = new Response();

            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->setMessage("Refresh token is missing");
            $response->send();
            exit;
        }

        try{
            
            $refreshtoken = $jsonData->refresh_token;

            $query = $writeDB->prepare("SELECT tblsessions.id as sessionid, tblsessions.userid as userid, accesstoken, refreshtoken, useractive, loginattempts, accesstokenexpiry, refreshtokenexpiry FROM tblsessions, tblusers
                                        WHERE tblusers.id = tblsessions.userid
                                        AND tblsessions.id = :sessionid
                                        AND tblsessions.accesstoken = :accesstoken
                                        AND tblsessions.refreshtoken = :refreshtoken");
            $query->bindParam(":sessionid", $sessionid, PDO::PARAM_INT);
            $query->bindParam(":accesstoken", $accesstoken, PDO::PARAM_STR);
            $query->bindParam(":refreshtoken", $refreshtoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if($rowCount === 0){
                $response = new Response();

                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessage("Access token or Refresh token is incorrect for this session id");
                $response->send();
                exit;
            }

            $row = $query->fetch(PDO::FETCH_ASSOC);

            $returned_sessionid = $row['sessionid'];
            $returned_userid = $row['userid'];
            $returned_accesstoken = $row['accesstoken'];
            $returned_refreshtoken = $row['refreshtoken'];
            $returned_useractive = $row['useractive'];
            $returned_loginattempts = $row['loginattempts'];
            $returned_accesstokenexpiry = $row['accesstokenexpiry'];
            $returned_refreshtokenexpiry = $row['refreshtokenexpiry'];

            if($returned_useractive !== "Y"){
                $response = new Response();
        
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessage("User Account is not active");
                $response->send();
                exit;
            }

            if($returned_loginattempts >= 3){
                $response = new Response();
        
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessage("User Account is locked out");
                $response->send();
                exit;
            }

            if(strtotime($returned_refreshtokenexpiry) < time()){ // current time is bigger than refresh token expiry time
                $response = new Response();
        
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessage("Refresh Token has expiry - Please Log in again");
                $response->send();
                exit;
            }
            
            $accesstoken = base64_encode( bin2hex( openssl_random_pseudo_bytes(24) ).time() ) ; // convert to readable char
            $refreshtoken = base64_encode( bin2hex( openssl_random_pseudo_bytes(24) ).time() ) ;

            $access_token_expiry_seconds = 1200;
            $refresh_token_expiry_seconds = 1209600;

            $query = $writeDB->prepare("UPDATE tblsessions SET accesstoken = :accesstoken, accesstokenexpiry = date_add(NOW(), INTERVAL :accesstokenexpiry SECOND), refreshtoken = :refreshtoken, refreshtokenexpiry = date_add(NOW(), INTERVAL :refreshtokenexpiry SECOND) 
                                        WHERE id = :sessionid
                                        AND userid = :userid
                                        AND accesstoken = :returnedaccesstoken
                                        AND refreshtoken = :returnedrefreshtoken");
            $query->bindParam(":userid", $returned_userid, PDO::PARAM_INT);
            $query->bindParam(":sessionid", $returned_sessionid, PDO::PARAM_INT);
            $query->bindParam(":accesstoken", $accesstoken, PDO::PARAM_STR);
            $query->bindParam(":accesstokenexpiry", $access_token_expiry_seconds, PDO::PARAM_INT);
            $query->bindParam(":refreshtoken", $refreshtoken, PDO::PARAM_STR);
            $query->bindParam(":refreshtokenexpiry", $refresh_token_expiry_seconds, PDO::PARAM_INT);
            $query->bindParam(":returnedaccesstoken", $returned_accesstoken, PDO::PARAM_STR);
            $query->bindParam(":returnedrefreshtoken", $returned_refreshtoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if($rowCount === 0){
                $response = new Response();

                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessage("Access token not refreshed - Please log in again");
                $response->send();
                exit;
            }

            $returnData = array();
            $returnData['session_id'] = $returned_sessionid;
            $returnData['access_token'] = $accesstoken;
            $returnData['access_token_expiry_in'] = $access_token_expiry_seconds;
            $returnData['refresh_token'] = $refreshtoken;
            $returnData['refresh_token_expiry_in'] = $refresh_token_expiry_seconds;
    
            $response = new Response();
    
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setMessage("Token Refreshed");
            $response->setData($returnData);
            $response->send();
            exit;

        }catch(PDOException $ex){
            $response = new Response();
        
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessage("Refresh token issue - Please log in again");
            $response->send();
            exit;
        }

    }else{
        $response = new Response();

        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->setMessage("Request method not allowed");
        
        $response->send();
        exit;
    }

}elseif(empty($_GET)){ // /session.php

    if($_SERVER['REQUEST_METHOD'] !== 'POST'){
        $response = new Response();

        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->setMessage("Request Method not allowed");
        $response->send();
        exit;
    }

    sleep(1); // this for making 1 request per second to avoid hacking the password for anyone

    if($_SERVER['CONTENT_TYPE'] !== 'application/json'){
        $response = new Response();

        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->setMessage("Content Type is not set to json");
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

    if(!isset($jsonData->username) || !isset($jsonData->password)){
        $response = new Response();
    
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
    
        (!isset($jsonData->username) ? $response->setMessage("username can not be empty") : false);
        (!isset($jsonData->password) ? $response->setMessage("password can not be empty") : false);
        
        $response->send();
        exit;
    }

    if(strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255){
        $response = new Response();
    
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
    
        (strlen($jsonData->username) < 1 ? $response->setMessage("username can not be empty") : false);
        (strlen($jsonData->username) > 255 ? $response->setMessage("username can not be greater than 255 char") : false);
        (strlen($jsonData->password) < 1 ? $response->setMessage("password can not be empty") : false);
        (strlen($jsonData->password) > 255 ? $response->setMessage("password can not be greater than 255 char") : false);
        
        $response->send();
        exit;
    }

    try{

        $username = $jsonData->username;
        $password = $jsonData->password; // password not hashed

        $query = $writeDB->prepare("SELECT id, fullname, username, password, useractive, loginattempts FROM tblusers WHERE username = :username");
        $query->bindParam(":username", $username, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();

        if($rowCount === 0){
            $response = new Response();
    
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->setMessage("Username or Password in incorrect");
            $response->send();
            exit;
        }

        $row = $query->fetch(PDO::FETCH_ASSOC);

        $returned_id = $row['id'];
        $returned_fullname = $row['fullname'];
        $returned_username = $row['username'];
        $returned_password = $row['password']; // password from database (hashed)
        $returned_useractive = $row['useractive'];
        $returned_loginattempts = $row['loginattempts'];

        if($returned_useractive !== 'Y'){
            $response = new Response();
    
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->setMessage("User account not active");
            $response->send();
            exit;
        }

        if($returned_loginattempts >= 3){
            $response = new Response();
    
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->setMessage("User account is currently locked out");
            $response->send();
            exit;
        }

        if(!password_verify($password, $returned_password)){ //check if the password when it hashed it's the same with pass in database

            $query = $writeDB->prepare('update tblusers set loginattempts = loginattempts+1 where id = :id'); // id is the primary key that generated dynamic in db
            $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
            $query->execute();

            $response = new Response();
    
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->setMessage("Username or Password in incorrect");
            $response->send();
            exit;
        }

        $accesstoken = base64_encode( bin2hex( openssl_random_pseudo_bytes(24) ).time() ) ; // convert to readable char
        $refreshtoken = base64_encode( bin2hex( openssl_random_pseudo_bytes(24) ).time() ) ;

        $access_token_expiry_seconds = 1200; //20min
        $refresh_token_expiry_seconds = 1209600; //14 days
    
    }catch(PDOException $ex){
        $response = new Response();
    
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->setMessage("log in issue - Please try again");
        $response->send();
        exit;
    }

    try{

        $writeDB->beginTransaction(); // all database query must success to put data in database

        $query = $writeDB->prepare("UPDATE tblusers SET loginattempts = 0 WHERE id = :id");
        $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
        $query->execute();

        $query = $writeDB->prepare("INSERT INTO tblsessions (userid, accesstoken, accesstokenexpiry, refreshtoken, refreshtokenexpiry) VALUES (:userid, :accesstoken, date_add(NOW(), INTERVAL :accesstokenexpiry SECOND) , :refreshtoken, date_add(NOW(), INTERVAL :refreshtokenexpiry SECOND) )");
        $query->bindParam(":userid", $returned_id, PDO::PARAM_INT);
        $query->bindParam(":accesstoken", $accesstoken, PDO::PARAM_STR);
        $query->bindParam(":accesstokenexpiry", $access_token_expiry_seconds, PDO::PARAM_INT);
        $query->bindParam(":refreshtoken", $refreshtoken, PDO::PARAM_STR);
        $query->bindParam(":refreshtokenexpiry", $refresh_token_expiry_seconds, PDO::PARAM_INT);
        $query->execute();

        $lastSessionID = $writeDB->lastInsertId();

        $writeDB->commit(); // because we use transaction we must to this to save the data in db

        $returnData = array();
        $returnData['session_id'] = intval($lastSessionID);
        $returnData['access_token'] = $accesstoken;
        $returnData['access_token_expiry_in'] = $access_token_expiry_seconds;
        $returnData['refresh_token'] = $refreshtoken;
        $returnData['refresh_token_expiry_in'] = $refresh_token_expiry_seconds;

        $response = new Response();

        $response->setHttpStatusCode(201);
        $response->setSuccess(true);
        $response->setData($returnData);
        $response->send();
        exit;

    }catch(PDOException $ex){
        $writeDB->rollBack(); // only work with transaction and it takes us the value before the try 
        $response = new Response();
    
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->setMessage("log in issue - Please try again");
        $response->send();
        exit;
    }

}else{ // ex: /session.php?test or anything is not exist
    $response = new Response();

    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->setMessage("Page Not Found");
    $response->send();
    exit;
}