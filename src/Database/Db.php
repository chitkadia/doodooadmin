<?php
/**
 * Singleton class for database connection
 */
namespace App\Database;

class Db {

    private $_connection;
    private static $_instance;
    private $_error;

    /**
     * Do connection with database
     * By declaring it private, it doesn't allow to create object of this class (to treat as Singleton class)
     */
    private function __construct() {
        try {
            $db_config = require __DIR__ . "/../database.php";

            $dsn = $db_config["engine"] . ":host=" . $db_config["host"] . ";port=" . $db_config["port"] . ";dbname=" . $db_config["database"] . ";charset=" . $db_config["charset"];
            $usr = $db_config["username"];
            $pwd = $db_config["password"];
            $opt = $db_config["options"];

            try {
                $this->_connection = new \Slim\PDO\Database($dsn, $usr, $pwd, $opt);

            } catch (\PDOException $e) {
                $this->_error = $e->getMessage();
            }

        } catch (\Exception $e) {
            $this->_error = $e->getMessage();
        }
    }

    /**
     * Do not allow cloning of the object (to treat as Singleton class)
     */
    private function __clone() {}

    /**
     * Get an instance of the Database
     * This will return only one instance of Database (to treat as Singleton class)
     *
     * @return _instance
     */
    public static function getInstance() {
        if (!self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Get current open connection of database
     *
     * @return _connection
     */
    public function getConnection() {
        return $this->_connection;
    }

    /**
     * Get connection error message
     *
     * @return _error
     */
    public function getErrorMessage() {
        return $this->_error;
    }

}