<?php
// required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../../../models/User.php';

$database = new Database();
$db = $database->connect();

$user = new User($db);

$data = json_decode(file_get_contents("php://input"));

if (
    !empty($data->username) &&
    !empty($data->password) &&
    !empty($data->name)
) {
    $user->username = $data->username;
    $user->password = $data->password;
    $user->name = $data->name;
    $user->stage = isset($data->stage) ? $data->stage : '';
    $user->user_town = isset($data->user_town) ? $data->user_town : 0;
    $user->quota_start = isset($data->quota_start) ? $data->quota_start : 0;
    $user->quota_end = isset($data->quota_end) ? $data->quota_end : 0;
    $user->current_quota = isset($data->current_quota) ? $data->current_quota : 0;
    $user->prefix = isset($data->prefix) ? $data->prefix : 'DXH-';
    $user->printer_name = isset($data->printer_name) ? $data->printer_name : 'InnerPrinter';
    $user->stage_id = isset($data->stage_id) ? $data->stage_id : null;
    $user->phone_number = isset($data->phone_number) ? $data->phone_number : null;
    $user->printer_id = isset($data->printer_id) ? $data->printer_id : null;

    if ($user->create()) {
        // Fetch stage_name and printer_name
        $stage_name = null;
        $printer_name = null;
        if ($user->stage_id) {
            $stage_stmt = $db->prepare('SELECT name FROM stage WHERE id = ? LIMIT 1');
            $stage_stmt->execute([$user->stage_id]);
            $stage_row = $stage_stmt->fetch(PDO::FETCH_ASSOC);
            if ($stage_row) $stage_name = $stage_row['name'];
        }
        if ($user->printer_id) {
            $printer_stmt = $db->prepare('SELECT name FROM printers WHERE id = ? LIMIT 1');
            $printer_stmt->execute([$user->printer_id]);
            $printer_row = $printer_stmt->fetch(PDO::FETCH_ASSOC);
            if ($printer_row) $printer_name = $printer_row['name'];
        }
        http_response_code(201);
        echo json_encode([
            "id" => $user->id,
            "username" => $user->username,
            "password" => $user->password,
            "name" => $user->name,
            "stage_id" => $user->stage_id,
            "stage_name" => $stage_name,
            "phone_number" => $user->phone_number,
            "prefix" => $user->prefix,
            "printer_id" => $user->printer_id,
            "printer_name" => $printer_name
        ]);
    } else {
        http_response_code(503);
        echo json_encode(array("message" => "Unable to create user."));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Unable to create user. Data is incomplete. `username`, `password`, and `name` are required."));
} 