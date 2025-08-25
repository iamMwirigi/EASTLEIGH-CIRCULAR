<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

$userData = authorize(['admin', 'user']);

$database = new Database();
$db = $database->connect();

if($db === null) {
    http_response_code(503);
    echo json_encode(['message' => 'Failed to connect to the database.', 'response' => 'error']);
    exit();
}

$id = isset($_GET['id']) ? $_GET['id'] : die(json_encode(['message' => 'Member ID not provided.']));

$query = 'SELECT id, name, phone_number, number FROM member WHERE id = ?';

$stmt = $db->prepare($query);
$stmt->bindParam(1, $id);
$stmt->execute();

$num = $stmt->rowCount();

if($num > 0) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    extract($row);
    // Fetch accounts for this member
    $accounts_stmt = $db->prepare('SELECT ma.id, ma.member_id, ma.account_typeid, at.name, at.description FROM member_account_types ma JOIN account_type at ON ma.account_typeid = at.id WHERE ma.member_id = ?');
    $accounts_stmt->execute([$id]);
    $accounts = [];
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
    // Fetch member account balances
    $balances_stmt = $db->prepare('SELECT * FROM member_accounts WHERE member_id = ?');
    $balances_stmt->execute([$id]);
    $balances = $balances_stmt->fetch(PDO::FETCH_ASSOC);
    
    $member_item = array(
        'id' => $id,
        'name' => $name,
        'phone_number' => $phone_number,
        'number' => $number,
        'accounts' => $accounts,
        'balances' => $balances ? [
            'savings_current_balance' => (float)$balances['savings_current_balance'],
            'loan_current_balance' => (float)$balances['loan_current_balance'],
            'seasonal_tickets_current_balance' => (float)$balances['seasonal_tickets_current_balance'],
            'insurance_current_balance' => (float)$balances['insurance_current_balance'],
            'operations_current_balance' => (float)$balances['operations_current_balance']
        ] : null
    );
    
    echo json_encode([
        'message' => 'Member found',
        'response' => 'success',
        'data' => $member_item
    ]);

} else {
    http_response_code(404);
    echo json_encode(
        array('message' => 'Member not found.', 'response' => 'error')
    );
} 