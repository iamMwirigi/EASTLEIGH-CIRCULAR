<?php
// required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../../../models/User.php';

$database = new Database();
$db = $database->connect();

$user = new User($db);

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->id)){
    $user->id = $data->id;

    if($user->delete()){
        http_response_code(200);
        echo json_encode(array("message" => "User was deleted."));
    }else{
        http_response_code(503);
        echo json_encode(array("message" => "Unable to delete user."));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Unable to delete user. `id` is required."));
} 