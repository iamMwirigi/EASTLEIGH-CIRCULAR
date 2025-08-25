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

$id = isset($_GET['id']) ? $_GET['id'] : null;
if ($id === null) {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = isset($data['id']) ? $data['id'] : null;
}
if (!$id) {
    echo json_encode(['message' => 'Transaction ID not provided.']);
    exit();
}

$query = 'SELECT * FROM new_transaction WHERE id = ?';

$stmt = $db->prepare($query);
$stmt->bindParam(1, $id);
$stmt->execute();

$num = $stmt->rowCount();

if($num > 0) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userData->role === 'user' && $row['collected_by'] !== $userData->username) {
        http_response_code(403);
        echo json_encode(array("message" => "Forbidden. You don't have permission to access this resource.", "response" => "error"));
        exit();
    }

    // Safely initialize variables to avoid undefined warnings
    $sacco_fee = isset($row['sacco_fee']) ? $row['sacco_fee'] : null;
    $investment = isset($row['investment']) ? $row['investment'] : null;
    $tyres = isset($row['tyres']) ? $row['tyres'] : null;
    $welfare = isset($row['welfare']) ? $row['welfare'] : null;
    $number_plate = isset($row['number_plate']) ? $row['number_plate'] : null;
    $savings = isset($row['savings']) ? $row['savings'] : null;
    $insurance = isset($row['insurance']) ? $row['insurance'] : null;
    $t_time = isset($row['t_time']) ? $row['t_time'] : null;
    $t_date = isset($row['t_date']) ? $row['t_date'] : null;
    $collected_by = isset($row['collected_by']) ? $row['collected_by'] : null;
    $stage_name = isset($row['stage_name']) ? $row['stage_name'] : null;
    $amount = isset($row['amount']) ? $row['amount'] : null;

    $transaction_item = array(
        'id' => $id,
        'number_plate' => $number_plate,
        'savings' => $savings,
        'insurance' => $insurance,
        't_time' => $t_time,
        't_date' => $t_date,
        'collected_by' => $collected_by,
        'stage_name' => $stage_name,
        'amount' => $amount,
        'operations' => isset($row['operations']) ? $row['operations'] : null,
        'loans' => isset($row['loans']) ? $row['loans'] : null,
        'county' => isset($row['county']) ? $row['county'] : null
    );
    
    echo json_encode([
        'message' => 'Collection found',
        'response' => 'success',
        'data' => $transaction_item
    ]);

} else {
    http_response_code(404);
    echo json_encode(
        array('message' => 'Collection not found.', 'response' => 'error')
    );
} 