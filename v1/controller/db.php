<?php

class DB{
    private static $writeDBConnection;
    private static $readDBConnection;

    public static function connectWriteDB(){
        if(self::$writeDBConnection === null){
            self::$writeDBConnection = new PDO("mysql:host=localhost;dbname=tasksdb;charset=utf8","root","");
            self::$writeDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // error handle
            self::$writeDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // handle emulate prepare
        }
        
        return self::$writeDBConnection;
    }   

    public static function connectReadDB(){
        if(self::$readDBConnection === null){
            self::$readDBConnection = new PDO("mysql:host=localhost;dbname=tasksdb;charset=utf8","root","");
            self::$readDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // error handle
            self::$readDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // handle emulate prepare
        }
        
        return self::$readDBConnection;
    }   

}