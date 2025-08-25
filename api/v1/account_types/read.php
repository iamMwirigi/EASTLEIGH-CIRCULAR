<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';

$database = new Database();
$db = $database->connect();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["message" => "Failed to connect to the database.", "response" => "error"]);
    exit();
}

$stmt = $db->prepare('SELECT id, name, description FROM account_type');
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode([
    "message" => "Account types retrieved successfully.",
    "response" => "success",
    "data" => $results
]); 