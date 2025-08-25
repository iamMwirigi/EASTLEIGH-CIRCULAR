<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../../../models/Member.php';

$database = new Database();
$db = $database->connect();

$data = json_decode(file_get_contents("php://input"));

if (empty($data->token) || empty($data->new_password)) {
    http_response_code(400);
    echo json_encode(["message" => "Unable to reset password. `token` and `new_password` are required."]);
    exit();
}

$jwt = $data->token;
$secret_key = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? 'your_secret_key';

try {
    $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
    $user_data = $decoded->data ?? null;
    if (!$user_data || !isset($user_data->username) || !isset($user_data->role) || $user_data->role !== 'member') {
        throw new Exception('Invalid token data.');
    }
    $phone_number = $user_data->username;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["message" => "Invalid or expired token."]);
    exit();
}

$member = new Member($db);
$member->phone_number = $phone_number;
$member->password = $data->new_password;

// Allow update for any member with a valid token
$query = "UPDATE member SET entry_code = :entry_code WHERE phone_number = :phone_number";
$stmt = $db->prepare($query);
$stmt->bindParam(':entry_code', $member->password);
$stmt->bindParam(':phone_number', $member->phone_number);

if ($stmt->execute() && $stmt->rowCount() > 0) {
    http_response_code(200);
    echo json_encode(["message" => "Password was reset."]);
} else {
    http_response_code(400);
    echo json_encode(["message" => "Unable to reset password."]);
} 