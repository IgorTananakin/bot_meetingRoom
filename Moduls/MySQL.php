<?php

class MySQL
{
    public function __construct($servername, $username, $password, $database)
    {
        $this->conn = mysqli_connect($servername, $username, $password, $database);
    }

    public function real_escape_string($text)
    {
        return $this->conn->real_escape_string($text);
    }

    public function SQLSelectCustom($sql, $fetch_assoc = true)
    {
        $resultSet = mysqli_query($this->conn, $sql) or die("database error:" . mysqli_error($this->conn));
        if ($resultSet) {
            if ($fetch_assoc) {
                for ($row = array(); $set = mysqli_fetch_assoc($resultSet); $row[] = $set) ;
            } else {
                $row = $resultSet;
            }
        } else {
            return false;
        }

        return ($row);
    }

    public function SQL_Insert($array, $table)
    {
        $pole = implode(",", array_keys($array));
        $sql = "INSERT INTO " . $table . "(" . $pole . ") VALUES ('" . implode("' , '", $array) . "') ";
        return mysqli_query($this->conn, $sql) or die("database error:" . mysqli_error($this->conn));

    }

    public function SQL_Select($array, $table, $where = "", $fetch_assoc = true, $limit = "", $max = "", $disting = '', $groupBy = '')
    {
        if (!empty($groupBy)) {
            $groupBy = "$groupBy";
        }
        if (!empty($where)) {
            $where = "WHERE $where";
        }
        if (!empty($limit)) {
            $limit = "ORDER BY id DESC LIMIT $limit";
        }
        if (!empty($max)) {
            $max = "max($max)";
        }
        if (!empty($disting)) {
            $disting = "DISTINCT $disting";
        }
        $sql = "SELECT $disting $max" . implode(" , ", $array) . " FROM $table $where $groupBy $limit";
        $resultSet = mysqli_query($this->conn, $sql) or die("database error:" . mysqli_error($this->conn));
        if ($fetch_assoc) {
            for ($row = array(); $set = mysqli_fetch_assoc($resultSet); $row[] = $set) ;
        } else {
            $row = $resultSet;
        }

        return ($row);
    }

    public function SQL_Update($array, $table, $where)
    {
        $sql = "UPDATE $table
SET " . implode(" , ", $array) . "
WHERE $where";

        return mysqli_query($this->conn, $sql) or die("database error:" . mysqli_error($this->conn));
    }
    public function SQL_Delete($table, $where)
    {
        $sql = "DELETE FROM $table WHERE $where";
        return mysqli_query($this->conn, $sql) or die("database error:" . mysqli_error($this->conn));
    }
}