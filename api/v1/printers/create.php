<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';

$database = new Database();
$db = $database->connect();

$data = json_decode(file_get_contents('php://input'));

if (!empty($data->name)) {
    $stmt = $db->prepare('INSERT INTO printers (name) VALUES (:name)');
    $stmt->bindParam(':name', $data->name);
    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(['id' => $db->lastInsertId(), 'name' => $data->name]);
    } else {
        http_response_code(503);
        echo json_encode(['message' => 'Unable to create printer.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['message' => 'Printer name is required.']);
} 