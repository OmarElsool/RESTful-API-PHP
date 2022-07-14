<?php 

require_once("Task.php");

try{

    $task = new Task(1,"Title1","Description1","08/07/2022 12:00","N");
    header('Content-type: application/json; charset = utf-8');
    echo json_encode($task->returnTaskAsArray());

}catch(TaskException $ex){
    echo "Error : ". $ex->getMessage();
}