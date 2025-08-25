<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
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
if (empty($data['name'])) {
    http_response_code(400);
    echo json_encode(["message" => "'name' is required.", "response" => "error"]);
    exit();
}
$stmt = $db->prepare('INSERT INTO account_type (name, description) VALUES (?, ?)');
$stmt->execute([$data['name'], $data['description'] ?? null]);
$id = $db->lastInsertId();
$fetch = $db->prepare('SELECT id, name, description FROM account_type WHERE id = ?');
$fetch->execute([$id]);
$created = $fetch->fetch(PDO::FETCH_ASSOC);
echo json_encode([
    "message" => "Account type created successfully.",
    "response" => "success",
    "data" => $created
]); 