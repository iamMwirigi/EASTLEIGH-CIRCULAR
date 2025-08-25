<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';

$database = new Database();
$db = $database->connect();

$data = json_decode(file_get_contents('php://input'));

if (!empty($data->name) && !empty($data->prefix)) {
    if (!empty($data->id)) {
        $stmt = $db->prepare('INSERT INTO stage (id, name, prefix, quota_start, quota_end, current_quota) VALUES (:id, :name, :prefix, 0, 0, 0)');
        $stmt->bindParam(':id', $data->id);
    } else {
        $stmt = $db->prepare('INSERT INTO stage (name, prefix, quota_start, quota_end, current_quota) VALUES (:name, :prefix, 0, 0, 0)');
    }
    $stmt->bindParam(':name', $data->name);
    $stmt->bindParam(':prefix', $data->prefix);
    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(['id' => $db->lastInsertId(), 'name' => $data->name, 'prefix' => $data->prefix]);
    } else {
        http_response_code(503);
        echo json_encode(['message' => 'Unable to create stage.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['message' => 'Stage name and prefix are required.']);
} 