<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';

$database = new Database();
$db = $database->connect();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["message" => "Failed to connect to the database.", "response" => "error"]);
    exit();
}

// Function to generate unique account number
function generateAccountNumber($db) {
    do {
        // Generate random 10-digit integer (1000000000 to 9999999999)
        $random_digits = mt_rand(1000000000, 9999999999);
        $account_number = "ACCT-" . $random_digits;
        
        // Check if account number already exists in member_account_types
        $check_stmt = $db->prepare('SELECT id FROM member_account_types WHERE acc_number = ?');
        $check_stmt->execute([$account_number]);
    } while ($check_stmt->fetch()); // Keep generating until unique
    
    return $account_number;
}

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['member_id']) || empty($data['account_type_id'])) {
    http_response_code(400);
    echo json_encode(["message" => "'member_id' and 'account_type_id' are required.", "response" => "error"]);
    exit();
}

// First get member details
$member_stmt = $db->prepare('SELECT id, name, phone_number, number FROM member WHERE id = ?');
$member_stmt->execute([$data['member_id']]);
$member = $member_stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    http_response_code(404);
    echo json_encode(["message" => "Member not found.", "response" => "error"]);
    exit();
}

// Generate account number
$acc_number = generateAccountNumber($db);

$stmt = $db->prepare('INSERT INTO member_account_types (member_id, account_typeid, acc_number) VALUES (?, ?, ?)');
$stmt->execute([$data['member_id'], $data['account_type_id'], $acc_number]);
$id = $db->lastInsertId();

$fetch = $db->prepare('SELECT ma.id, ma.member_id, ma.account_typeid, ma.acc_number, at.name, at.description FROM member_account_types ma JOIN account_type at ON ma.account_typeid = at.id WHERE ma.id = ?');
$fetch->execute([$id]);
$row = $fetch->fetch(PDO::FETCH_ASSOC);
$account = [
    'id' => $row['id'],
    'acc_number' => $row['acc_number'],
    'account_type' => [
        'id' => $row['account_typeid'],
        'name' => $row['name'],
        'description' => $row['description']
    ]
];

echo json_encode([
    "message" => "Member account created successfully.",
    "response" => "success",
    "data" => [
        "member" => [
            "id" => (int)$member['id'],
            "name" => $member['name'],
            "phone_number" => $member['phone_number'],
            "number" => (int)$member['number']
        ],
        "account" => $account
    ]
]); 