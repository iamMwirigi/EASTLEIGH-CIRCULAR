<?php
// required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../../../models/User.php';

$database = new Database();
$db = $database->connect();

$user = new User($db);

$data = json_decode(file_get_contents("php://input"));

if(
    !empty($data->id) &&
    !empty($data->username) &&
    !empty($data->name)
){
    $user->id = $data->id;
    $user->username = $data->username;
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
    $user->password = isset($data->password) ? $data->password : null;

    if($user->update()){
        http_response_code(200);
        echo json_encode(array("message" => "User was updated."));
    }else{
        http_response_code(503);
        echo json_encode(array("message" => "Unable to update user."));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Unable to update user. Data is incomplete. `id`, `username`, and `name` are required."));
} 