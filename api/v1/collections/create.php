<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
use AfricasTalking\SDK\AfricasTalking;
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

$userData = authorize(['admin', 'user', 'member']);

$database = new Database();
$db = $database->connect();

if($db === null) {
    http_response_code(503);
    echo json_encode(['message' => 'Failed to connect to the database.', 'response' => 'error']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method Not Allowed. Use POST.', 'response' => 'error']);
    exit();
}

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

// Trim whitespace from number_plate to prevent lookup failures
if (isset($data['number_plate'])) {
    $data['number_plate'] = trim($data['number_plate']);
}

// Make amount optional by removing it from required fields
$required_fields = [
    'number_plate', 't_time', 't_date'
];

foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(['message' => "Missing required field: $field", 'response' => 'error']);
        exit();
    }
}

// Deductions: at least one must be provided and non-zero
$deductions = [
    'operations' => isset($data['operations']) ? (float)$data['operations'] : 0,
    'loans' => isset($data['loans']) ? (float)$data['loans'] : 0,
    'county' => isset($data['county']) ? (float)$data['county'] : 0,
    'savings' => isset($data['savings']) ? (float)$data['savings'] : 0,
    'insurance' => isset($data['insurance']) ? (float)$data['insurance'] : 0
];
// Prevent negative values for any deduction
foreach ($deductions as $key => $value) {
    if ($value < 0) {
        http_response_code(400);
        echo json_encode(['message' => "Deduction '$key' cannot be negative.", 'response' => 'error']);
        exit();
    }
}
if (array_sum($deductions) == 0) {
    http_response_code(400);
    echo json_encode(['message' => 'At least one deduction field must be provided and non-zero.', 'response' => 'error']);
    exit();
}

// Sequential receipt number generation: DIX-1, DIX-2, ...
$receipt_prefix = 'IG-';
$max_receipt_stmt = $db->prepare("SELECT receipt_no FROM new_transaction WHERE receipt_no LIKE '{$receipt_prefix}%'");
$max_receipt_stmt->execute();
$max_number = 0;
while ($row = $max_receipt_stmt->fetch(PDO::FETCH_ASSOC)) {
    if (preg_match('/^IG-(\\d+)$/', $row['receipt_no'], $matches)) {
        $num = (int)$matches[1];
        if ($num > $max_number) $max_number = $num;
    }
}
$next_receipt_no = $receipt_prefix . ($max_number + 1);
$receipt_no = $next_receipt_no;

// Stage name logic: allow stage_id in request to override user's stage_id
$stage_id = isset($data['stage_id']) ? $data['stage_id'] : null;
if (!$stage_id) {
    $user_id = $userData->id;
    $user_stmt = $db->prepare('SELECT stage_id FROM _user_ WHERE id = ?');
    $user_stmt->execute([$user_id]);
    $user_row = $user_stmt->fetch(PDO::FETCH_ASSOC);
    if ($user_row && $user_row['stage_id']) {
        $stage_id = $user_row['stage_id'];
    }
}
$stage_name = null;
if ($stage_id) {
    $stage_stmt = $db->prepare('SELECT name FROM stage WHERE id = ?');
    $stage_stmt->execute([$stage_id]);
    $stage_row = $stage_stmt->fetch(PDO::FETCH_ASSOC);
    if ($stage_row) $stage_name = $stage_row['name'];
}
if (!$stage_name) {
    $stage_name = "UNKNOWN"; // Default value if not found
}

$query = 'INSERT INTO new_transaction (
    number_plate, operations, loans, county, savings, insurance,
    t_time, t_date, collected_by, stage_name, amount,
    s_time, s_date, client_side_id, receipt_no, delete_status, for_date
) VALUES (
    :number_plate, :operations, :loans, :county, :savings, :insurance,
    :t_time, :t_date, :collected_by, :stage_name, :amount,
    :s_time, :s_date, :client_side_id, :receipt_no, :delete_status, :for_date
)';

$stmt = $db->prepare($query);

// Set Nairobi timezone for server-side fields
date_default_timezone_set('Africa/Nairobi');

