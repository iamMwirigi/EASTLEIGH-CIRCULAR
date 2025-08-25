<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: DELETE');
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

// Fetch the transaction before deleting
$fetch = $db->prepare('SELECT t.*, m.name as member_name, at.name as member_acc_type_name FROM transactions t JOIN member m ON t.member_id = m.id JOIN account_type at ON t.account_type_id = at.id WHERE t.id = ?');
$fetch->execute([$data['id']]);
$row = $fetch->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    echo json_encode(['message' => 'Transaction not found', 'response' => 'error']);
    exit();
}

$db->beginTransaction();

try {
    // Map account_type_id to the corresponding balance column in member_accounts
    $balance_columns = [
        1 => 'loan_current_balance',
        2 => 'savings_current_balance',
        3 => 'seasonal_tickets_current_balance',
        4 => 'operations_current_balance',
        6 => 'insurance_current_balance'
    ];

    $account_type_id = $row['account_type_id'];
    if (!isset($balance_columns[$account_type_id])) {
        throw new Exception('Invalid account_type_id in transaction being deleted.');
    }

    $balance_column = $balance_columns[$account_type_id];
    $member_id = $row['member_id'];
    $amount = (float)$row['amount'];
    $transaction_type = $row['transaction_type'];

    // Get current balance from member_accounts
    $balance_stmt = $db->prepare("SELECT $balance_column, savings_current_balance FROM member_accounts WHERE member_id = ?");
    $balance_stmt->execute([$member_id]);
    $balances = $balance_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$balances) {
        throw new Exception('Member account not found for transaction being deleted.');
    }

    $current_balance = (float)$balances[$balance_column];
    $reverted_balance = $current_balance;

    // Revert the balance
    switch ($transaction_type) {
        case 'deposit':
        case 'interest':
            $reverted_balance -= $amount;
            break;
        case 'withdrawal':
        case 'fee':
            // Special handling for loan withdrawals (payments)
            if ($account_type_id == 1) {
                $reverted_balance += $amount;
                // Check if this payment created an excess that went to savings
                $loan_balance_before_payment = (float)$row['balance_before'];
                if ($amount > $loan_balance_before_payment && $loan_balance_before_payment > 0) {
                    $excess_to_savings = $amount - $loan_balance_before_payment;
                    $reverted_savings_balance = (float)$balances['savings_current_balance'] - $excess_to_savings;
                    
                    $update_savings_stmt = $db->prepare("UPDATE member_accounts SET savings_current_balance = ? WHERE member_id = ?");
                    $update_savings_stmt->execute([$reverted_savings_balance, $member_id]);
                }
            } else {
                $reverted_balance += $amount;
            }
            break;
        case 'transfer':
            throw new Exception('Deleting transfer transactions is not supported via this endpoint.');
            break;
    }

    // Update the member_accounts balance
    $update_balance_stmt = $db->prepare("UPDATE member_accounts SET $balance_column = ? WHERE member_id = ?");
    $update_balance_stmt->execute([$reverted_balance, $member_id]);

    // Now, delete the transaction
    $stmt = $db->prepare('DELETE FROM transactions WHERE id = ?');
    if (!$stmt->execute([$data['id']])) {
        throw new Exception('Failed to delete the transaction record itself.');
    }

    // If all went well, commit the transaction
    $db->commit();

    // Return the details of the deleted transaction
    $response_data = [
        'id' => (int)$row['id'],
        'member_name' => $row['member_name'],
        'amount' => (float)$row['amount'],
        'transaction_type' => $row['transaction_type'],
        'account_type_name' => $row['member_acc_type_name']
    ];
    echo json_encode(['message' => 'Transaction deleted and balance reverted successfully', 'response' => 'success', 'data' => $response_data]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['message' => 'Failed to delete transaction: ' . $e->getMessage(), 'response' => 'error']);
}