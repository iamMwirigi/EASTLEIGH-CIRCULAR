<?php

class Member
{
    private $conn;
    private $table_name = "member";

    public $id;
    public $name;
    public $phone_number;
    public $number;
    public $password;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    function resetPassword()
    {
        $query = "UPDATE " . $this->table_name . "
                SET
                    entry_code = :entry_code
                WHERE
                    phone_number = :phone_number AND entry_code = '0000'";

        $stmt = $this->conn->prepare($query);

        $this->password = htmlspecialchars(strip_tags($this->password));
        $this->phone_number = htmlspecialchars(strip_tags($this->phone_number));

        $stmt->bindParam(':entry_code', $this->password);
        $stmt->bindParam(':phone_number', $this->phone_number);

        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }
} 