// Prepare variables for bindParam (must be variables, not expressions)
$t_date = $data['t_date'] ?? date('Y-m-d');
$s_time = date('H:i:s');
$s_date = date('Y-m-d');
$client_side_id = isset($data['client_side_id']) && $data['client_side_id'] ? $data['client_side_id'] : uniqid('CLNT-');

// Required fields
$stmt->bindParam(':number_plate', $data['number_plate']);
$stmt->bindParam(':savings', $deductions['savings']);
$stmt->bindParam(':insurance', $deductions['insurance']);
$stmt->bindParam(':t_time', $data['t_time']);
$stmt->bindParam(':t_date', $t_date);
// Set collected_by from logged-in user
$collected_by = $userData->username;
$stmt->bindParam(':collected_by', $collected_by);
$stmt->bindParam(':stage_name', $stage_name);
// Calculate amount as the sum of all deduction fields
$amount = 0;
foreach (['operations', 'loans', 'county', 'savings', 'insurance'] as $ded_field) {
    $amount += isset($deductions[$ded_field]) ? (float)$deductions[$ded_field] : 0;
}
// When binding amount, always use the calculated value
$stmt->bindParam(':amount', $amount);
$stmt->bindValue(':s_time', $s_time);
$stmt->bindValue(':s_date', $s_date);
$stmt->bindValue(':client_side_id', $client_side_id);
$stmt->bindValue(':receipt_no', $receipt_no);
$stmt->bindValue(':delete_status', $data['delete_status'] ?? 0);
$stmt->bindValue(':for_date', $data['for_date'] ?? null);
$stmt->bindValue(':operations', $deductions['operations']);
$stmt->bindValue(':loans', $deductions['loans']);
$stmt->bindValue(':county', $deductions['county']);

