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
if (!isset($data['session_id']) || !isset($data['time_played'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'session_id and time_played are required.']);
    exit();
}
$session_id = (int)$data['session_id'];
$time_played = (int)$data['time_played'];
$stmt = $db->prepare('UPDATE game_sessions SET time_played = ? WHERE id = ?');
$stmt->execute([$time_played, $session_id]);
if ($stmt->rowCount() > 0) {
    echo json_encode(['status' => 'success', 'message' => 'Game session updated with time played.']);
} else {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Session not found or not updated.']);
} 