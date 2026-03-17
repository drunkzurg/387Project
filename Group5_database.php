<?php
class Group5_Database {

    private static $connection;

    public static function DBConnect() {
        if (!isset(self::$connection)) {

            $dsn = "mysql:host=localhost;dbname=group5sp26;charset=utf8mb4";
            $username = "group5sp26";
            $password = "oleMissSp26";

            try {
                self::$connection = new PDO($dsn, $username, $password);
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            catch (PDOException $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }

        return self::$connection;
    }

    public static function DBDisconnect() {
        self::$connection = null;
    }
}
?>
