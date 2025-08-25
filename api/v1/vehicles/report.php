<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method Not Allowed. Use POST.', 'response' => 'error']);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

// Validate required fields
if (!isset($data->vehicle_ids) || !isset($data->query) || !isset($data->start_date) || !isset($data->end_date)) {
    http_response_code(400);
    echo json_encode(['message' => 'Missing required fields: vehicle_ids, query, start_date, end_date', 'response' => 'error']);
    exit();
}

// Validate vehicle_ids is an array
if (!is_array($data->vehicle_ids) || empty($data->vehicle_ids)) {
    http_response_code(400);
    echo json_encode(['message' => 'vehicle_ids must be a non-empty array', 'response' => 'error']);
    exit();
}

// Validate query is an array
if (!is_array($data->query) || empty($data->query)) {
    http_response_code(400);
    echo json_encode(['message' => 'query must be a non-empty array', 'response' => 'error']);
    exit();
}

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data->start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data->end_date)) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid date format. Use YYYY-MM-DD', 'response' => 'error']);
    exit();
}

// Available deduction categories
$available_categories = ['operations', 'loans', 'seasonal_tickets', 'savings', 'insurance'];

// Determine which categories to include
$categories_to_include = [];
if (in_array('all', $data->query)) {
    $categories_to_include = $available_categories;
} else {
    foreach ($data->query as $category) {
        if (in_array($category, $available_categories)) {
            $categories_to_include[] = $category;
        }
    }
}

if (empty($categories_to_include)) {
    http_response_code(400);
    echo json_encode(['message' => 'No valid deduction categories specified', 'response' => 'error']);
    exit();
}

try {
    // Get vehicle information and validate ownership
    $vehicle_placeholders = implode(',', array_fill(0, count($data->vehicle_ids), '?'));
    $vehicle_query = 'SELECT v.id, v.number_plate FROM vehicle v';
    
    // If member, filter by ownership
    if ($userData->role === 'member') {
        $vehicle_query .= ' WHERE v.owner = ? AND v.id IN (' . $vehicle_placeholders . ')';
        $vehicle_params = array_merge([$userData->id], $data->vehicle_ids);
    } else {
        $vehicle_query .= ' WHERE v.id IN (' . $vehicle_placeholders . ')';
        $vehicle_params = $data->vehicle_ids;
    }
    
    $vehicle_stmt = $db->prepare($vehicle_query);
    $vehicle_stmt->execute($vehicle_params);
    $vehicles = $vehicle_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($vehicles)) {
        http_response_code(200);
        echo json_encode([
            'message' => 'No vehicles found or access denied',
            'response' => 'success',
            'data' => [
                'collection' => [],
                'totals' => []
            ]
        ]);
        exit();
    }
    
    $vehicle_ids = array_column($vehicles, 'id');
    $vehicle_map = array_column($vehicles, 'number_plate', 'id');
    
    // Build the main query
    $select_fields = ['v.id as vehicle_id', 'v.number_plate'];
    foreach ($categories_to_include as $cat) {
        $select_fields[] = 'SUM(t.' . $cat . ') as ' . $cat;
    }
    $select_fields[] = 'SUM(t.amount) as total_collection';
    $main_query = 'SELECT ' . implode(', ', $select_fields) . ' FROM new_transaction t JOIN vehicle v ON t.number_plate = v.number_plate WHERE v.id IN (' . implode(',', array_fill(0, count($vehicle_ids), '?')) . ') AND t.t_date >= ? AND t.t_date <= ? GROUP BY v.id, v.number_plate';
    $main_params = array_merge($vehicle_ids, [$data->start_date, $data->end_date]);
    $main_stmt = $db->prepare($main_query);
    $main_stmt->execute($main_params);
    $collections = $main_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = [];
    foreach ($collections as $row) {
        $vehicle_id = $row['vehicle_id'];
        $summary = [];
        foreach ($categories_to_include as $cat) {
            $summary[$cat] = isset($row[$cat]) ? (float)$row[$cat] : 0;
        }
        $summary['total_collection'] = isset($row['total_collection']) ? (float)$row['total_collection'] : 0;
        $result[] = [
            'id' => $vehicle_id,
            'number_plate' => $vehicle_map[$vehicle_id],
            'summary' => $summary
        ];
    }
    http_response_code(200);
    echo json_encode([
        'message' => 'Collections retrieved successfully',
        'response' => 'success',
        'data' => $result
    ]);
    exit();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error: ' . $e->getMessage(), 'response' => 'error']);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'message' => 'Server error: ' . $e->getMessage(),
        'response' => 'error'
    ]);
} 