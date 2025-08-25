<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

$userData = authorize(['admin', 'user']);

$database = new Database();
$db = $database->connect();

// Function to generate unique transaction reference number
function generateTransactionReferenceNumber($db) {
    do {
        // Generate random 13-digit integer (1000000000000 to 9999999999999)
        $random_digits = mt_rand(1000000000000, 9999999999999);
        $reference_number = "TXN-" . $random_digits;
        
        // Check if reference number already exists in transactions
        $check_stmt = $db->prepare('SELECT id FROM transactions WHERE reference_number = ?');
        $check_stmt->execute([$reference_number]);
    } while ($check_stmt->fetch()); // Keep generating until unique
    
    return $reference_number;
}

$data = json_decode(file_get_contents('php://input'), true);

$required = ['member_id','account_type_id','destination_account_type_id','amount'];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(['message' => "$field is required", 'response' => 'error']);
        exit();
    }
}

// Map account_type_id to the corresponding balance column in member_accounts
$balance_columns = [
    1 => 'loan_current_balance',      // Loans
    2 => 'savings_current_balance',   // Savings  
    4 => 'operations_current_balance', // Operations
    6 => 'insurance_current_balance',   // Insurance
    7 => 'county_current_balance' // County
];

if (!isset($balance_columns[$data['account_type_id']])) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid account_type_id', 'response' => 'error']);
    exit();
}
if (!isset($balance_columns[$data['destination_account_type_id']])) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid destination_account_type_id', 'response' => 'error']);
    exit();
}

$balance_column = $balance_columns[$data['account_type_id']];
$dest_balance_column = $balance_columns[$data['destination_account_type_id']];

// Get current balance from member_accounts (source)
$balance_stmt = $db->prepare("SELECT $balance_column FROM member_accounts WHERE member_id = ?");
$balance_stmt->execute([$data['member_id']]);
$current_balance = $balance_stmt->fetch(PDO::FETCH_ASSOC);
if (!$current_balance) {
    // Auto-create member_accounts record if it doesn't exist
    $create_account_stmt = $db->prepare('INSERT INTO member_accounts (member_id, savings_opening_balance, savings_current_balance, loan_opening_balance, loan_current_balance, county_opening_balance, county_current_balance, operations_opening_balance, operations_current_balance, insurance_opening_balance, insurance_current_balance) VALUES (?, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)');
    $create_account_stmt->execute([$data['member_id']]);
    $balance_stmt->execute([$data['member_id']]);
    $current_balance = $balance_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current_balance) {
        http_response_code(400);
        echo json_encode(['message' => 'Failed to create member account', 'response' => 'error']);
        exit();
    }
}
$balance_before = (float)$current_balance[$balance_column];
$amount = (float)$data['amount'];

// Check if source has enough funds
if ($balance_before < $amount) {
    http_response_code(400);
    echo json_encode(['message' => 'Insufficient funds for transfer', 'response' => 'error']);
    exit();
}

// Get destination balance
$dest_balance_stmt = $db->prepare("SELECT $dest_balance_column FROM member_accounts WHERE member_id = ?");
$dest_balance_stmt->execute([$data['member_id']]);
$dest_balance = $dest_balance_stmt->fetch(PDO::FETCH_ASSOC);
if (!$dest_balance) {
    http_response_code(400);
    echo json_encode(['message' => 'Destination account not found', 'response' => 'error']);
    exit();
}
$dest_balance_before = (float)$dest_balance[$dest_balance_column];

// Subtract from source
$balance_after = $balance_before - $amount;
// Add to destination
$dest_balance_after = $dest_balance_before + $amount;

// Update balances
$update_balance_stmt = $db->prepare("UPDATE member_accounts SET $balance_column = ? WHERE member_id = ?");
$update_balance_stmt->execute([$balance_after, $data['member_id']]);
$update_dest_stmt = $db->prepare("UPDATE member_accounts SET $dest_balance_column = ? WHERE member_id = ?");
$update_dest_stmt->execute([$dest_balance_after, $data['member_id']]);

// Set timezone to Nairobi
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Africa/Nairobi');
}
$transaction_date = !empty($data['transaction_date']) ? $data['transaction_date'] : date('Y-m-d');

// Generate reference number
$reference_number = generateTransactionReferenceNumber($db);

// Insert transaction record
$query = 'INSERT INTO transactions (member_id, account_type_id, destination_account_type_id, amount, transaction_type, balance_before, balance_after, description, transaction_date, reference_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
$stmt = $db->prepare($query);
$stmt->execute([
    $data['member_id'],
    $data['account_type_id'],
    $data['destination_account_type_id'],
    $data['amount'],
    'transfer',
    $balance_before,
    $balance_after,
    $data['description'] ?? null,
    $transaction_date,
    $reference_number
]);
$id = $db->lastInsertId();

// Fetch the created transaction with member and account type names
$fetch = $db->prepare('SELECT t.*, m.name as member_name, at.name as member_acc_type_name FROM transactions t JOIN member m ON t.member_id = m.id JOIN account_type at ON t.account_type_id = at.id WHERE t.id = ?');
$fetch->execute([$id]);
$row = $fetch->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $response = [
        'id' => (int)$row['id'],
        'member_id' => (int)$row['member_id'],
        'account_type_id' => (int)$row['account_type_id'],
        'destination_account_type_id' => $row['destination_account_type_id'] ? (int)$row['destination_account_type_id'] : null,
        'amount' => (float)$row['amount'],
        'transaction_type' => $row['transaction_type'],
        'balance_before' => (float)$row['balance_before'],
        'balance_after' => (float)$row['balance_after'],
        'description' => $row['description'],
        'transaction_date' => $row['transaction_date'],
        'reference_number' => $row['reference_number'],
        'member_name' => $row['member_name'],
        'member_acc_type_name' => $row['member_acc_type_name']
    ];
    echo json_encode(['message' => 'Transfer completed successfully', 'response' => 'success', 'data' => $response]);
} else {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to fetch created transfer', 'response' => 'error']);
} 