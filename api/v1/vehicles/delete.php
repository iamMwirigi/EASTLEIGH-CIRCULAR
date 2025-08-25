<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: DELETE');
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

if (empty($data->id)) {
    http_response_code(400);
    echo json_encode(array("message" => "Vehicle ID not provided.", "response" => "error"));
    return;
}

$query = 'DELETE FROM vehicle WHERE id = :id';

$stmt = $db->prepare($query);

// Clean data
$id = htmlspecialchars(strip_tags($data->id));

// Bind data
$stmt->bindParam(':id', $id);

if($stmt->execute()) {
    if($stmt->rowCount()) {
        http_response_code(200);
        echo json_encode(
            array('message' => 'Vehicle Deleted', 'response' => 'success')
        );
    } else {
        http_response_code(404);
        echo json_encode(
            array('message' => 'Vehicle Not Found', 'response' => 'error')
        );
    }
} else {
    http_response_code(500);
    echo json_encode(
        array('message' => 'Vehicle Not Deleted', 'response' => 'error')
    );
} 