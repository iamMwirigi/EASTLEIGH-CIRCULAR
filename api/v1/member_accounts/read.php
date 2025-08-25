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

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['member_id'])) {
    http_response_code(400);
    echo json_encode(["message" => "'member_id' is required.", "response" => "error"]);
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

// Get member accounts with balances and account numbers
$stmt = $db->prepare('
    SELECT 
        ma.id, 
        ma.member_id, 
        ma.account_typeid, 
        ma.created_at,
        ma.acc_number,
        at.name, 
        at.description,
        -- Get balances from member_accounts table
        CASE 
            WHEN at.id = 1 THEN maa.loan_opening_balance
            WHEN at.id = 2 THEN maa.savings_opening_balance
            WHEN at.id = 3 THEN maa.seasonal_tickets_opening_balance
            WHEN at.id = 4 THEN maa.operations_opening_balance
            WHEN at.id = 6 THEN maa.insurance_opening_balance
            ELSE 0
        END as initial_balance,
        CASE 
            WHEN at.id = 1 THEN maa.loan_current_balance
            WHEN at.id = 2 THEN maa.savings_current_balance
            WHEN at.id = 3 THEN maa.seasonal_tickets_current_balance
            WHEN at.id = 4 THEN maa.operations_current_balance
            WHEN at.id = 6 THEN maa.insurance_current_balance
            ELSE 0
        END as current_balance
    FROM member_account_types ma 
    JOIN account_type at ON ma.account_typeid = at.id 
    LEFT JOIN member_accounts maa ON ma.member_id = maa.member_id 
    WHERE ma.member_id = ?
');
$stmt->execute([$data['member_id']]);
$accounts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Generate account number if not exists, otherwise use existing
    $acc_number = $row['acc_number'] ?: generateAccountNumber($db);
    
    // If no acc_number exists, update the member_account_types table
    if (!$row['acc_number']) {
        $update_stmt = $db->prepare('UPDATE member_account_types SET acc_number = ? WHERE id = ?');
        $update_stmt->execute([$acc_number, $row['id']]);
    }
    
    $accounts[] = [
        'id' => $row['id'],
        'acc_number' => $acc_number,
        'initial_balance' => (float)$row['initial_balance'],
        'current_balance' => (float)$row['current_balance'],
        'created_at' => $row['created_at'],
        'account_type' => [
            'id' => $row['account_typeid'],
            'name' => $row['name'],
            'description' => $row['description']
        ]
    ];
}

echo json_encode([
    "message" => "Member accounts retrieved successfully.",
    "response" => "success",
    "data" => [
        "member" => [
            "id" => (int)$member['id'],
            "name" => $member['name'],
            "phone_number" => $member['phone_number'],
            "number" => (int)$member['number']
        ],
        "accounts" => $accounts
    ]
]); 