<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

$userData = authorize(['admin', 'user']);

$database = new Database();
$db = $database->connect();

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['id'])) {
    http_response_code(400);
    echo json_encode(['message' => 'Transaction id is required', 'response' => 'error']);
    exit();
}

// Only update fields that are provided
$fields = ['member_id','account_type_id','amount','amount_transacted','transaction_type','description'];
$set = [];
$params = [];

foreach ($fields as $field) {
    if (isset($data[$field])) {
        // Map amount_transacted to amount for database
        $db_field = $field === 'amount_transacted' ? 'amount' : $field;
        $set[] = "$db_field = ?";
        $params[] = $data[$field];
    }
}

if (empty($set)) {
    http_response_code(400);
    echo json_encode(['message' => 'No fields to update', 'response' => 'error']);
    exit();
}

// For transfers, we need destination_account_type_id
if (isset($data['transaction_type']) && $data['transaction_type'] === 'transfer' && !isset($data['destination_account_type_id'])) {
    http_response_code(400);
    echo json_encode(['message' => 'destination_account_type_id is required for transfers', 'response' => 'error']);
    exit();
}

// Map account_type_id to the corresponding balance column in member_accounts
$balance_columns = [
    1 => 'loan_current_balance',      // Loans
    2 => 'savings_current_balance',   // Savings  
    4 => 'operations_current_balance', // Operations
    6 => 'insurance_current_balance',   // Insurance
    7 => 'county_current_balance' // County
];

// Get the current transaction to revert its effect
$current_stmt = $db->prepare('SELECT * FROM transactions WHERE id = ?');
$current_stmt->execute([$data['id']]);
$current_transaction = $current_stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_transaction) {
    http_response_code(404);
    echo json_encode(['message' => 'Transaction not found', 'response' => 'error']);
    exit();
}

if (!isset($balance_columns[$current_transaction['account_type_id']])) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid account_type_id', 'response' => 'error']);
    exit();
}

$balance_column = $balance_columns[$current_transaction['account_type_id']];

// Get current balance from member_accounts
$balance_stmt = $db->prepare("SELECT $balance_column FROM member_accounts WHERE member_id = ?");
$balance_stmt->execute([$current_transaction['member_id']]);
$current_balance = $balance_stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_balance) {
    // Auto-create member_accounts record if it doesn't exist
    $create_account_stmt = $db->prepare('INSERT INTO member_accounts (member_id, savings_opening_balance, savings_current_balance, loan_opening_balance, loan_current_balance, county_opening_balance, county_current_balance, operations_opening_balance, operations_current_balance, insurance_opening_balance, insurance_current_balance) VALUES (?, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)');
    $create_account_stmt->execute([$current_transaction['member_id']]);
    
    // Now get the balance again
    $balance_stmt->execute([$current_transaction['member_id']]);
    $current_balance = $balance_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_balance) {
        http_response_code(400);
        echo json_encode(['message' => 'Failed to create member account', 'response' => 'error']);
        exit();
    }
}

$balance_before = (float)$current_balance[$balance_column];

// Revert the effect of the current transaction
$old_amount = (float)$current_transaction['amount'];
switch ($current_transaction['transaction_type']) {
    case 'deposit':
    case 'interest':
        $balance_before = $balance_before - $old_amount; // Revert deposit
        break;
    case 'withdrawal':
    case 'fee':
        // Special handling for loan withdrawals (payments)
        if ($current_transaction['account_type_id'] == 1) { // Loan account (ID 1)
            $old_loan_balance = $balance_before;
            $old_payment = $old_amount;
            
            // Revert the loan balance
            $balance_before = $old_loan_balance + $old_payment;
            
            // If there was excess that went to savings, revert that too
            $old_savings_stmt = $db->prepare("SELECT savings_current_balance FROM member_accounts WHERE member_id = ?");
            $old_savings_stmt->execute([$current_transaction['member_id']]);
            $old_savings_balance = $old_savings_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($old_savings_balance) {
                // Calculate how much excess was added to savings
                $old_loan_balance_before = $old_loan_balance + $old_payment;
                if ($old_payment > $old_loan_balance_before) {
                    $old_excess = $old_payment - $old_loan_balance_before;
                    $revert_savings_balance = (float)$old_savings_balance['savings_current_balance'] - $old_excess;
                    $update_savings_stmt = $db->prepare("UPDATE member_accounts SET savings_current_balance = ? WHERE member_id = ?");
                    $update_savings_stmt->execute([$revert_savings_balance, $current_transaction['member_id']]);
                }
            }
        } else {
            // Regular withdrawal revert
            $balance_before = $balance_before + $old_amount; // Revert withdrawal
        }
        break;
    case 'transfer':
        $balance_before = $balance_before + $old_amount; // Revert transfer (add back to source)
        // Also revert destination account if we have the destination info
        if (isset($data['destination_account_type_id']) && isset($balance_columns[$data['destination_account_type_id']])) {
            $dest_balance_column = $balance_columns[$data['destination_account_type_id']];
            $dest_balance_stmt = $db->prepare("SELECT $dest_balance_column FROM member_accounts WHERE member_id = ?");
            $dest_balance_stmt->execute([$current_transaction['member_id']]);
            $dest_balance = $dest_balance_stmt->fetch(PDO::FETCH_ASSOC);
            if ($dest_balance) {
                $dest_balance_after = (float)$dest_balance[$dest_balance_column] - $old_amount; // Revert destination
                $update_dest_stmt = $db->prepare("UPDATE member_accounts SET $dest_balance_column = ? WHERE member_id = ?");
                $update_dest_stmt->execute([$dest_balance_after, $current_transaction['member_id']]);
            }
        }
        break;
}

