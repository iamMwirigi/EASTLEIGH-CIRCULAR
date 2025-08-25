<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';

$database = new Database();
$db = $database->connect();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["message" => "Failed to connect to the database.", "response" => "error"]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['id'])) {
    http_response_code(400);
    echo json_encode(["message" => "'id' is required.", "response" => "error"]);
    exit();
}
$fetch = $db->prepare('SELECT id, name, description FROM account_type WHERE id = ?');
$fetch->execute([$data['id']]);
$deleted = $fetch->fetch(PDO::FETCH_ASSOC);
$stmt = $db->prepare('DELETE FROM account_type WHERE id = ?');
$stmt->execute([$data['id']]);
echo json_encode([
    "message" => "Account type deleted successfully.",
    "response" => "success",
    "data" => $deleted
]); 