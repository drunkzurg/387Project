<?php

class Group5_Database {

    public function __construct() {
        die("Init function error");
    }

    public static function DBConnect() {
        require_once("/home/group5sp26/DBGroup5.php");
        $mysqli = null;

        try {
            $mysqli = new PDO(
                'mysql:host=' . DBHOST . ';dbname=' . DBNAME,
                USERNAME,
                PASSWORD
            );
            echo "Connection Succesfull.";
        }
        catch (PDOException $e) {
            echo "Could not connect.";
            die($e->getMessage());
        }

        return $mysqli;
    }

    public static function DBDisconnect() {
        $mysqli = null;
    }
}

?>
