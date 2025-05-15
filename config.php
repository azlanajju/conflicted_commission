<?php
class Database
{
    private $host = "srv1752.hstgr.io";
    private $db_name = "u229215627_goldenDreamSQL";
    private $username = "u229215627_GoldenDreamSQL";
    private $password = "Azl@n2002";
    public $conn;

    // Base URL configuration
    public static $baseUrl = "https://goldendream.in/";

    public function getConnection()
    {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
        }

        return $this->conn;
    }
}