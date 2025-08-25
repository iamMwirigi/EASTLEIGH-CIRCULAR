<?php
// required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../../../models/User.php';

$database = new Database();
$db = $database->connect();

$user = new User($db);

$user->id = isset($_GET['id']) ? $_GET['id'] : die();

if($user->readOne()){
    $user_arr = array(
        "id" =>  $user->id,
        "username" => $user->username,
        "name" => $user->name,
        "stage" => $user->stage,
        "user_town" => $user->user_town,
        "quota_start" => $user->quota_start,
        "quota_end" => $user->quota_end,
        "current_quota" => $user->current_quota,
        "delete_status" => $user->delete_status,
        "prefix" => $user->prefix,
        "printer_name" => $user->printer_name,
    );
    http_response_code(200);
    echo json_encode($user_arr);
} else {
    http_response_code(404);
    echo json_encode(array("message" => "User does not exist."));
} 