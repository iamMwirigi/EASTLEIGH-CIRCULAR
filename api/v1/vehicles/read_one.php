<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

$userData = authorize(['admin', 'user']);

$database = new Database();
$db = $database->connect();

if($db === null) {
    http_response_code(503);
    echo json_encode(['message' => 'Failed to connect to the database.', 'response' => 'error']);
    exit();
}

$id = isset($_GET['id']) ? $_GET['id'] : die(json_encode(['message' => 'Vehicle ID not provided.']));

$query = 'SELECT v.id, v.number_plate, v.owner, m.name as owner_name
          FROM vehicle v
          LEFT JOIN member m ON v.owner = m.id
          WHERE v.id = :id';

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();

$num = $stmt->rowCount();

if($num > 0) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    extract($row);
    $vehicle_item = array(
        'id' => $id,
        'number_plate' => $number_plate,
        'owner_id' => $owner,
        'owner_name' => $owner_name
    );
    
    echo json_encode([
        'message' => 'Vehicle found',
        'response' => 'success',
        'data' => $vehicle_item
    ]);

} else {
    http_response_code(404);
    echo json_encode(
        array('message' => 'Vehicle not found.', 'response' => 'error')
    );
} 