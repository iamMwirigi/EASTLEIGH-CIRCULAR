<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

$userData = authorize(['admin']);

$database = new Database();
$db = $database->connect();

if($db === null) {
    http_response_code(503);
    echo json_encode(['message' => 'Failed to connect to the database.', 'response' => 'error']);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->name) || empty($data->phone_number) || empty($data->number)) {
    http_response_code(400);
    echo json_encode(array("message" => "Incomplete data for member.", "response" => "error"));
    return;
}

$query = 'INSERT INTO member SET name = :name, phone_number = :phone_number, number = :number';

$stmt = $db->prepare($query);

// Clean data
$name = htmlspecialchars(strip_tags($data->name));
$phone_number = htmlspecialchars(strip_tags($data->phone_number));
$number = htmlspecialchars(strip_tags($data->number));

// Bind data
$stmt->bindParam(':name', $name);
$stmt->bindParam(':phone_number', $phone_number);
$stmt->bindParam(':number', $number);

if($stmt->execute()) {
    $member_id = $db->lastInsertId();
    
    // Create member_accounts record with default balances
    $member_accounts_stmt = $db->prepare('INSERT INTO member_accounts (member_id, savings_opening_balance, savings_current_balance, loan_opening_balance, loan_current_balance, county_opening_balance, county_current_balance, operations_opening_balance, operations_current_balance, insurance_opening_balance, insurance_current_balance) VALUES (?, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)');
    $member_accounts_stmt->execute([$member_id]);
    
    // Handle accounts if provided
    $accounts = [];
    if (!empty($data->accounts) && is_array($data->accounts)) {
        foreach ($data->accounts as $account_type_id) {
            $insert_acc = $db->prepare('INSERT INTO member_account_types (member_id, account_typeid) VALUES (?, ?)');
            $insert_acc->execute([$member_id, $account_type_id]);
        }
        // Fetch accounts with joined account_type info
        $accounts_stmt = $db->prepare('SELECT ma.id, ma.member_id, ma.account_typeid, at.name, at.description FROM member_account_types ma JOIN account_type at ON ma.account_typeid = at.id WHERE ma.member_id = ?');
        $accounts_stmt->execute([$member_id]);
        while ($acc_row = $accounts_stmt->fetch(PDO::FETCH_ASSOC)) {
            $accounts[] = [
                'id' => $acc_row['id'],
                'member_id' => $acc_row['member_id'],
                'account_type' => [
                    'id' => $acc_row['account_typeid'],
                    'name' => $acc_row['name'],
                    'description' => $acc_row['description']
                ]
            ];
        }
    }
    $member = [
        'id' => $member_id,
        'name' => $name,
        'phone_number' => $phone_number,
        'number' => $number,
        'accounts' => $accounts
    ];
    http_response_code(201);
    echo json_encode([
        'message' => 'Member created successfully',
        'response' => 'success',
        'data' => $member
    ]);
} else {
    http_response_code(500);
    echo json_encode(
        array('message' => 'Member Not Created', 'response' => 'error')
    );
} 