if($stmt->execute()) {
    $last_id = $db->lastInsertId();
    
    // Update member_accounts balances based on collection
    if (!empty($data['number_plate'])) {
        $vehicle_stmt = $db->prepare('SELECT owner FROM vehicle WHERE number_plate = ?');
        $vehicle_stmt->execute([$data['number_plate']]);
        $vehicle_row = $vehicle_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vehicle_row && $vehicle_row['owner']) {
            $member_id = $vehicle_row['owner'];
            
            // Get current member_accounts record
            $member_accounts_stmt = $db->prepare('SELECT * FROM member_accounts WHERE member_id = ?');
            $member_accounts_stmt->execute([$member_id]);
            $member_accounts = $member_accounts_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$member_accounts) {
                // Create member_accounts record if it doesn't exist
                $create_account_stmt = $db->prepare('INSERT INTO member_accounts (member_id, savings_opening_balance, savings_current_balance, loan_opening_balance, loan_current_balance, county_opening_balance, county_current_balance, operations_opening_balance, operations_current_balance, insurance_opening_balance, insurance_current_balance) VALUES (?, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)');
                $create_account_stmt->execute([$member_id]);
                $member_accounts_stmt->execute([$member_id]);
                $member_accounts = $member_accounts_stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            if ($member_accounts) {
                // Calculate new balances
                $new_savings = (float)$member_accounts['savings_current_balance'] + (float)($deductions['savings'] ?? 0);
                $new_loans = (float)$member_accounts['loan_current_balance'] + (float)($deductions['loans'] ?? 0);
                $new_county = (float)$member_accounts['county_current_balance'] + (float)($deductions['county'] ?? 0);
                $new_insurance = (float)$member_accounts['insurance_current_balance'] + (float)($deductions['insurance'] ?? 0);
                
                // Update member_accounts
                $update_stmt = $db->prepare('UPDATE member_accounts SET savings_current_balance = ?, loan_current_balance = ?, county_current_balance = ?, insurance_current_balance = ? WHERE member_id = ?');
                $update_stmt->execute([$new_savings, $new_loans, $new_county, $new_insurance, $member_id]);
                
                // Create transaction records for each deduction type
                $account_type_map = [
                    'savings' => 2, // Savings account type ID
                    'loans' => 1,   // Loans account type ID
                    'county' => 7, // County account type ID
                    'insurance' => 6 // Insurance account type ID
                ];
                
                foreach ($deductions as $deduction_type => $amount) {
                    if ($amount > 0 && isset($account_type_map[$deduction_type])) {
                        $account_type_id = $account_type_map[$deduction_type];
                        $balance_before = 0;
                        
                        // Get balance before based on deduction type
                        switch ($deduction_type) {
                            case 'savings':
                                $balance_before = (float)$member_accounts['savings_current_balance'];
                                break;
                            case 'loans':
                                $balance_before = (float)$member_accounts['loan_current_balance'];
                                break;
                            case 'county':
                                $balance_before = (float)$member_accounts['county_current_balance'];
                                break;
                            case 'insurance':
                                $balance_before = (float)$member_accounts['insurance_current_balance'];
                                break;
                        }
                        
                        $balance_after = $balance_before + $amount;
                        
                        // Generate transaction reference number
                        $transaction_ref_number = generateTransactionReferenceNumber($db);
                        
                        // Create transaction record
                        $transaction_stmt = $db->prepare('INSERT INTO transactions (member_id, account_type_id, transaction_type, amount, balance_before, balance_after, transaction_date, description, reference_number, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                        $transaction_date = $t_date; // Use the collection's date, not the current date
                        $description = "Collection - " . ucfirst(str_replace('_', ' ', $deduction_type)) . ": +" . number_format($amount, 2);
                        
                        $transaction_stmt->execute([
                            $member_id,
                            $account_type_id,
                            'deposit', // Collections are deposits to member accounts
                            $amount,
                            $balance_before,
                            $balance_after,
                            $transaction_date,
                            $description,
                            $transaction_ref_number
                        ]);
                    }
                }
            }
        }
    }
    
    // Fetch the full collection row
    $fetch_stmt = $db->prepare('SELECT * FROM new_transaction WHERE id = ?');
    $fetch_stmt->execute([$last_id]);
    $collection = $fetch_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Replace s_time with concatenated s_date and s_time
    if (isset($collection['s_date']) && isset($collection['s_time'])) {
        $collection['s_time'] = $collection['s_date'] . ' ' . $collection['s_time'];
    }
    // Remove 'total' from the response if present
    if (isset($collection['total'])) {
        unset($collection['total']);
    }
    // Add total to the collection array
    $total = 0;
    foreach (['loans', 'county', 'savings', 'insurance'] as $ded_field) {
        $total += isset($deductions[$ded_field]) ? (float)$deductions[$ded_field] : 0;
    }
    $collection['total'] = $total;
    // Remove deduction fields with a value of zero from the response
    foreach (['operations', 'loans', 'county', 'savings', 'insurance'] as $ded_field) {
        if (isset($collection[$ded_field]) && (float)$collection[$ded_field] == 0) {
            unset($collection[$ded_field]);
        }
    }
    // Fetch company name from organization_details
    $org_stmt = $db->prepare('SELECT name FROM organization_details ORDER BY id ASC LIMIT 1');
    $org_stmt->execute();
    $org = $org_stmt->fetch(PDO::FETCH_ASSOC);
    $company_name = $org ? $org['name'] : 'iGuru';
    // Fetch attendant's phone number
    $user_stmt = $db->prepare('SELECT phone_number FROM _user_ WHERE id = ?');
    $user_stmt->execute([$userData->id]);
    $user_row = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $attendant_phone = $user_row && $user_row['phone_number'] ? $user_row['phone_number'] : null;
    // Only keep the block that sends SMS to the vehicle owner (member)
    $owner_phone = null;
    if (!empty($collection['number_plate'])) {
        $vehicle_stmt = $db->prepare('SELECT owner FROM vehicle WHERE number_plate = ?');
        $vehicle_stmt->execute([$collection['number_plate']]);
        $vehicle_row = $vehicle_stmt->fetch(PDO::FETCH_ASSOC);
        if ($vehicle_row && $vehicle_row['owner']) {
            $member_stmt = $db->prepare('SELECT phone_number FROM member WHERE id = ?');
            $member_stmt->execute([$vehicle_row['owner']]);
            $member_row = $member_stmt->fetch(PDO::FETCH_ASSOC);
            if ($member_row && $member_row['phone_number']) {
                $owner_phone = $member_row['phone_number'];
            }
        }
    }
    // Only send SMS if at least one of loans, tickets/seasonal_tickets, savings, or insurance is greater than zero
    $send_sms = false;
    $sms_deduction_fields = [
        'loans',
        'county', // will be set below if present
        'savings',
        'insurance'
    ];
    // Check for county
    $county_val = isset($collection['county']) ? (float)$collection['county'] : 0;
    if ((float)($collection['loans'] ?? 0) > 0 || $county_val > 0 || (float)($collection['savings'] ?? 0) > 0 || (float)($collection['insurance'] ?? 0) > 0) {
        $send_sms = true;
    }
    if ($owner_phone && $send_sms) {
        $sms_debug_info = []; // Initialize debug array
        include_once __DIR__ . '/../../../models/Sms.php';
        $sanitized_number = Sms::sanitizeNumber($owner_phone);
        if ($sanitized_number) {
            // Build a formatted deductions string for SMS (ignore operations)
            $deductions_list = [];
            foreach ([
                'loans' => 'Loans',
                'county' => 'County',
                'savings' => 'Savings',
                'insurance' => 'Insurance'
            ] as $ded_field => $ded_label) {
                $val = isset($collection[$ded_field]) ? (float)$collection[$ded_field] : 0;
                if ($val > 0) {
                    $deductions_list[] = "$ded_label: Ksh $val";
                }
            }
            $deductions_str = empty($deductions_list) ? '' : (implode("\n", $deductions_list) . "\n");
            $sms_message =
                "{$collection['number_plate']}\n" .
                $deductions_str .
                "Total: Ksh {$collection['total']}\n" .
                "\n\n" . // Two blank lines
                "Thank you\n" .
                "$company_name";
            $now = date('d-m-Y H:i');
            $sms_message .= " [$now]";
            // Use Africa's Talking SDK
            $username = "mzigosms";
            $apiKey = "91e59dfce79a61c35f3904acb2c71c27aeeef34f847f940fafb4c29674f8805c";
            $AT = new AfricasTalking($username, $apiKey);
            $sms = $AT->sms();
            $success = false;
            $cost = 0.0;
            try {
                $sms_debug_info['attempt'] = "Attempting to send SMS to $sanitized_number";
                $result = $sms->send([
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
                // Log error if needed
                $success = false;
                $sms_debug_info['error'] = "Africa's Talking API Exception: " . $e->getMessage();
            }
            $sms_debug_info['success_flag_after_api_call'] = $success;
            // Log SMS attempt to database only if sent successfully
            if ($success) {
                include_once __DIR__ . '/../../../models/Sms.php'; // Ensure Sms class is included
                $smsLog = new Sms($db);
                $smsLog->sent_to = $sanitized_number;
                $smsLog->text_message = $sms_message;
                $smsLog->sent_date = $collection['t_date'];
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
        $sms_debug_info['skipped'] = "SMS not sent. Phone found: " . ($owner_phone ? 'Yes' : 'No') . ", Send condition met: " . ($send_sms ? 'Yes' : 'No');
    }

    // Add company_name and contacts inside the collection array
    $collection['company_name'] = $company_name;
    // Fetch contacts from organization_details
    $org_stmt = $db->prepare('SELECT contacts FROM organization_details ORDER BY id ASC LIMIT 1');
    $org_stmt->execute();
    $org = $org_stmt->fetch(PDO::FETCH_ASSOC);
    $company_contacts = $org ? $org['contacts'] : null;
    $collection['company_contacts'] = $company_contacts;

    $final_response = [
        'message' => 'Collection created successfully',
        'response' => 'success',
        'collection' => $collection
    ];
    if (!empty($sms_debug_info)) {
        $final_response['sms_debug'] = $sms_debug_info;
    }
    http_response_code(201);
    echo json_encode($final_response);
} else {
    http_response_code(500);
    echo json_encode([
        'message' => 'Failed to create collection',
        'response' => 'error'
    ]);
} 