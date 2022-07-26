<?php 

require_once("db.php");
require_once("../model/Response.php");
require_once("../model/Task.php");

try{

    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB(); 

}catch(PDOException $ex){
    error_log("Connection Error : ".$ex,0);

    $response = new Response();

    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->setMessage("Database connection error");
    $response->send();
    exit;
}

// begin auth script

if(!isset( $_SERVER['HTTP_AUTHORIZATION'] ) || strlen( $_SERVER['HTTP_AUTHORIZATION'] ) < 1 ){
    $response = new Response();

    $response->setHttpStatusCode(401);
    $response->setSuccess(false);
    $response->setMessage("access token is missing from the header");
    $response->send();
    exit;
}

$accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

try{
    $query = $writeDB->prepare("SELECT userid, accesstokenexpiry, useractive, loginattempts FROM tblsessions, tblusers
                                WHERE tblsessions.userid = tblusers.id
                                AND accesstoken = :accesstoken");
    $query->bindParam(":accesstoken", $accesstoken, PDO::PARAM_STR);
    $query->execute();

    $rowCount = $query->rowCount();

    if($rowCount === 0){
        $response = new Response();

        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->setMessage("Access Token is not valid");
        $response->send();
        exit;
    }

    $row = $query->fetch(PDO::FETCH_ASSOC);

    $returned_userid = $row['userid'];
    $returned_accesstokenexpiry = $row['accesstokenexpiry']; 
    $returned_useractive = $row['useractive'];
    $returned_loginattempts = $row['loginattempts'];

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

    if(strtotime($returned_accesstokenexpiry) < time()){ // current time is bigger than access token expiry time
        $response = new Response();
            
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->setMessage("Access Token has expiry - Please Log in again");
        $response->send();
        exit;
    }
}
catch(PDOException $ex){
    $response = new Response();

    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->setMessage("There is an Auth issue - Please try again");
    $response->send();
    exit;
}

// end auth script

// check if taskid is exist 
if(array_key_exists("taskid",$_GET)){ // ex: task.php?taskid=3

    $taskid = $_GET['taskid'];

    if($taskid == '' || !is_numeric($taskid)){
        $response = new Response();

        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->setMessage("Task ID must be a number");
        $response->send();
        exit;
    }

    if($_SERVER['REQUEST_METHOD'] === 'GET'){ 

        try{

            $query = $readDB->prepare("SELECT id, title, description, DATE_FORMAT(deadline, '%d/%m/%Y %H:%i') as deadline, completed from tbltasks where id = :taskid AND userid = :userid"); // : before taskid for injection and security
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if($rowCount === 0){
                $response = new Response();

                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->setMessage("Task not found");
                $response->send();
                exit;
            }

            while($row = $query->fetch(PDO::FETCH_ASSOC)){
                $task = new Task($row['id'],$row['title'],$row['description'],$row['deadline'],$row['completed']);

                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['row_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();

            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit;

        }
        catch(TaskException $ex){ // handle task error
            $response = new Response();

            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessage($ex->getMessage());
            $response->send();
            exit;
        }
        catch(PDOException $ex){ // handle database error
            error_log("Database query error : ".$ex,0);

            $response = new Response();
        
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessage("Failed to get task");
            $response->send();
            exit;
        }

    }elseif($_SERVER['REQUEST_METHOD'] === 'DELETE'){

        try{

            $query = $writeDB->prepare("DELETE FROM tbltasks WHERE id = :taskid AND userid = :userid");
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if($rowCount === 0){
                $response = new Response();

                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->setMessage("Task not found");
                $response->send();
                exit;
            }

            $response = new Response();

            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setMessage("Task Deleted");
            $response->send();
            exit;

        }catch(PDOException $ex){
            $response = new Response();

            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessage($ex->getMessage());
            $response->send();
            exit;
        }

    }elseif($_SERVER['REQUEST_METHOD'] === 'PATCH'){

        try{

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

            $title_updated = false;
            $description_updated = false;
            $deadline_updated = false;
            $completed_updated = false;

            $queryField = "";

            if(isset($jsonData->title)){
                $title_updated = true;
                $queryField .= "title = :title, ";
            }

            if(isset($jsonData->description)){
                $description_updated = true;
                $queryField .= "description = :description, ";
            }

            if(isset($jsonData->deadline)){
                $deadline_updated = true;
                $queryField .= "deadline = STR_TO_DATE(:deadline, '%d/%m/%Y %H:%i'), ";
            }

            if(isset($jsonData->completed)){
                $completed_updated = true;
                $queryField .= "completed = :completed, ";
            }

            $queryField = rtrim($queryField,", "); // to delete , from last element

            if($title_updated === false && $description_updated === false && $deadline_updated === false && $completed_updated === false){
                $response = new Response();

                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->setMessage("no task field updated");
                $response->send();
                exit;
            }

            $query = $writeDB->prepare("SELECT id, title, description, DATE_FORMAT(deadline, '%d/%m/%Y %H:%i') as deadline, completed from tbltasks where id = :taskid AND userid = :userid");
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if($rowCount === 0){
                $response = new Response();

                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->setMessage("Task not found");
                $response->send();
                exit;
            }

            while($row = $query->fetch(PDO::FETCH_ASSOC)){
                $task = new Task($row['id'],$row['title'],$row['description'],$row['deadline'],$row['completed']);
            }

            $queryString = "UPDATE tbltasks SET ".$queryField." WHERE id = :taskid AND userid = :userid";
            $query = $writeDB->prepare($queryString);

            if($title_updated === true){
                $task->setTitle($jsonData->title);
                $up_title = $task->getTitle();
                $query->bindParam(":title",$up_title, PDO::PARAM_STR);
            }

            if($description_updated === true){
                $task->setDescription($jsonData->description);
                $up_description = $task->getDescription();
                $query->bindParam(":description",$up_description, PDO::PARAM_STR);
            }

            if($deadline_updated === true){
                $task->setDeadline($jsonData->deadline);
                $up_deadline = $task->getDeadline();
                $query->bindParam(":deadline",$up_deadline, PDO::PARAM_STR);
            }

            if($completed_updated === true){
                $task->setCompleted($jsonData->completed);
                $up_completed = $task->getCompleted();
                $query->bindParam(":completed",$up_completed, PDO::PARAM_STR);
            }

            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if($rowCount === 0){
                $response = new Response();

                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->setMessage("Task not Updated");
                $response->send();
                exit;
            }

            $query = $readDB->prepare("SELECT id, title, description, DATE_FORMAT(deadline, '%d/%m/%Y %H:%i') as deadline, completed from tbltasks where id = :taskid AND userid = :userid");
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if($rowCount === 0){
                $response = new Response();

                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->setMessage("Task not found after update");
                $response->send();
                exit;
            }

            while($row = $query->fetch(PDO::FETCH_ASSOC)){
                $task = new Task($row['id'],$row['title'],$row['description'],$row['deadline'],$row['completed']);

                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['row_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();

            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setMessage("Task Updated");
            $response->setData($returnData);
            $response->send();
            exit;


        }catch(TaskException $ex){ // handle task error
            $response = new Response();

            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->setMessage($ex->getMessage());
            $response->send();
            exit;
        }
        catch(PDOException $ex){ // handle database error
            error_log("Database query error : ".$ex,0);

            $response = new Response();
        
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessage("Failed to Update task");
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

}elseif(array_key_exists("completed",$_GET)){ // to get all completed taskes // v1/task.php?completed=Y == v1/tasks/completed
    $completed = $_GET['completed'];

    if($completed !== 'Y' && $completed !== 'N'){
        $response = new Response();

        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->setMessage("Completed Must be Y or N");
        $response->send();
        exit;
    }

    if($_SERVER['REQUEST_METHOD'] === 'GET'){

        try{

            $query = $readDB->prepare("SELECT id, title, description, DATE_FORMAT(deadline, '%d/%m/%Y %H:%i') as deadline, completed from tbltasks where completed = :completed AND userid = :userid"); // : before taskid for injection and security
            $query->bindParam(':completed', $completed, PDO::PARAM_STR);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            $taskArray = array();

            while($row = $query->fetch(PDO::FETCH_ASSOC)){
                $task = new Task($row['id'],$row['title'],$row['description'],$row['deadline'],$row['completed']);

                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['row_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();

            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit;

        }catch(TaskException $ex){ // handle task error
            $response = new Response();

            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessage($ex->getMessage());
            $response->send();
            exit;
        }
        catch(PDOException $ex){ // handle database error
            error_log("Database query error : ".$ex,0);

            $response = new Response();
        
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessage("Failed to get task");
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
}elseif(array_key_exists("page",$_GET)){ // to sperate tasks into pages like every 20 task in 1 page

    if($_SERVER['REQUEST_METHOD'] === 'GET'){

        $page = $_GET['page'];

        if($page == '' || !is_numeric($page)){
            $response = new Response();
    
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->setMessage("Page number must be a number");
            $response->send();
            exit;
        }
        $limitPerPage = 20;

        try{

            $query = $readDB->prepare("SELECT count(id) as totalNoOfTasks FROM tbltasks WHERE userid = :userid");
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT); 
            $query->execute();

            $row = $query->fetch(PDO::FETCH_ASSOC);

            $tasksCount = intval($row['totalNoOfTasks']);
            $numOfPages = ceil( $tasksCount/$limitPerPage );

            if($numOfPages == 0){
                $numOfPages = 1;
            }

            if($page > $numOfPages || $page == 0){
                $response = new Response();

                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->setMessage("Page not found");
                $response->send();
                exit;
            }

            $offset = ($page == 1 ? 0 : ($limitPerPage*($page-1))); // offset is to know which row start with in database after first page

            $query = $readDB->prepare("SELECT id, title, description, DATE_FORMAT(deadline, '%d/%m/%Y %H:%i') as deadline, completed from tbltasks WHERE userid = :userid LIMIT :pglimit offset :offset"); // offset which row start with
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->bindParam(':pglimit', $limitPerPage, PDO::PARAM_INT);
            $query->bindParam(':offset', $offset, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            $taskArray = array();

            while($row = $query->fetch(PDO::FETCH_ASSOC)){
                $task = new Task($row['id'],$row['title'],$row['description'],$row['deadline'],$row['completed']);

                $taskArray[] = $task->returnTaskAsArray();
            }
 
            $returnData = array();
            $returnData['row_returned'] = $rowCount;
            $returnData['total_returned'] = $tasksCount;
            $returnData['total_pages'] = $numOfPages;
            ($page < $numOfPages ? $returnData['has_next_page'] = true : $returnData['has_next_page'] = false);
            ($page > 1 ? $returnData['has_previous_page'] = true : $returnData['has_previous_page'] = false);
            $returnData['tasks'] = $taskArray;

            $response = new Response();

            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit;

        }
        catch(TaskException $ex){ // handle task error
            $response = new Response();

            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessage($ex->getMessage());
            $response->send();
            exit;
        }
        catch(PDOException $ex){ // handle database error
            error_log("Database query error : ".$ex,0);

            $response = new Response();
        
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessage("Failed to get task");
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

}elseif(empty($_GET)){ // if GET is empty mean we get all tasks not specific id or complete // ex: task.php
    // /tasks
    if($_SERVER['REQUEST_METHOD'] === 'GET'){

    try{

        $query = $readDB->prepare("SELECT id, title, description, DATE_FORMAT(deadline, '%d/%m/%Y %H:%i') as deadline, completed from tbltasks WHERE userid = :userid");
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();
        $taskArray = array();

        while($row = $query->fetch(PDO::FETCH_ASSOC)){
            $task = new Task($row['id'],$row['title'],$row['description'],$row['deadline'],$row['completed']);

            $taskArray[] = $task->returnTaskAsArray();
        }

        $returnData = array();
        $returnData['row_returned'] = $rowCount;
        $returnData['tasks'] = $taskArray;

        $response = new Response();

        $response->setHttpStatusCode(200);
        $response->setSuccess(true);
        $response->toCache(true);
        $response->setData($returnData);
        $response->send();
        exit;

    }catch(TaskException $ex){ // handle task error
        $response = new Response();

        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->setMessage($ex->getMessage());
        $response->send();
        exit;
    }
    catch(PDOException $ex){ // handle database error
        error_log("Database query error : ".$ex,0);

        $response = new Response();
    
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->setMessage("Failed to get task");
        $response->send();
        exit;
    }

    }elseif($_SERVER['REQUEST_METHOD'] === 'POST'){

        try{

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

            if(!isset($jsonData->title) || !isset($jsonData->completed)){
                $response = new Response();

                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                (!isset($jsonData->title)) ? $response->setMessage("title field must be set") : false; // if it's not set then message this
                (!isset($jsonData->completed)) ? $response->setMessage("completed field must be set") : false;
                $response->send();
                exit;
            }

            $newTask = new Task(null,$jsonData->title, ( isset($jsonData->description) ? $jsonData->description : null ) , ( isset($jsonData->deadline) ? $jsonData->deadline : null ) , $jsonData->completed);

            $title = $newTask->getTitle();
            $description = $newTask->getDescription();
            $deadline = $newTask->getDeadline();
            $completed = $newTask->getCompleted();

            //Insert data into database
            $query = $writeDB->prepare("INSERT INTO tbltasks ( title, description, deadline, completed, userid) VALUES (:title, :description, STR_TO_DATE(:deadline, '%d/%m/%Y %H:%i'), :completed, :userid)");
            $query->bindParam(':title', $title, PDO::PARAM_STR);
            $query->bindParam(':description', $description, PDO::PARAM_STR);
            $query->bindParam(':deadline', $deadline, PDO::PARAM_STR);
            $query->bindParam(':completed', $completed, PDO::PARAM_STR);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if($rowCount === 0){
                $response = new Response();

                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->setMessage("Failed To Create Task");
                $response->send();
                exit;
            }

            $lastTaskID = $writeDB->lastInsertId();

            $query = $readDB->prepare("SELECT id, title, description, DATE_FORMAT(deadline, '%d/%m/%Y %H:%i') as deadline, completed from tbltasks where id = :taskid AND userid = :userid"); 
            $query->bindParam(':taskid', $lastTaskID, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT); 
            $query->execute();

            $rowCount = $query->rowCount();

            if($rowCount === 0){
                $response = new Response();

                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->setMessage("Failed To Retrieve Task after creation");
                $response->send();
                exit;
            }

            $taskArray = array();

            while($row = $query->fetch(PDO::FETCH_ASSOC)){
                $task = new Task($row['id'],$row['title'],$row['description'],$row['deadline'],$row['completed']);

                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['row_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();

            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->setMessage("Task Created");
            $response->setData($returnData);
            $response->send();
            exit;

        }catch(TaskException $ex){ // handle task error
            $response = new Response();

            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->setMessage($ex->getMessage());
            $response->send();
            exit;
        }
        catch(PDOException $ex){ // handle database error
            error_log("Database query error : ".$ex,0);

            $response = new Response();
        
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessage("Failed to insert task to database");
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

}else{
    $response = new Response();

    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->setMessage("Page Not Found");
    $response->send();
    exit;
}