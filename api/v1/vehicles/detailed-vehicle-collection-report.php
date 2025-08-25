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
    echo json_encode(['status' => 'error', 'message' => 'Failed to connect to the database.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed. Use POST.']);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

// Validate required fields
if (!isset($data->vehicle_ids) || !isset($data->start_date) || !isset($data->end_date) || !isset($data->query)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields: vehicle_ids, start_date, end_date, query']);
    exit();
}

if (!is_array($data->vehicle_ids) || empty($data->vehicle_ids)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'vehicle_ids must be a non-empty array']);
    exit();
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data->start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data->end_date)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid date format. Use YYYY-MM-DD']);
    exit();
}

$available_fields = ['operations', 'loans', 'seasonal_tickets', 'savings', 'insurance'];

// Determine which fields to include
$fields_to_include = [];
if (is_array($data->query) && in_array('all', $data->query)) {
    $fields_to_include = $available_fields;
} else {
    foreach ($data->query as $field) {
        if (in_array($field, $available_fields)) {
            $fields_to_include[] = $field;
        }
    }
}
if (empty($fields_to_include)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No valid collection fields specified in query']);
    exit();
}

try {
    // Get vehicle info
    $vehicle_placeholders = implode(',', array_fill(0, count($data->vehicle_ids), '?'));
    $vehicle_query = 'SELECT v.id, v.number_plate FROM vehicle v';
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
            'status' => 'success',
            'message' => 'No vehicles found or access denied',
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'data' => [
                'vehicles' => [],
                'grand_total_summary' => new stdClass()
            ]
        ]);
        exit();
    }
    $vehicle_id_map = array_column($vehicles, 'number_plate', 'id');
    $vehicles_response = [];
    $grand_totals = array_fill_keys($fields_to_include, 0);
    $grand_totals['total_collection'] = 0;
    foreach ($vehicles as $vehicle) {
        $vehicle_id = $vehicle['id'];
        $number_plate = $vehicle['number_plate'];
        // Fetch all transactions for this vehicle in date range
        $sql = 'SELECT * FROM new_transaction WHERE number_plate = ? AND t_date >= ? AND t_date <= ? ORDER BY t_date DESC, id DESC';
        $params = [$number_plate, $data->start_date, $data->end_date];
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $collections = [];
        $totals = array_fill_keys($fields_to_include, 0);
        $totals['total_collection'] = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $collection_item = [
                'id' => (int)$row['id'],
                'number_plate' => $row['number_plate'],
                't_time' => $row['t_time'],
                't_date' => $row['t_date'],
                's_time' => isset($row['s_time']) ? $row['s_time'] : null,
                's_date' => isset($row['s_date']) ? $row['s_date'] : null,
                'client_side_id' => isset($row['client_side_id']) ? $row['client_side_id'] : null,
                'receipt_no' => isset($row['receipt_no']) ? $row['receipt_no'] : null,
                'collected_by' => $row['collected_by'],
                'stage_name' => $row['stage_name'],
                'delete_status' => isset($row['delete_status']) ? (int)$row['delete_status'] : 0,
                'for_date' => isset($row['for_date']) ? $row['for_date'] : $row['t_date'],
            ];
            $total_collection = 0;
            foreach ($fields_to_include as $field) {
                $collection_item[$field] = isset($row[$field]) ? (float)$row[$field] : 0;
                $totals[$field] += $collection_item[$field];
                $total_collection += $collection_item[$field];
            }
            $collection_item['total_collection'] = $total_collection;
            $totals['total_collection'] += $total_collection;
            $collections[] = $collection_item;
        }
        // Add to grand totals
        foreach ($fields_to_include as $field) {
            $grand_totals[$field] += $totals[$field];
        }
        $grand_totals['total_collection'] += $totals['total_collection'];
        $vehicles_response[] = [
            'id' => (int)$vehicle_id,
            'collections' => $collections,
            'total_summary' => $totals
        ];
    }
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Collections retrieved successfully',
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'data' => [
            'vehicles' => $vehicles_response,
            'grand_total_summary' => $grand_totals
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
} 