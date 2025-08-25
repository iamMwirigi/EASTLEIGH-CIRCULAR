<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';

$database = new Database();
$db = $database->connect();

if ($db === null) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Failed to connect to the database.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed. Use POST.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['player_name'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'player_name is required.']);
    exit();
}
$player_name = trim($data['player_name']);
$bike_id = null;
if (isset($data['bike']) && trim($data['bike']) !== '') {
$bike_name = trim($data['bike']);
    // 2. Find bike id (no hardcoded check)
    $bike_stmt = $db->prepare('SELECT id FROM bikes WHERE name = ?');
    $bike_stmt->execute([$bike_name]);
    $bike = $bike_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bike) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Bike not found.']);
        exit();
    }
    $bike_id = $bike['id'];
}
// 1. Create player if not exists
$stmt = $db->prepare('SELECT id FROM players WHERE name = ?');
$stmt->execute([$player_name]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);
if ($player) {
    $player_id = $player['id'];
} else {
    $insert_player = $db->prepare('INSERT INTO players (name) VALUES (?)');
    $insert_player->execute([$player_name]);
    $player_id = $db->lastInsertId();
}
// 3. Create game session with time_played=0
$session_stmt = $db->prepare('INSERT INTO game_sessions (player_id, bike_id, time_played) VALUES (?, ?, 0)');
$session_stmt->execute([$player_id, $bike_id]);
$session_id = $db->lastInsertId();
http_response_code(201);
echo json_encode([
    'status' => 'success',
    'message' => 'Player registered and game session started.',
    'session_id' => (int)$session_id,
    'player_id' => (int)$player_id,
    'bike_id' => $bike_id !== null ? (int)$bike_id : null
]); 