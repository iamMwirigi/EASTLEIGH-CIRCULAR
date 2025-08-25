<?php
class Stage {
    private $conn;
    private $table = 'stage';

    public $id;
    public $name;
    public $prefix;
    public $quota_start;
    public $quota_end;
    public $current_quota;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = 'INSERT INTO ' . $this->table . ' SET name = :name, prefix = :prefix, quota_start = :quota_start, quota_end = :quota_end, current_quota = :current_quota';

        $stmt = $this->conn->prepare($query);

        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->prefix = htmlspecialchars(strip_tags($this->prefix));
        $this->quota_start = htmlspecialchars(strip_tags($this->quota_start));
        $this->quota_end = htmlspecialchars(strip_tags($this->quota_end));
        $this->current_quota = htmlspecialchars(strip_tags($this->current_quota));

        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':prefix', $this->prefix);
        $stmt->bindParam(':quota_start', $this->quota_start);
        $stmt->bindParam(':quota_end', $this->quota_end);
        $stmt->bindParam(':current_quota', $this->current_quota);

        if($stmt->execute()) {
            return true;
        }

        printf("Error: %s.\n", $stmt->error);

        return false;
    }

    public function read() {
        $query = 'SELECT
                id,
                name,
                prefix,
                quota_start,
                quota_end,
                current_quota
            FROM
                ' . $this->table . '
            ORDER BY
                name DESC';

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    public function read_one() {
        $query = 'SELECT
                id,
                name,
                prefix,
                quota_start,
                quota_end,
                current_quota
            FROM
                ' . $this->table . '
            WHERE
                id = ?
            LIMIT 0,1';

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->name = $row['name'];
        $this->prefix = $row['prefix'];
        $this->quota_start = $row['quota_start'];
        $this->quota_end = $row['quota_end'];
        $this->current_quota = $row['current_quota'];
    }

    public function update() {
        $fields = [];
        $params = [':id' => $this->id];

        if ($this->name !== null) {
            $fields[] = "name = :name";
            $params[':name'] = htmlspecialchars(strip_tags($this->name));
        }
        if ($this->prefix !== null) {
            $fields[] = "prefix = :prefix";
            $params[':prefix'] = htmlspecialchars(strip_tags($this->prefix));
        }
        if ($this->quota_start !== null) {
            $fields[] = "quota_start = :quota_start";
            $params[':quota_start'] = htmlspecialchars(strip_tags($this->quota_start));
        }
        if ($this->quota_end !== null) {
            $fields[] = "quota_end = :quota_end";
            $params[':quota_end'] = htmlspecialchars(strip_tags($this->quota_end));
        }
        if ($this->current_quota !== null) {
            $fields[] = "current_quota = :current_quota";
            $params[':current_quota'] = htmlspecialchars(strip_tags($this->current_quota));
        }

        if (empty($fields)) {
            // Nothing to update
            return true;
        }

        $query = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $fields) . ' WHERE id = :id';

        $stmt = $this->conn->prepare($query);

        if ($stmt->execute($params)) {
            return true;
        }

        printf("Error: %s.\n", $stmt->error);

        return false;
    }

    public function delete() {
        $query = 'DELETE FROM ' . $this->table . ' WHERE id = :id';

        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(':id', $this->id);

        if($stmt->execute()) {
            return true;
        }

        printf("Error: %s.\n", $stmt->error);

        return false;
    }
} 