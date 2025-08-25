<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

$userData = authorize(['admin']);

$database = new Database();
$db = $database->connect();

if($db === null) {
    http_response_code(503);
    echo json_encode(['message' => 'Failed to connect to the database.', 'response' => 'error']);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->number_plate) || empty($data->owner)) {
    http_response_code(400);
    echo json_encode(array("message" => "Incomplete data for vehicle.", "response" => "error"));
    return;
}

// Create vehicle
$query = 'INSERT INTO vehicle (number_plate, owner) VALUES (:number_plate, :owner)';

// Prepare statement
$stmt = $db->prepare($query);

// Clean data
$data->number_plate = htmlspecialchars(strip_tags($data->number_plate));
$data->owner = htmlspecialchars(strip_tags($data->owner));

// Bind data
$stmt->bindParam(':number_plate', $data->number_plate);
$stmt->bindParam(':owner', $data->owner);

// Execute query
if($stmt->execute()) {
    http_response_code(201);
    echo json_encode(
        array('message' => 'Vehicle Created', 'response' => 'success')
    );
} else {
    http_response_code(500);
    echo json_encode(
        array('message' => 'Vehicle Not Created. It might already exist.', 'response' => 'error')
    );
} 