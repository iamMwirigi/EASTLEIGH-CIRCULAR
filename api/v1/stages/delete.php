<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';

$database = new Database();
$db = $database->connect();

$data = json_decode(file_get_contents('php://input'));

if (!empty($data->id)) {
    $stmt = $db->prepare('DELETE FROM stage WHERE id = :id');
    $stmt->bindParam(':id', $data->id);
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(['message' => 'Stage deleted.']);
    } else {
        http_response_code(503);
        echo json_encode(['message' => 'Unable to delete stage.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['message' => 'Stage id is required.']);
} 