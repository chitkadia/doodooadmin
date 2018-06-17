<?php
/**
 * Base file of application models
 * Every model should extend this file
 */
namespace App\Models;

use \App\Database\Db;
use \App\Components\ModelValidationsComponent as Validator;

abstract class AppModel {

    // Database connection
    protected $_db;

    // Database table to which model is connected
    protected $_table;
    protected $_table_alias;

    // Database table primary key column
    protected $_pk_column;

    // Array of database table columns
    protected $_fields;

    // Array of tables describing join conditions
    protected $_joins;

    // Array of tables describing validations
    protected $_validations;

    // Array of message string containing validation errors on field value
    protected $_validation_messages;

    // Last query details
    protected $_failed_query_message;
    protected $_query_error = false;
    protected $_query_string;

    // Last insert id
    protected $_last_insert_id;

    // Data to save/update
    protected $_data;

    /**
     * Constructor
     */
    public function __construct() {
        // Connect with database
        $database = Db::getInstance();
        $this->_db = $database->getConnection();
    }

    /**
     * Check if database connected or not
     * 
     * @return (boolean) True on success, otherwise false
     */
    public function dbConnected() {
        if ($this->_db !== null) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Abstract Method: Set database table name to associate with the model
     */
    abstract protected function _setTable();

    /**
     * Abstract Method: Set database table's primary key column name
     */
    abstract protected function _setPkColumn();

    /**
     * Abstract Method: Set database table's fields
     */
    abstract protected function _setFields();

    /**
     * Abstract Method: Set join relations
     */
    abstract protected function _setJoins();

    /**
     * Abstract Method: Set validations
     */
    abstract protected function _setValidations();

    /**
     * Prepare statement conditions for SELECT and UPDATE queries
     *
     * @param $statement (object): Prepared statement to append conditions
     * @param $condition (null|array): Query conditions
     * - $condition["fields"] array Array of fields to fetch, if not passed, primary key field will be returned
     * - $condition["join"] boolean|array Join conditions
     * - $condition["where"] array Array of WHERE conditions
     * - $condition["having"] array Array of HAVING conditions
     * - $condition["group_by"] string String of GROUP BY fields
     * - $condition["order_by"] array Array of ORDER BY fields
     * - $condition["limit"] integer Number of records to fetch
     * - $condition["offset"] integer Offset to start the records from
     *
     * @return (object) Statement with conditions applied
     */
    private function _prepareCondition(&$statement, $condition = null) {
        // JOIN
        if (isset($condition["join"])) {
            if ($condition["join"] && !empty($condition["join"])) {
                $join = (array) $condition["join"];

                foreach ($join as $j) {
                    if (isset($this->_joins[$j])) {
                        $statement->join($this->_joins[$j][0], $this->_joins[$j][1], $this->_joins[$j][2], $this->_joins[$j][3], $this->_joins[$j][4]);
                    }
                }
            }
        }

        // WHERE
        if (!empty($condition["where"])) {
            $where = (array) $condition["where"];

            foreach ($where as $val) {
                $key = key($val);

                switch ($key) {
                    case ("where" || "orWhere") && (count($val[$key]) == 3):
                        $statement->$key($val[$key][0], $val[$key][1], $val[$key][2]);
                        break;

                    case ("whereBetween" || "orWhereBetween") && (count($val[$key]) == 2):
                    case ("whereNotBetween" || "orWhereNotBetween") && (count($val[$key]) == 2):
                    case ("whereIn" || "orWhereIn") && (count($val[$key]) == 2):
                    case ("whereNotIn" || "orWhereNotIn") && (count($val[$key]) == 2):
                    case ("whereLike" || "orWhereLike") && (count($val[$key]) == 2):
                    case ("whereNotLike" || "orWhereNotLike") && (count($val[$key]) == 2):
                        $statement->$key($val[$key][0], $val[$key][1]);
                        break;

                    case ("whereNull" || "orWhereNull") && (!empty($val[$key])):
                    case ("whereNotNull" || "orWhereNotNull") && (!empty($val[$key])):
                        $statement->$key($val[$key]);
                        break;

                    default:
                        break;
                }
            }
        }

        // HAVING
        if (!empty($condition["having"])) {
            $having = (array) $condition["having"];

            foreach ($having as $val) {
                $key = key($val);

                switch ($key) {
                    case ("having" || "orHaving") && (count($val[$key]) == 3):
                    case "havingCount" && (count($val[$key]) == 3):
                    case "havingMin" && (count($val[$key]) == 3):
                    case "havingMax" && (count($val[$key]) == 3):
                    case "havingAvg" && (count($val[$key]) == 3):
                    case "havingSum" && (count($val[$key]) == 3):
                        $statement->$key($val[$key][0], $val[$key][1], $val[$key][2]);
                        break;

                    default:
                        break;
                }
            }
        }

        // GROUP BY
        if (!empty($condition["group_by"])) {
            $group_by = trim($condition["group_by"]);

            $statement->groupBy($group_by);
        }

        // ORDER BY
        if (!empty($condition["order_by"])) {
            $order_by = (array) $condition["order_by"];

            foreach ($order_by as $order_by_clause) {
                if (!empty($order_by_clause)) {
                    $oby_arr = explode(" ", $order_by_clause);

                    $oby_arr[1] = (empty($oby_arr[1])) ? "ASC" : strtoupper($oby_arr[1]);

                    $statement->orderBy($oby_arr[0], $oby_arr[1]);
                }
            }
        }

        // LIMIT, OFFSET
        if (isset($condition["limit"]) && isset($condition["offset"])) {
            $limit = (int) $condition["limit"];
            $offset = (int) $condition["offset"];
            
            $statement->limit($limit, $offset);

        } else if (isset($condition["offset"])) {
            $offset = (int) $condition["offset"];

            $statement->offset($offset);
        }

        return $statement;
    }

    /**
     * Validate model data for data integrity
     * 
     * @return (boolean) Return true on valid data, otherwise false
     */
    private function _validateModel() {
        if (empty($this->_validations)) {
            return true;
        }

        $this->_validation_messages = Validator::validate($this->_validations, $this->_data);

        if (count($this->_validation_messages) > 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Get model validation messages
     *
     * @return (string) Validation message(s)
     */
    public function getValidationMessage() {
        $message = "";

        if (!empty($this->_validation_messages)) {
            $message = implode("\n", $this->_validation_messages);
        }

        return $message;
    }

    /**
     * Get last query fail message
     *
     * @return (string) Error message
     */
    public function getQueryError() {
        $message = "";

        if (!empty($this->_failed_query_message)) {
            $message = $this->_failed_query_message;
        }

        return $message;
    }

    /**
     * Check if last query failed
     *
     * @return (boolean) True of last query failed, otherwise false
     */
    public function hasQueryError() {
        return (bool) $this->_query_error;
    }

    /**
     * Get last executed query as string
     *
     * @return (string) Query as string
     */
    public function getQueryString() {
        return $this->_query_string;
    }

    /**
     * Get last insert id of primary key column
     *
     * @return (integer) Id of last inserted record
     */
    public function getLastInsertId() {
        return (int) $this->_last_insert_id;
    }

    /**
     * Turns off auto commit mode and begins a transaction
     *
     * @return true if the method call succeeded, false otherwise
     */
    public function beginTransaction() {
        return $this->_db->beginTransaction();
    }

    /**
     * Commits a transaction
     *
     * @return returning the database connection to autocommit mode until the next call
     */
    public function commit() {
        return $this->_db->commit();
    }

    /**
     * Rolls back a transaction
     *
     * @return rolls back the current transaction
     */
    public function rollBack() {
        return $this->_db->rollBack();
    }

    /**
     * Fetch one record from database table
     *
     * @param $conditions (null|array): Query conditions
     *
     * @return (array) Associative array (Column => Value) of single row of table
     */
    public function fetch($conditions = null) {
        $row = [];

        // Which fields to select
        $fields = [$this->_table_alias . "." . $this->_pk_column];
        if (!empty($conditions["fields"])) {
            $fields = (array) $conditions["fields"];
        }

        // Prepare select statement
        $select_stmt = $this->_db->select($fields)->from($this->_table . " AS " . $this->_table_alias);

        // Append conditions
        $select_stmt = $this->_prepareCondition($select_stmt, $conditions);

        // Execute query
        try {
            // Store query
            $this->_query_string = $select_stmt->__toString();

            $stmt = $select_stmt->execute();
            $row = $stmt->fetch();

            // Reset statement
            $stmt = null;

            // Reset query error flag
            $this->_query_error = false;
            
        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $row;
    }

    /**
     * Fetch more than one record from database table
     *
     * @param $conditions (null|array): Query conditions
     *
     * @return (array) Associative array (Column => Value) of table rows
     */
    public function fetchAll($conditions = null) {
        $rows = [];

        // Which fields to select
        $fields = [$this->_table_alias . "." . $this->_pk_column];
        if (!empty($conditions["fields"])) {
            $fields = (array) $conditions["fields"];
        }

        // Prepare select statement
        $select_stmt = $this->_db->select($fields)->from($this->_table . " AS " . $this->_table_alias);

        // Append conditions
        $select_stmt = $this->_prepareCondition($select_stmt, $conditions);

        // Execute query
        try {
            // Store query
            $this->_query_string = $select_stmt->__toString();

            $stmt = $select_stmt->execute();
            $rows = $stmt->fetchAll();

            // Reset statement
            $stmt = null;

            // Reset query error flag
            $this->_query_error = false;
            
        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $rows;
    }

    /**
     * Fetch more than one record from database table
     *
     * @param $key_column (string): Column name (value of column to be returned as key)
     * @param $val_column (string): Column name (value of column to be returned as value)
     * @param $conditions (null|array): Query conditions
     *
     * @return (array) Associative array (Key Column's Data => Value Column's Data) of table rows
     */
    public function fetchList($key_column, $val_column, $conditions = null) {
        $rows = [];

        // If no key and value columns are specified, return empty result
        if (empty($key_column) || empty($val_column)) {
            $this->_failed_query_message = "Please specify key and/or value column to fetch.";
            $this->_query_error = true;

            return $rows;
        }

        // Which fields to select
        $fields = [$key_column, $val_column];

        // Prepare select statement
        $select_stmt = $this->_db->select($fields)->from($this->_table . " AS " . $this->_table_alias);

        // Append conditions
        $select_stmt = $this->_prepareCondition($select_stmt, $conditions);

        // Execute query
        try {
            // Store query
            $this->_query_string = $select_stmt->__toString();

            $stmt = $select_stmt->execute();
            $data_rows = $stmt->fetchAll();

            // Reset statement
            $stmt = null;

            // Reset query error flag
            $this->_query_error = false;

            // Prepare list data
            $key_column_arr = explode(".", $key_column);
            $val_column_arr = explode(".", $val_column);
            $key = end($key_column_arr);
            $val = end($val_column_arr);

            foreach ($data_rows as $dval) {
                $rows[$dval[$key]] = $dval[$val];
            }
            
        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $rows;
    }

    /**
     * Fetch one record from database matching primary key column value
     *
     * @param $id (integer): ID of primary key column
     * @param $fields (null|array): Array of fields to fetch, if not passed, primary key field will be returned
     *
     * @return (array) Associative array (Column => Value) of single row of table
     */
    public function fetchById($id = null, $fields = null) {
        $row = [];

        if (!empty($id)) {
            $id = (int) $id;

            // Which fields to select
            $fields = (!empty($fields)) ? (array) $fields : [$this->_table_alias . "." . $this->_pk_column];

            // Prepare select statement
            $select_stmt = $this->_db->select($fields)->from($this->_table . " AS " . $this->_table_alias)->where($this->_pk_column, "=", $id);

            // Execute query
            try {
                // Store query
                $this->_query_string = $select_stmt->__toString();

                $stmt = $select_stmt->execute();
                $row = $stmt->fetch();

                // Reset statement
                $stmt = null;

                // Reset query error flag
                $this->_query_error = false;
                
            } catch(\PDOException $e) {
                $this->_failed_query_message = $e->getMessage();
                $this->_query_error = true;

                throw new \Exception($this->_failed_query_message);
            }
        }

        return $row;
    }

    /**
     * Save database table record
     *
     * @param $data (array): Associative array (Column => Value)
     * @param $validate (boolean): Validate data before save or not
     *
     * @return (integer) Last insert id OR # records updated OR false on validation failure
     */
    public function save($data = [], $validate = false) {
        $return_data = 0;

        // Check if data is an array
        if (!is_array($data)) {
            throw new \Exception("Data must be an array.");
        }

        $this->_data = [];
        $valid_fields = $this->_fields;

        // Prepare data to be saved
        foreach ($data as $key => $val) {
            if (isset($valid_fields[$key])) {
                $this->_data[$key] = $val;
            }
        }

        // Check if data is present
        if (empty($this->_data)) {
            throw new \Exception("No data set to save.");
        }

        // Check if data is valid
        if ($validate) {
            if ($this->_validateModel() === false) {
                return false;
            }
        }

        try {
            if (!empty($this->_data[$this->_pk_column])) {
                // Update record
                $id_to_update = $this->_data[$this->_pk_column];
                unset($this->_data[$this->_pk_column]);

                $save_statement = $this->_db->update($this->_data)
                                    ->table($this->_table)
                                    ->where($this->_pk_column, "=", $id_to_update);

                // Store query
                $this->_query_string = $save_statement->__toString();

                $return_data = $save_statement->execute();

            } else {
                // Insert record
                $column_keys = array_keys($this->_data);
                $column_values = array_values($this->_data);

                $save_statement = $this->_db->insert($column_keys)
                                    ->into($this->_table)
                                    ->values($column_values);

                // Store query
                $this->_query_string = $save_statement->__toString();

                $return_data = $save_statement->execute();
                $this->_last_insert_id = $return_data;
            }

            // Reset statement
            $save_statement = null;

            // Reset query error flag
            $this->_query_error = false;

            return $return_data;

        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $return_data;
    }

    /**
     * Update database table record
     *
     * @param $data (array): Associative array (Column => Value)
     * @param $conditions (null|array): Query conditions
     *
     * @return (integer) Number of records updated OR false on validation failure
     */
    public function update($data = [], $conditions = null) {
        $return_data = 0;

        // Check if data is an array
        if (!is_array($data)) {
            throw new \Exception("Data must be an array.");
        }

        $this->_data = [];
        $valid_fields = $this->_fields;

        // Prepare data to be saved
        foreach ($data as $key => $val) {
            if (isset($valid_fields[$key])) {
                $this->_data[$key] = $val;
            }
        }

        // Check if data is present
        if (empty($this->_data)) {
            throw new \Exception("No data set to save.");
        }

        // Check if data is valid
        if ($this->_validateModel() === false) {
            return false;
        }

        try {
            $save_statement = $this->_db->update($this->_data)->table($this->_table);

            // Append conditions
            $save_statement = $this->_prepareCondition($save_statement, $conditions);

            // Store query
            $this->_query_string = $save_statement->__toString();

            $return_data = $save_statement->execute();

            // Reset statement
            $save_statement = null;

            // Reset query error flag
            $this->_query_error = false;

        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $return_data;
    }

    /**
     * Delete database table record
     *
     * @param $conditions (null|array): Query conditions
     *
     * @return (integer) Number of records deleted
     */
    public function delete($conditions = null) {
        $return_data = 0;

        try {
            $del_statement = $this->_db->delete()->from($this->_table);

            // Append conditions
            $del_statement = $this->_prepareCondition($del_statement, $conditions);

            // Store query
            $this->_query_string = $del_statement->__toString();

            $return_data = $del_statement->execute();

            // Reset statement
            $del_statement = null;

            // Reset query error flag
            $this->_query_error = false;

        } catch(\PDOException $e) {
            $this->_failed_query_message = $e->getMessage();
            $this->_query_error = true;

            throw new \Exception($this->_failed_query_message);
        }

        return $return_data;
    }

}

/**
 * Common code which is used to initialize model classes
 */
trait ModelInitialize {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        $this->_setTable();
        $this->_setPkColumn();
        $this->_setFields();
        $this->_setJoins();
        $this->_setValidations();
    }

}