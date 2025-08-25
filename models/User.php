<?php

class User
{
    private $conn;
    private $table_name = "_user_";

    public $id;
    public $username;
    public $password;
    public $name;
    public $stage;
    public $user_town;
    public $quota_start;
    public $quota_end;
    public $current_quota;
    public $delete_status;
    public $prefix;
    public $printer_name;
    public $stage_id;
    public $phone_number;
    public $printer_id;


    public function __construct($db)
    {
        $this->conn = $db;
    }

    function read()
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE delete_status = 0";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    function readOne()
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? AND delete_status = 0 LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = $row['id'];
            $this->username = $row['username'];
            $this->name = $row['name'];
            $this->stage = $row['stage'];
            $this->user_town = $row['user_town'];
            $this->quota_start = $row['quota_start'];
            $this->quota_end = $row['quota_end'];
            $this->current_quota = $row['current_quota'];
            $this->delete_status = $row['delete_status'];
            $this->prefix = $row['prefix'];
            $this->printer_name = $row['printer_name'];
            return true;
        }
        return false;
    }

    function create()
    {
        $query = "INSERT INTO " . $this->table_name . " SET
            username=:username, password=:password, name=:name, stage=:stage, user_town=:user_town, quota_start=:quota_start, quota_end=:quota_end, current_quota=:current_quota, prefix=:prefix, printer_name=:printer_name, stage_id=:stage_id, phone_number=:phone_number, printer_id=:printer_id";

        $stmt = $this->conn->prepare($query);

        $this->username = isset($this->username) ? htmlspecialchars(strip_tags($this->username)) : '';
        $this->password = isset($this->password) ? htmlspecialchars(strip_tags($this->password)) : '';
        $this->name = isset($this->name) ? htmlspecialchars(strip_tags($this->name)) : '';
        $this->stage = isset($this->stage) ? htmlspecialchars(strip_tags($this->stage)) : '';
        $this->user_town = ($this->user_town !== '' && $this->user_town !== null) ? $this->user_town : null;
        $this->quota_start = ($this->quota_start !== '' && $this->quota_start !== null) ? $this->quota_start : null;
        $this->quota_end = ($this->quota_end !== '' && $this->quota_end !== null) ? $this->quota_end : null;
        $this->current_quota = ($this->current_quota !== '' && $this->current_quota !== null) ? $this->current_quota : null;
        $this->prefix = isset($this->prefix) ? htmlspecialchars(strip_tags($this->prefix)) : '';
        $this->printer_name = isset($this->printer_name) ? htmlspecialchars(strip_tags($this->printer_name)) : '';
        $this->stage_id = ($this->stage_id !== '' && $this->stage_id !== null) ? $this->stage_id : null;
        $this->phone_number = isset($this->phone_number) ? htmlspecialchars(strip_tags($this->phone_number)) : '';
        $this->printer_id = ($this->printer_id !== '' && $this->printer_id !== null) ? $this->printer_id : null;

        $stmt->bindParam(":username", $this->username);
        $password_hash = password_hash($this->password, PASSWORD_BCRYPT);
        $stmt->bindParam(":password", $password_hash);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":stage", $this->stage);
        $stmt->bindParam(":user_town", $this->user_town, PDO::PARAM_INT);
        $stmt->bindParam(":quota_start", $this->quota_start, PDO::PARAM_INT);
        $stmt->bindParam(":quota_end", $this->quota_end, PDO::PARAM_INT);
        $stmt->bindParam(":current_quota", $this->current_quota, PDO::PARAM_INT);
        $stmt->bindParam(":prefix", $this->prefix);
        $stmt->bindParam(":printer_name", $this->printer_name);
        $stmt->bindParam(":stage_id", $this->stage_id, PDO::PARAM_INT);
        $stmt->bindParam(":phone_number", $this->phone_number);
        $stmt->bindParam(":printer_id", $this->printer_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    function update()
    {
        $set_password = '';
        if (!empty($this->password)) {
            $set_password = ', password = :password';
        }
        $query = "UPDATE " . $this->table_name . " SET
            username = :username,
            name = :name,
            stage = :stage,
            user_town = :user_town,
            quota_start = :quota_start,
            quota_end = :quota_end,
            current_quota = :current_quota,
            prefix = :prefix,
            printer_name = :printer_name,
            stage_id = :stage_id,
            phone_number = :phone_number,
            printer_id = :printer_id
            $set_password
            WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->username = isset($this->username) ? htmlspecialchars(strip_tags($this->username)) : '';
        $this->name = isset($this->name) ? htmlspecialchars(strip_tags($this->name)) : '';
        $this->stage = isset($this->stage) ? htmlspecialchars(strip_tags($this->stage)) : '';
        $this->user_town = ($this->user_town !== '' && $this->user_town !== null) ? $this->user_town : 0;
        $this->quota_start = ($this->quota_start !== '' && $this->quota_start !== null) ? $this->quota_start : 0;
        $this->quota_end = ($this->quota_end !== '' && $this->quota_end !== null) ? $this->quota_end : 0;
        $this->current_quota = ($this->current_quota !== '' && $this->current_quota !== null) ? $this->current_quota : 0;
        $this->prefix = isset($this->prefix) ? htmlspecialchars(strip_tags($this->prefix)) : '';
        $this->printer_name = isset($this->printer_name) ? htmlspecialchars(strip_tags($this->printer_name)) : '';
        $this->stage_id = ($this->stage_id !== '' && $this->stage_id !== null) ? $this->stage_id : null;
        $this->phone_number = isset($this->phone_number) ? htmlspecialchars(strip_tags($this->phone_number)) : '';
        $this->printer_id = ($this->printer_id !== '' && $this->printer_id !== null) ? $this->printer_id : null;
        $this->id = $this->id;

        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':stage', $this->stage);
        $stmt->bindParam(':user_town', $this->user_town, PDO::PARAM_INT);
        $stmt->bindParam(':quota_start', $this->quota_start, PDO::PARAM_INT);
        $stmt->bindParam(':quota_end', $this->quota_end, PDO::PARAM_INT);
        $stmt->bindParam(':current_quota', $this->current_quota, PDO::PARAM_INT);
        $stmt->bindParam(':prefix', $this->prefix);
        $stmt->bindParam(':printer_name', $this->printer_name);
        $stmt->bindParam(':stage_id', $this->stage_id, PDO::PARAM_INT);
        $stmt->bindParam(':phone_number', $this->phone_number);
        $stmt->bindParam(':printer_id', $this->printer_id, PDO::PARAM_INT);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        if (!empty($this->password)) {
            $password_hash = password_hash($this->password, PASSWORD_BCRYPT);
            $stmt->bindParam(':password', $password_hash);
        }
        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
    
    function delete()
    {
        $query = "UPDATE " . $this->table_name . " SET delete_status = 1 WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(1, $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
} 