<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

$userData = authorize(['admin', 'user', 'member']);

$database = new Database();
$db = $database->connect();

if($db === null) {
    http_response_code(503);
    echo json_encode(['message' => 'Failed to connect to the database.', 'response' => 'error']);
    exit();
}

// Default: all vehicles
$query = 'SELECT v.id, v.number_plate, v.owner, m.name as owner_name
          FROM vehicle v
          LEFT JOIN member m ON v.owner = m.id';
$params = array();

// If owner_id is provided in POST input, filter by owner_id
$input = json_decode(file_get_contents('php://input'), true);
if (isset($input['owner_id'])) {
    $query .= ' WHERE v.owner = :owner_id';
    $params[':owner_id'] = $input['owner_id'];
} else if ($userData->role === 'member') {
    $query .= ' WHERE v.owner = :owner_id';
    $params[':owner_id'] = $userData->id;
}

$query .= ' ORDER BY v.number_plate ASC';

$stmt = $db->prepare($query);
$stmt->execute($params);

$num = $stmt->rowCount();

if($num > 0) {
    $vehicles_arr = array();
    $vehicles_arr['data'] = array();

    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        $vehicle_item = array(
            'id' => $id,
            'number_plate' => $number_plate,
            'owner_id' => $owner,
            'owner_name' => $owner_name
        );

        array_push($vehicles_arr['data'], $vehicle_item);
    }
    
    $vehicles_arr['message'] = 'Vehicles retrieved successfully';
    $vehicles_arr['response'] = 'success';

    echo json_encode($vehicles_arr);
} else {
    echo json_encode(
        array('message' => 'No Vehicles Found', 'response' => 'success', 'data' => [])
    );
} 