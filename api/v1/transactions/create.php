<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
use AfricasTalking\SDK\AfricasTalking;
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

$required = ['member_id','account_type_id','amount','transaction_type'];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(['message' => "$field is required", 'response' => 'error']);
        exit();
    }
}

// For transfers, we need destination_account_type_id
if ($data['transaction_type'] === 'transfer' && !isset($data['destination_account_type_id'])) {
    http_response_code(400);
    echo json_encode(['message' => 'destination_account_type_id is required for transfers', 'response' => 'error']);
    exit();
}

// Map account_type_id to the corresponding balance column in member_accounts
$balance_columns = [
    1 => 'loan_current_balance',      // Loans
    2 => 'savings_current_balance',   // Savings  
    4 => 'operations_current_balance', // Operations
    7 => 'county_current_balance', // County
    6 => 'insurance_current_balance'   // Insurance
];

if (!isset($balance_columns[$data['account_type_id']])) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid account_type_id', 'response' => 'error']);
    exit();
}

$balance_column = $balance_columns[$data['account_type_id']];

// Get current balance from member_accounts
$balance_stmt = $db->prepare("SELECT $balance_column FROM member_accounts WHERE member_id = ?");
$balance_stmt->execute([$data['member_id']]);
$current_balance = $balance_stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_balance) {
    // Auto-create member_accounts record if it doesn't exist
    $create_account_stmt = $db->prepare('INSERT INTO member_accounts (member_id, savings_opening_balance, savings_current_balance, loan_opening_balance, loan_current_balance, county_opening_balance, county_current_balance, operations_opening_balance, operations_current_balance, insurance_opening_balance, insurance_current_balance) VALUES (?, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)');
    $create_account_stmt->execute([$data['member_id']]);
    
    // Now get the balance again
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

// Calculate balance_after based on transaction_type
switch ($data['transaction_type']) {
    case 'deposit':
    case 'interest':
        $balance_after = $balance_before + $amount;
        break;
    case 'withdrawal':
    case 'fee':
        // Check if withdrawal amount exceeds balance (except for loans)
        // Ensure proper type casting for comparison
        $account_type_id = (int)$data['account_type_id'];
        
        if ($account_type_id != 1 && $amount > $balance_before) { // Not loan account
            http_response_code(400);
            echo json_encode(['message' => 'Insufficient funds for withdrawal. Available balance: ' . $balance_before . ', Requested amount: ' . $amount, 'response' => 'error']);
            exit();
        }
        
        // Special handling for loan withdrawals (payments)
        if ($account_type_id == 1) { // Loan account (ID 1)
            $loan_balance = $balance_before;
            $payment_amount = $amount;
            
            if ($loan_balance <= 0) {
                // No existing loan balance - entire payment goes to savings
                $balance_after = 0; // Loan balance stays at 0
                
                // Add entire amount to savings account
                $savings_stmt = $db->prepare("SELECT savings_current_balance FROM member_accounts WHERE member_id = ?");
                $savings_stmt->execute([$data['member_id']]);
                $savings_balance = $savings_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($savings_balance) {
                    $new_savings_balance = (float)$savings_balance['savings_current_balance'] + $payment_amount;
                    $update_savings_stmt = $db->prepare("UPDATE member_accounts SET savings_current_balance = ? WHERE member_id = ?");
                    $update_savings_stmt->execute([$new_savings_balance, $data['member_id']]);
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
                $savings_stmt->execute([$data['member_id']]);
                $savings_balance = $savings_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($savings_balance) {
                    $new_savings_balance = (float)$savings_balance['savings_current_balance'] + $excess_amount;
                    $update_savings_stmt = $db->prepare("UPDATE member_accounts SET savings_current_balance = ? WHERE member_id = ?");
                    $update_savings_stmt->execute([$new_savings_balance, $data['member_id']]);
                }
            }
        } else {
            // Regular withdrawal
            $balance_after = $balance_before - $amount;
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
$update_balance_stmt->execute([$balance_after, $data['member_id']]);

// Set timezone to Nairobi
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Africa/Nairobi');
}

// Use today's date if transaction_date is not provided
$transaction_date = !empty($data['transaction_date']) ? $data['transaction_date'] : date('Y-m-d');

$reference_number = generateTransactionReferenceNumber($db);

$query = 'INSERT INTO transactions (member_id, account_type_id, destination_account_type_id, amount, transaction_type, balance_before, balance_after, description, transaction_date, reference_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
$stmt = $db->prepare($query);
$stmt->execute([
    $data['member_id'],
    $data['account_type_id'],
    $data['destination_account_type_id'] ?? null,
    $data['amount'],
    $data['transaction_type'],
    $balance_before,
    $balance_after,
    $data['description'] ?? null,
    $transaction_date,
    $reference_number
]);
$id = $db->lastInsertId();

// Fetch the created transaction with member and account type names
$fetch = $db->prepare('SELECT t.*, m.name as member_name, m.phone_number, at.name as member_acc_type_name FROM transactions t JOIN member m ON t.member_id = m.id JOIN account_type at ON t.account_type_id = at.id WHERE t.id = ?');
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

    // --- Start of new SMS logic ---
    $sms_debug_info = []; // Initialize debug array
    $account_type_id = (int)$row['account_type_id'];
    $transaction_type = $row['transaction_type'];
    $owner_phone = $row['phone_number'] ?? null;

    // Send for Loans (1), Savings (2), Insurance (6) and County (7)
    $allowed_sms_accounts = [1, 2, 6, 7];
    if ($owner_phone && in_array($account_type_id, $allowed_sms_accounts)) {
        include_once __DIR__ . '/../../../models/Sms.php';
        $sanitized_number = Sms::sanitizeNumber($owner_phone);

        if ($sanitized_number) {
            // Fetch company name from organization_details
            $org_stmt = $db->prepare('SELECT name FROM organization_details ORDER BY id ASC LIMIT 1');
            $org_stmt->execute();
            $org = $org_stmt->fetch(PDO::FETCH_ASSOC);
            $company_name = $org ? $org['name'] : 'iGuru';

            $sms_message = "";
            $member_name = $row['member_name'];
            $amount_formatted = number_format((float)$row['amount'], 2);
            $balance_after_formatted = number_format((float)$row['balance_after'], 2);

            if ($account_type_id === 1) { // Loan Account
                if ($transaction_type === 'deposit') {
                    $sms_message = "Dear $member_name, you have received a new loan of KES $amount_formatted. Your new loan balance is KES $balance_after_formatted. Thank you, $company_name.";
                } elseif ($transaction_type === 'withdrawal') { // This is a loan payment
                    $loan_balance_before = (float)$row['balance_before'];
                    $payment_amount = (float)$row['amount'];

                    if ($loan_balance_before <= 0) {
                        // Case: No outstanding loan. Entire payment goes to savings.
                        $sms_message = "Dear $member_name, your payment of KES $amount_formatted has been received. As you have no outstanding loan, the full amount has been credited to your savings. Thank you, $company_name.";
                    } elseif ($payment_amount > $loan_balance_before) {
                        // Case: Payment is more than the loan balance. Excess goes to savings.
                        $excess_amount = number_format($payment_amount - $loan_balance_before, 2);
                        $sms_message = "Dear $member_name, your loan payment of KES $amount_formatted has been received. Your loan is now fully paid. The excess of KES $excess_amount has been credited to your savings. Thank you, $company_name.";
                    } else {
                        // Case: Standard loan payment.
                         $sms_message = "Dear $member_name, your loan payment of KES $amount_formatted has been received. Your new loan balance is KES $balance_after_formatted. Thank you, $company_name.";
                    }
                }
            } elseif ($account_type_id === 2) { // Savings Account
                if ($transaction_type === 'deposit') {
                    $sms_message = "Dear $member_name, your savings deposit of KES $amount_formatted has been received. Your new savings balance is KES $balance_after_formatted. Thank you, $company_name.";
                } elseif ($transaction_type === 'withdrawal') {
                    $sms_message = "Dear $member_name, your savings withdrawal of KES $amount_formatted has been processed. Your new savings balance is KES $balance_after_formatted. Thank you, $company_name.";
            }
        } elseif ($account_type_id === 7) { // County Account
            if ($transaction_type === 'deposit') {
                $sms_message = "Dear $member_name, your County contribution of KES $amount_formatted has been received. Your new balance is KES $balance_after_formatted. Thank you, $company_name.";
            } elseif ($transaction_type === 'withdrawal') {
                $sms_message = "Dear $member_name, your County withdrawal of KES $amount_formatted has been processed. Your new balance is KES $balance_after_formatted. Thank you, $company_name.";
            }
        } elseif ($account_type_id === 6) { // Insurance Account
            if ($transaction_type === 'deposit') {
                $sms_message = "Dear $member_name, your Insurance contribution of KES $amount_formatted has been received. Your new balance is KES $balance_after_formatted. Thank you, $company_name.";
            } elseif ($transaction_type === 'withdrawal') {
                $sms_message = "Dear $member_name, your Insurance withdrawal of KES $amount_formatted has been processed. Your new balance is KES $balance_after_formatted. Thank you, $company_name.";
                }
            }

            if (!empty($sms_message)) {
                $username = "mzigosms";
                $apiKey = "91e59dfce79a61c35f3904acb2c71c27aeeef34f847f940fafb4c29674f8805c";
                $AT = new AfricasTalking($username, $apiKey);
                $sms_service = $AT->sms();
                $success = false;
                $cost = 0.0;
                try {
                    $sms_debug_info['attempt'] = "Attempting to send SMS to $sanitized_number";
                    $result = $sms_service->send([
                        'to'      => $sanitized_number,
                        'message' => $sms_message,
                        'from'    => 'iGuru'
                    ]);

                    // --- Robust Success Check (Handles Array/Object from SDK) ---
                    $result_array = json_decode(json_encode($result), true);

                    $sms_debug_info['api_response'] = $result_array;
                    $is_status_ok = isset($result_array['status']) && $result_array['status'] === 'success';
                    $is_recipient_ok = isset($result_array['data']['SMSMessageData']['Recipients'][0]['statusCode']) && $result_array['data']['SMSMessageData']['Recipients'][0]['statusCode'] == 100;

                    $sms_debug_info['check_api_status'] = [
                        'is_ok' => $is_status_ok,
                        'value' => $result_array['status'] ?? 'not_set'
                    ];
                    $sms_debug_info['check_recipient_status'] = [
                        'is_ok' => $is_recipient_ok,
                        'value' => $result_array['data']['SMSMessageData']['Recipients'][0]['statusCode'] ?? 'not_set',
                        'type' => isset($result_array['data']['SMSMessageData']['Recipients'][0]['statusCode']) ? gettype($result_array['data']['SMSMessageData']['Recipients'][0]['statusCode']) : 'not_set'
                    ];

                    if ($is_status_ok && $is_recipient_ok) {
                        $success = true;
                        $cost = (float)str_replace('KES ', '', $result_array['data']['SMSMessageData']['Recipients'][0]['cost']);
                    }

                } catch (Exception $e) {
                    $success = false;
                    $sms_debug_info['error'] = "Africa's Talking API Exception: " . $e->getMessage();
                }

                $sms_debug_info['success_flag_after_api_call'] = $success;

                // Log SMS attempt to database only if sent successfully
                if ($success) { 
                    $smsLog = new Sms($db);
                    $smsLog->sent_to = $sanitized_number;
                    $smsLog->text_message = $sms_message;
                    $smsLog->sent_date = $row['transaction_date'];
                    $smsLog->sent_time = date('H:i:s');
                    $smsLog->sent_status = 1;
                    $smsLog->sent_from = 'iGuru';
                    $smsLog->package_id = '';
                    $smsLog->af_cost = 0;
                    $smsLog->sms_characters = strlen($sms_message);
                    $smsLog->pages = ceil(strlen($sms_message) / 160);
                    $smsLog->page_cost = 0.80;
                    $smsLog->cost = 0.80; // Flat rate cost per message
                    if ($smsLog->create()) {
                        $sms_debug_info['db_log'] = "SMS logged to database successfully.";
                    } else {
                        $sms_debug_info['db_log_error'] = "Failed to log SMS to database. Reason: " . ($smsLog->db_error ?? 'Unknown');
                    }
                }
            }
        } else {
            $sms_debug_info['error'] = "Phone number '$owner_phone' could not be sanitized.";
        }
    } else {
        $sms_debug_info['skipped'] = "SMS not sent. Phone found: " . ($owner_phone ? 'Yes' : 'No') . ", Is account in allowed list: " . (in_array($account_type_id, $allowed_sms_accounts) ? 'Yes' : 'No');
    }
    // --- End of new SMS logic ---

    $final_response = ['message' => 'Transaction created successfully', 'response' => 'success', 'data' => $response];
    if (!empty($sms_debug_info)) {
        $final_response['sms_debug'] = $sms_debug_info;
    }
    echo json_encode($final_response);
} else {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to fetch created transaction', 'response' => 'error']);
} 