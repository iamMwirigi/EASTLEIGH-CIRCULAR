<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../../../models/Sms.php';
include_once __DIR__ . '/../auth/authorize.php';

// Authorize admin or user
$userData = authorize(['admin', 'user']);

// Instantiate DB & connect
$database = new Database();
$db = $database->connect();

// Instantiate sms object
$sms = new Sms($db);

// Get filter parameters from GET or POST request body
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$start_date = $input['dateFrom'] ?? $_GET['dateFrom'] ?? null;
$end_date = $input['dateTo'] ?? $_GET['dateTo'] ?? null;

// Pass parameters to the read method
$result = $sms->read($start_date, $end_date);
$num = $result->rowCount();

if ($num > 0) {
    $sms_arr = [];
    $sms_arr['data'] = [];

    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        // Sanitize and format data for the response
        $sms_item = [
            'id' => (int)$row['id'],
            'sent_from' => $row['sent_from'],
            'sent_to' => $row['sent_to'],
            'package_id' => $row['package_id'],
            'text_message' => $row['text_message'],
            'af_cost' => (float)$row['af_cost'],
            'sent_time' => $row['sent_time'],
            'sent_date' => $row['sent_date'],
            'sms_characters' => (int)$row['sms_characters'],
            'sent_status' => (int)$row['sent_status'],
            'pages' => (int)$row['pages'],
            'page_cost' => (float)$row['page_cost'],
            'cost' => (float)$row['cost']
        ];
        array_push($sms_arr['data'], $sms_item);
    }
    
    $sms_arr['message'] = 'Messages retrieved successfully';
    $sms_arr['response'] = 'success';

    echo json_encode($sms_arr);
} else {
    echo json_encode([
        'message' => 'No Messages Found',
        'response' => 'success',
        'data' => []
    ]);
}