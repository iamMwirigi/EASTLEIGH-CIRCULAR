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
    // List all game sessions with player and bike names, exclude sessions where time_played is 0
    $stmt = $db->prepare('SELECT gs.id, gs.time_played, gs.created_at, p.name as player_name, b.name as bike_name FROM game_sessions gs JOIN players p ON gs.player_id = p.id LEFT JOIN bikes b ON gs.bike_id = b.id WHERE gs.time_played > 0 ORDER BY gs.time_played ASC');
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'sessions' => $sessions]);
    exit();
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['player_id']) || !isset($data['bike_id']) || !isset($data['time_played'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'player_id, bike_id, and time_played are required.']);
        exit();
    }
    $player_id = (int)$data['player_id'];
    $bike_id = (int)$data['bike_id'];
    $time_played = (int)$data['time_played'];
    $stmt = $db->prepare('INSERT INTO game_sessions (player_id, bike_id, time_played) VALUES (?, ?, ?)');
    try {
        $stmt->execute([$player_id, $bike_id, $time_played]);
        echo json_encode(['status' => 'success', 'message' => 'Game session recorded successfully.']);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid player or bike ID.']);
    }
    exit();
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed.']);
