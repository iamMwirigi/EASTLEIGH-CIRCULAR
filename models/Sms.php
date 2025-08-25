<?php
class Sms {
    private $conn;
    private $table = 'sms';

    public $id;
    public $sent_from;
    public $sent_to;
    public $package_id;
    public $text_message;
    public $af_cost;
    public $sent_time;
    public $sent_date;
    public $sms_characters;
    public $sent_status;
    public $pages;
    public $page_cost;
    public $cost;
    public $db_error;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function read($start_date = null, $end_date = null) {
        $query = 'SELECT
                id,
                sent_from,
                sent_to,
                package_id,
                text_message,
                af_cost,
                sent_time,
                sent_date,
                sms_characters,
                sent_status,
                pages,
                page_cost,
                cost
            FROM
                ' . $this->table;

        $params = [];
        $where_clauses = [];

        if ($start_date) {
            $where_clauses[] = 'sent_date >= ?';
            $params[] = $start_date;
        }
        if ($end_date) {
            $where_clauses[] = 'sent_date <= ?';
            $params[] = $end_date;
        }

        if (!empty($where_clauses)) {
            $query .= ' WHERE ' . implode(' AND ', $where_clauses);
        }

        $query .= ' ORDER BY sent_date DESC, sent_time DESC';

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function create() {
        $query = 'INSERT INTO ' . $this->table . ' SET sent_from = :sent_from, sent_to = :sent_to, package_id = :package_id, text_message = :text_message, af_cost = :af_cost, sent_date = :sent_date, sent_time = :sent_time, sms_characters = :sms_characters, sent_status = :sent_status, pages = :pages, page_cost = :page_cost, cost = :cost';

        $stmt = $this->conn->prepare($query);

        $this->sent_from = htmlspecialchars(strip_tags($this->sent_from));
        $this->sent_to = htmlspecialchars(strip_tags($this->sent_to));
        $this->package_id = htmlspecialchars(strip_tags($this->package_id));
        $this->text_message = htmlspecialchars(strip_tags($this->text_message));
        $this->af_cost = htmlspecialchars(strip_tags($this->af_cost));
        $this->sent_date = htmlspecialchars(strip_tags($this->sent_date));
        $this->sent_time = htmlspecialchars(strip_tags($this->sent_time));
        $this->sms_characters = htmlspecialchars(strip_tags($this->sms_characters));
        $this->sent_status = htmlspecialchars(strip_tags($this->sent_status));
        $this->pages = htmlspecialchars(strip_tags($this->pages));
        $this->page_cost = htmlspecialchars(strip_tags($this->page_cost));
        $this->cost = htmlspecialchars(strip_tags($this->cost));

        $stmt->bindParam(':sent_from', $this->sent_from);
        $stmt->bindParam(':sent_to', $this->sent_to);
        $stmt->bindParam(':package_id', $this->package_id);
        $stmt->bindParam(':text_message', $this->text_message);
        $stmt->bindParam(':af_cost', $this->af_cost);
        $stmt->bindParam(':sent_date', $this->sent_date);
        $stmt->bindParam(':sent_time', $this->sent_time);
        $stmt->bindParam(':sms_characters', $this->sms_characters);
        $stmt->bindParam(':sent_status', $this->sent_status);
        $stmt->bindParam(':pages', $this->pages);
        $stmt->bindParam(':page_cost', $this->page_cost);
        $stmt->bindParam(':cost', $this->cost);

        if ($stmt->execute()) {
            return true;
        }

        // Correctly capture and log PDO errors
        $errorInfo = $stmt->errorInfo();
        $this->db_error = $errorInfo[2]; // Store error for debugging
        error_log("SMS Create DB Error: " . $this->db_error); // Log to server error log
        return false;
    }

    // Static helper to sanitize phone numbers
    public static function sanitizeNumber($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strpos($phone, '0') === 0) {
            $phone = '254' . substr($phone, 1);
        } elseif (strpos($phone, '7') === 0) {
            $phone = '254' . $phone;
        } elseif (strpos($phone, '254') === 0) {
            // Already correct
        } elseif (strpos($phone, '1') === 0) {
            $phone = '254' . $phone;
        } else {
            return null;
        }
        return $phone;
    }

    // Static helper to send SMS via Dix Huit API
    public static function sendTextDixHuit($recipient, $message, $senderID) {
        // Send SMS via API
        $baseUrl = "http://94.72.97.10/api/v2/SendSMS";
        $ch = curl_init($baseUrl);
        $data = array(
            'ApiKey' => '4zO2J0eeE74irbiK7gRlBzn/ovuptXNs9hhiXohnmHk=',
            'ClientId' => '1f7e7003-aef6-439f-a0dc-8e3af7b9b9a1',
            'SenderId' => $senderID,
            'Message' => $message,
            'MobileNumbers' => $recipient
        );
        $payload = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Accept:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $curl_error = curl_error($ch);
        // Log cURL error if any
        if ($curl_error) {
            file_put_contents('/tmp/sms_api_debug.log', date('c') . " | cURL error: $curl_error\n", FILE_APPEND);
        }
        // Log API response
        file_put_contents('/tmp/sms_api_debug.log', date('c') . " | API response: $result\n", FILE_APPEND);
        curl_close($ch);

        // Determine sent_status
        $sent_status = 0;
        if ($curl_error) {
            error_log("SMS API cURL error: $curl_error");
        } else if ($result) {
            // Try to parse API response for success indication (customize as needed)
            $response = json_decode($result, true);
            if (isset($response['status']) && strtolower($response['status']) === 'success') {
                $sent_status = 1;
            } else {
                $sent_status = 0;
                error_log("SMS API response: $result");
            }
        } else {
            error_log("SMS API returned empty result");
        }

        // Log SMS attempt to database
        include_once __DIR__ . '/../config/Database.php';
        $database = new Database();
        $db = $database->connect();
        if ($db) {
            $sms = new Sms($db);
            $sms->sent_from = $senderID;
            $sms->sent_to = $recipient;
            $sms->package_id = '';
            $sms->text_message = $message;
            $sms->af_cost = 0;
            $sms->sent_date = date('Y-m-d');
            $sms->sent_time = date('H:i:s');
            $sms->sms_characters = strlen($message);
            $sms->sent_status = $sent_status;
            $sms->pages = 1;
            $sms->page_cost = 0.8;
            $sms->cost = 0.8;
            $sms->create();
        } else {
            error_log("Failed to log SMS: DB connection error");
        }

        return $result;
    }

    // Static helper to send SMS via Mzigo API
    public static function sendTextMzigo($recipient, $message, $senderID) {
        $baseUrl = "http://94.72.97.10/api/v2/SendSMS";
        $ch = curl_init($baseUrl);
        $data = array(
            'ApiKey' => '91e59dfce79a61c35f3904acb2c71c27aeeef34f847f940fafb4c29674f8805c',
            'ClientId' => 'mzigosms',
            'SenderId' => $senderID,
            'Message' => $message,
            'MobileNumbers' => $recipient
        );
        $payload = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Accept:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $curl_error = curl_error($ch);
        // Log cURL error if any
        if ($curl_error) {
            file_put_contents('/tmp/sms_api_debug.log', date('c') . " | cURL error: $curl_error\n", FILE_APPEND);
        }
        // Log API response
        file_put_contents('/tmp/sms_api_debug.log', date('c') . " | API response: $result\n", FILE_APPEND);
        curl_close($ch);
        return $result;
    }
} 