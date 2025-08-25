<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

$userData = authorize(['admin', 'user']);

$database = new Database();
$db = $database->connect();

// Allow filtering by member_id, account_type_id, date range
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$member_id = $input['member_id'] ?? $_GET['member_id'] ?? null;
$account_type_id = $input['account_type_id'] ?? $_GET['account_type_id'] ?? null;
$start_date = $input['start_date'] ?? $_GET['start_date'] ?? null;
$end_date = $input['end_date'] ?? $_GET['end_date'] ?? null;

$where = [];
$params = [];
if ($member_id) {
    $where[] = 't.member_id = ?';
    $params[] = $member_id;
}
if ($account_type_id) {
    $where[] = 't.account_type_id = ?';
    $params[] = $account_type_id;
}
if ($start_date) {
    $where[] = 't.transaction_date >= ?';
    $params[] = $start_date;
}
if ($end_date) {
    $where[] = 't.transaction_date <= ?';
    $params[] = $end_date;
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$query = 'SELECT t.*, m.name as member_name, at.name as member_acc_type_name FROM transactions t JOIN member m ON t.member_id = m.id JOIN account_type at ON t.account_type_id = at.id ' . $where_sql . ' ORDER BY t.transaction_date DESC, t.created_at DESC';
$stmt = $db->prepare($query);
$stmt->execute($params);

$transactions = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $transactions[] = [
        'id' => (int)$row['id'],
        'member_id' => (int)$row['member_id'],
        'member_name' => $row['member_name'],
        'initial_balance' => (float)$row['balance_before'],
        'amount_transacted' => (float)$row['amount'],
        'current_balance' => (float)$row['balance_after'],
        'transaction_type' => $row['transaction_type'],
        'description' => $row['description'],
        'reference_number' => $row['reference_number'],
        'created_at' => date('H:i', strtotime($row['created_at'])),
        'date' => date('d-m-Y', strtotime($row['transaction_date'])),
        'member_acc_type_id' => (int)$row['account_type_id'],
        'member_acc_type_name' => $row['member_acc_type_name']
    ];
}

$response = [
    'Account_statement' => [
        'transactions' => $transactions
    ]
];
echo json_encode($response); 