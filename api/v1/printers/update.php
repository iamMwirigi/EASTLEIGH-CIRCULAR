<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';

$database = new Database();
$db = $database->connect();

$data = json_decode(file_get_contents('php://input'));

if (!empty($data->id) && !empty($data->name)) {
    $stmt = $db->prepare('UPDATE printers SET name = :name WHERE id = :id');
    $stmt->bindParam(':id', $data->id);
    $stmt->bindParam(':name', $data->name);
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(['message' => 'Printer updated.']);
    } else {
        http_response_code(503);
        echo json_encode(['message' => 'Unable to update printer.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['message' => 'Printer id and name are required.']);
} 