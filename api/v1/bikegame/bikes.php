<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';

$database = new Database();
$db = $database->connect();

if ($db === null) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Failed to connect to the database.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // List all bikes
    $stmt = $db->prepare('SELECT id, name FROM bikes ORDER BY name ASC');
    $stmt->execute();
    $bikes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'bikes' => $bikes]);
    exit();
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['name']) || trim($data['name']) === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Bike name is required.']);
        exit();
    }
    $name = trim($data['name']);
    // Add new bike
    $stmt = $db->prepare('INSERT INTO bikes (name) VALUES (?)');
    try {
        $stmt->execute([$name]);
        echo json_encode(['status' => 'success', 'message' => 'Bike added successfully.']);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Bike name must be unique.']);
    }
    exit();
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed.']); 