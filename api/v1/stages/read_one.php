<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../../../models/Stage.php';

// Instantiate DB & connect
$database = new Database();
$db = $database->connect();

// Instantiate stage object
$stage = new Stage($db);

// Get ID
$stage->id = isset($_GET['id']) ? $_GET['id'] : die();

// Get stage
$stage->read_one();

// Create array
$stage_arr = array(
    'id' => $stage->id,
    'name' => $stage->name,
    'prefix' => $stage->prefix,
    'quota_start' => $stage->quota_start,
    'quota_end' => $stage->quota_end,
    'current_quota' => $stage->current_quota
);

// Make JSON
echo json_encode($stage_arr); 