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
$member_id = isset($data->member_id) ? $data->member_id : null;

if ($member_id) {
    // Initialize for specific member
    $check_stmt = $db->prepare('SELECT id FROM member_accounts WHERE member_id = ?');
    $check_stmt->execute([$member_id]);
    
    if ($check_stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['message' => 'Member account already exists', 'response' => 'error']);
        exit();
    }
    
    $member_stmt = $db->prepare('SELECT id FROM member WHERE id = ?');
    $member_stmt->execute([$member_id]);
    
    if (!$member_stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['message' => 'Member not found', 'response' => 'error']);
        exit();
    }
    
    $insert_stmt = $db->prepare('INSERT INTO member_accounts (member_id, savings_opening_balance, savings_current_balance, loan_opening_balance, loan_current_balance, county_opening_balance, county_current_balance, operations_opening_balance, operations_current_balance) VALUES (?, 0, 0, 0, 0, 0, 0, 0, 0)');
    $insert_stmt->execute([$member_id]);
    
    http_response_code(201);
    echo json_encode([
        'message' => 'Member account initialized successfully',
        'response' => 'success',
        'data' => ['member_id' => $member_id]
    ]);
} else {
    // Initialize for all members without accounts
    $members_stmt = $db->prepare('
        SELECT m.id 
        FROM member m 
        LEFT JOIN member_accounts ma ON m.id = ma.member_id 
        WHERE ma.member_id IS NULL
    ');
    $members_stmt->execute();
    $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count = 0;
    foreach ($members as $member) {
        $insert_stmt = $db->prepare('INSERT INTO member_accounts (member_id, savings_opening_balance, savings_current_balance, loan_opening_balance, loan_current_balance, county_opening_balance, county_current_balance, operations_opening_balance, operations_current_balance) VALUES (?, 0, 0, 0, 0, 0, 0, 0, 0)');
        $insert_stmt->execute([$member['id']]);
        $count++;
    }
    
    http_response_code(201);
    echo json_encode([
        'message' => "Initialized accounts for $count members",
        'response' => 'success',
        'data' => ['count' => $count]
    ]);
} 