<?php 
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include_once __DIR__ . '/../../../config/Database.php';

$database = new Database();
$db = $database->connect();

$stmt = $db->prepare('SELECT id, name, prefix FROM stage ORDER BY id ASC');
$stmt->execute();
$stages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(["stages" => $stages]); 