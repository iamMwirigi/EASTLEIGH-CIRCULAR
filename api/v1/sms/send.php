<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../../../models/Sms.php';
include_once __DIR__ . '/../auth/authorize.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
use AfricasTalking\SDK\AfricasTalking;

$userData = authorize(['admin']);

// Instantiate DB & connect
$database = new Database();
$db = $database->connect();

// Instantiate sms object
$sms = new Sms($db);

$data = json_decode(file_get_contents("php://input"));

if (empty($data->member_ids) || empty($data->message)) {
    http_response_code(400);
    echo json_encode(array('message' => 'Missing member_ids or message field.', 'response' => 'error'));
    return;
}

$results = [];

foreach ($data->member_ids as $member_id) {
    $query = 'SELECT phone_number FROM member WHERE id = :id';
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $member_id);
    $stmt->execute();
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($member && !empty($member['phone_number'])) {
        $sanitized_number = Sms::sanitizeNumber($member['phone_number']);
        if ($sanitized_number) {
            $username = "mzigosms";
            $apiKey = "91e59dfce79a61c35f3904acb2c71c27aeeef34f847f940fafb4c29674f8805c";
            $AT = new AfricasTalking($username, $apiKey);
            $smsService = $AT->sms();
            $success = false;
            
            // Set timezone to Nairobi for accurate timestamps
            date_default_timezone_set('Africa/Nairobi');
            try {
                $now = date('d-m-Y H:i');
                $message_with_time = $data->message . " [$now]";
                $result = $smsService->send([
                    'to'      => $sanitized_number,
                    'message' => $message_with_time,
                    'from'    => 'iGuru'
                ]);
                // --- Robust Success Check (Handles Array/Object from SDK) ---
                $result_array = json_decode(json_encode($result), true);
                $is_status_ok = isset($result_array['status']) && $result_array['status'] === 'success';
                $is_recipient_ok = isset($result_array['data']['SMSMessageData']['Recipients'][0]['statusCode']) && in_array($result_array['data']['SMSMessageData']['Recipients'][0]['statusCode'], [100, 101, 102]);

                if ($is_status_ok && $is_recipient_ok) {
                    $success = true;
                }
            } catch (Exception $e) {
                $success = false;
            }
            if ($success) {
                $sms->sent_to = $sanitized_number;
                $sms->text_message = $message_with_time;
                $sms->sent_date = date('Y-m-d');
                $sms->sent_time = date('H:i:s');
                $sms->sent_status = 1;
                $sms->sent_from = 'iGuru';
                $sms->package_id = '';
                $sms->af_cost = 0;
                // Calculate characters and pages based on the full message sent, including the timestamp
                $sms->sms_characters = strlen($message_with_time);
                $sms->pages = ceil($sms->sms_characters / 160); 
                $sms->page_cost = 0.80;
                $sms->cost = 0.80; // Flat rate cost per message
                $sms->create();
                $results[] = [
                    'member_id' => $member_id,
                    'status' => 'success',
                    'api_response' => $result
                ];
            } else {
                $results[] = [
                    'member_id' => $member_id,
                    'status' => 'api_failed',
                    'api_response' => $result ?? null
                ];
            }
        } else {
            $results[] = ['member_id' => $member_id, 'status' => 'invalid_phone_format'];
        }
    } else {
        $results[] = ['member_id' => $member_id, 'status' => 'member_not_found_or_no_phone'];
    }
}

echo json_encode([
    'message' => 'Bulk SMS process completed.',
    'response' => 'success',
    'data' => $results
]); 