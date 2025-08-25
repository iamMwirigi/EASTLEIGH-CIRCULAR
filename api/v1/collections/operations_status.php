<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

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

// Handle both GET and POST for filter parameters
$data = json_decode(file_get_contents("php://input"), true) ?? [];
$start_date = $data['start_date'] ?? $_GET['start_date'] ?? null;
$end_date = $data['end_date'] ?? $_GET['end_date'] ?? null;
$stage_name = $data['stage_name'] ?? $_GET['stage_name'] ?? null;
$number_plate_filter = $data['number_plate'] ?? $_GET['number_plate'] ?? null;

// 1. Get all vehicles from the company
if ($number_plate_filter) {
    // Use LIKE to allow partial matches
    $vehicle_stmt = $db->prepare('SELECT number_plate FROM vehicle WHERE number_plate LIKE :number_plate ORDER BY number_plate ASC');
    $vehicle_stmt->execute([':number_plate' => '%' . $number_plate_filter . '%']);
} else {
    $vehicle_stmt = $db->prepare('SELECT number_plate FROM vehicle ORDER BY number_plate ASC');
    $vehicle_stmt->execute();
}
$all_vehicle_plates = $vehicle_stmt->fetchAll(PDO::FETCH_COLUMN);

// 2. Build query to get all operations transactions within the date range
$collection_params = [];
$collection_where = ["operations > 0"]; // Only get actual contributions

if ($start_date) {
    $collection_where[] = "t_date >= :start_date";
    $collection_params[':start_date'] = $start_date;
}
if ($end_date) {
    $collection_where[] = "t_date <= :end_date";
    $collection_params[':end_date'] = $end_date;
}
if ($userData->role === 'user') {
    $collection_where[] = "collected_by = :username";
    $collection_params[':username'] = $userData->username;
}
if ($stage_name) {
    $collection_where[] = "stage_name = :stage_name";
    $collection_params[':stage_name'] = $stage_name;
}
// Also filter the transactions by number plate if provided
if ($number_plate_filter) {
    $collection_where[] = "number_plate LIKE :number_plate_filter";
    $collection_params[':number_plate_filter'] = '%' . $number_plate_filter . '%';
}

$where_sql = !empty($collection_where) ? 'WHERE ' . implode(' AND ', $collection_where) : '';
// Fetch individual transactions to aggregate collectors and find the latest details in PHP
$collection_query = "SELECT number_plate, operations, collected_by, t_date, t_time, stage_name, receipt_no FROM new_transaction $where_sql";
$collection_stmt = $db->prepare($collection_query);
$collection_stmt->execute($collection_params);
$operations_transactions = $collection_stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Aggregate transactions by number plate in PHP
$aggregated_collections = [];
$total_operations_summary = 0;
$total_transactions_count = count($operations_transactions);

foreach ($operations_transactions as $tx) {
    $plate = trim($tx['number_plate']);
    if (!isset($aggregated_collections[$plate])) {
        $aggregated_collections[$plate] = [
            'operations' => 0,
            'collectors' => [],
            'latest_date' => '0000-00-00',
            'latest_time' => '00:00:00',
            'stage_name' => null,
            'receipt_no' => null
        ];
    }
    $operation_amount = (float)$tx['operations'];
    $aggregated_collections[$plate]['operations'] += $operation_amount;
    $total_operations_summary += $operation_amount;
    
    // Check if the current transaction is newer to grab its details
    $is_newer = false;
    if ($tx['t_date'] > $aggregated_collections[$plate]['latest_date']) {
        $is_newer = true;
    } elseif ($tx['t_date'] == $aggregated_collections[$plate]['latest_date'] && $tx['t_time'] > $aggregated_collections[$plate]['latest_time']) {
        $is_newer = true;
    }
    if ($is_newer) {
        $aggregated_collections[$plate]['latest_date'] = $tx['t_date'];
        $aggregated_collections[$plate]['latest_time'] = $tx['t_time'];
        $aggregated_collections[$plate]['stage_name'] = $tx['stage_name'];
        $aggregated_collections[$plate]['receipt_no'] = $tx['receipt_no'];
    }

    $collector_name = trim($tx['collected_by']);
    if (!empty($collector_name)) {
        $standardized_name = ucwords(strtolower($collector_name));
        $lower_case_key = strtolower($collector_name);
        $aggregated_collections[$plate]['collectors'][$lower_case_key] = $standardized_name;
    }
}

// 4. Process data and build response
$contributed_list = [];
$defaulted_list = [];

// Fetch company details once
$org_stmt = $db->prepare('SELECT name, contacts FROM organization_details ORDER BY id ASC LIMIT 1');
$org_stmt->execute();
$org = $org_stmt->fetch(PDO::FETCH_ASSOC);
$company_name = $org ? $org['name'] : null;
$company_contacts = $org ? $org['contacts'] : null;
$today = date('Y-m-d');

// Create contributed and defaulted lists
foreach ($all_vehicle_plates as $plate) {
    if (isset($aggregated_collections[$plate])) {
        // This vehicle contributed
        $agg_data = $aggregated_collections[$plate];
        $collectors_str = implode(', ', array_values($agg_data['collectors']));
        
        $contributed_list[] = [
            'id' => null,
            'number_plate' => $plate,
            't_time' => null,
            't_date' => $agg_data['latest_date'],
            's_time' => null,
            's_date' => null,
            'client_side_id' => null,
            'receipt_no' => $agg_data['receipt_no'],
            'collected_by' => $collectors_str ?: null,
            'stage_name' => $agg_data['stage_name'],
            'delete_status' => 0,
            'for_date' => $start_date ?? $today,
            'operations' => $agg_data['operations'],
            'amount' => $agg_data['operations'],
            'company_name' => $company_name,
            'company_contacts' => $company_contacts,
            '_sort_date' => $agg_data['latest_date']
        ];
    } else {
        // This vehicle defaulted
        $defaulted_list[] = ['number_plate' => $plate];
    }
}

// Sort the contributed list by latest date
usort($contributed_list, function($a, $b) {
    return strcmp($b['_sort_date'], $a['_sort_date']);
});

// Remove temporary sort key
array_walk($contributed_list, function(&$row) { unset($row['_sort_date']); });

// 5. Build the final response structure
http_response_code(200);
echo json_encode([
    'contributed' => $contributed_list,
    'defaulted' => $defaulted_list,
    'summary' => [
        'total_operations' => $total_operations_summary,
        'total_amount' => $total_operations_summary,
        'transactions' => $total_transactions_count,
        'vehicles_paid' => count($contributed_list),
        'vehicles_in_default' => count($defaulted_list)
    ],
    'message' => 'Collections retrieved successfully',
    'response' => 'success',
    'company_name' => $company_name,
    'company_contacts' => $company_contacts
]);