// Calculate new balance_after based on updated transaction
$new_amount = (float)($data['amount'] ?? $data['amount_transacted'] ?? $current_transaction['amount']);
$new_transaction_type = $data['transaction_type'] ?? $current_transaction['transaction_type'];

switch ($new_transaction_type) {
    case 'deposit':
    case 'interest':
        $balance_after = $balance_before + $new_amount;
        break;
    case 'withdrawal':
    case 'fee':
        // Check if withdrawal amount exceeds balance (except for loans)
        if ($current_transaction['account_type_id'] != 1 && $new_amount > $balance_before) { // Not loan account
            http_response_code(400);
            echo json_encode(['message' => 'Insufficient funds for withdrawal', 'response' => 'error']);
            exit();
        }
        
        // Special handling for loan withdrawals (payments)
        if ($current_transaction['account_type_id'] == 1) { // Loan account (ID 1)
            $loan_balance = $balance_before;
            $payment_amount = $new_amount;
            
            if ($loan_balance <= 0) {
                // No existing loan balance - entire payment goes to savings
                $balance_after = 0; // Loan balance stays at 0
                
                // Add entire amount to savings account
                $savings_stmt = $db->prepare("SELECT savings_current_balance FROM member_accounts WHERE member_id = ?");
                $savings_stmt->execute([$current_transaction['member_id']]);
                $savings_balance = $savings_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($savings_balance) {
                    $new_savings_balance = (float)$savings_balance['savings_current_balance'] + $payment_amount;
                    $update_savings_stmt = $db->prepare("UPDATE member_accounts SET savings_current_balance = ? WHERE member_id = ?");
                    $update_savings_stmt->execute([$new_savings_balance, $current_transaction['member_id']]);
                }
            } else if ($payment_amount <= $loan_balance) {
                // Payment is less than or equal to loan balance
                $balance_after = $loan_balance - $payment_amount;
            } else {
                // Payment exceeds loan balance - excess goes to savings
                $balance_after = 0; // Loan is fully paid
                $excess_amount = $payment_amount - $loan_balance;
                
                // Add excess to savings account
                $savings_stmt = $db->prepare("SELECT savings_current_balance FROM member_accounts WHERE member_id = ?");
                $savings_stmt->execute([$current_transaction['member_id']]);
                $savings_balance = $savings_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($savings_balance) {
                    $new_savings_balance = (float)$savings_balance['savings_current_balance'] + $excess_amount;
                    $update_savings_stmt = $db->prepare("UPDATE member_accounts SET savings_current_balance = ? WHERE member_id = ?");
                    $update_savings_stmt->execute([$new_savings_balance, $current_transaction['member_id']]);
                }
            }
        } else {
            // Regular withdrawal
            $balance_after = $balance_before - $new_amount;
        }
        break;
    case 'transfer':
        http_response_code(400);
        echo json_encode(['message' => 'Use the dedicated transfer endpoint for transfers', 'response' => 'error']);
        exit();
    default:
        http_response_code(400);
        echo json_encode(['message' => 'Invalid transaction_type', 'response' => 'error']);
        exit();
}

// Update the source member_accounts balance
$update_balance_stmt = $db->prepare("UPDATE member_accounts SET $balance_column = ? WHERE member_id = ?");
$update_balance_stmt->execute([$balance_after, $current_transaction['member_id']]);

// Add balance fields to the update
$set[] = "balance_before = ?";
$set[] = "balance_after = ?";
$params[] = $balance_before;
$params[] = $balance_after;

$params[] = $data['id'];
$query = 'UPDATE transactions SET ' . implode(', ', $set) . ' WHERE id = ?';
$stmt = $db->prepare($query);
if ($stmt->execute($params)) {
    // Fetch the updated transaction
    $fetch = $db->prepare('SELECT t.*, m.name as member_name, at.name as member_acc_type_name FROM transactions t JOIN member m ON t.member_id = m.id JOIN account_type at ON t.account_type_id = at.id WHERE t.id = ?');
    $fetch->execute([$data['id']]);
    $row = $fetch->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $response = [
            'id' => (int)$row['id'],
            'member_id' => (int)$row['member_id'],
            'member_name' => $row['member_name'],
            'initial_balance' => (float)$row['balance_before'],
            'amount_transacted' => (float)$row['amount'],
            'current_balance' => (float)$row['balance_after'],
            'description' => $row['transaction_type'],
            'reference_number' => $row['reference_number'],
            'created_at' => date('H:i', strtotime($row['created_at'])),
            'date' => date('d-m-Y', strtotime($row['transaction_date'])),
            'member_acc_type_id' => (int)$row['account_type_id'],
            'member_acc_type_name' => $row['member_acc_type_name']
        ];
        echo json_encode(['message' => 'Transaction updated successfully', 'response' => 'success', 'data' => $response]);
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Transaction not found after update', 'response' => 'error']);
    }
} else {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to update transaction', 'response' => 'error']);
} 