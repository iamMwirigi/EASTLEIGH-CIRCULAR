<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';

$database = new Database();
$db = $database->connect();

if ($db === null) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed.", "response" => "error"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));
$query = isset($data->query) ? trim($data->query) : '';
$start_date = isset($data->start_date) ? $data->start_date : null;
$end_date = isset($data->end_date) ? $data->end_date : null;
$receipt_number = isset($data->receipt_number) ? trim($data->receipt_number) : '';

$where_clauses = [];
$params = [];

if ($query !== '') {
    if (is_numeric($query)) {
        $where_clauses[] = "m.number = ?";
        $params[] = $query;
    } else {
        $where_clauses[] = "LOWER(m.name) LIKE ?";
        $params[] = '%' . strtolower($query) . '%';
    }
}
if ($start_date) {
    $where_clauses[] = "c.t_date >= ?";
    $params[] = $start_date;
}
if ($end_date) {
    $where_clauses[] = "c.t_date <= ?";
    $params[] = $end_date;
}
if ($receipt_number !== '') {
    $where_clauses[] = "c.receipt_no LIKE ?";
    $params[] = '%' . $receipt_number . '%';
}
$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : '';

// Get all matching members (even those without collections)
$member_where = [];
$member_params = [];
if ($query !== '') {
    if (is_numeric($query)) {
        $member_where[] = "number = ?";
        $member_params[] = $query;
    } else {
        $member_where[] = "LOWER(name) LIKE ?";
        $member_params[] = '%' . strtolower($query) . '%';
    }
}
$member_where_sql = count($member_where) > 0 ? " WHERE " . implode(" AND ", $member_where) : '';
$members_sql = "SELECT id, name FROM member $member_where_sql";
$member_stmt = $db->prepare($members_sql);
$member_stmt->execute($member_params);
$all_members = $member_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build a map for quick lookup
$members = [];
foreach ($all_members as $m) {
    $members[$m['id']] = [
        'member_id' => (string)$m['id'],
        'member_name' => $m['name'],
        'member_phone' => null, // Will be filled in below
        'collection' => [],
        'totals' => [
            'operations' => 0,
            'loans' => 0,
            'seasonal_tickets' => 0,
            'savings' => 0,
            'insurance' => 0,
            'grand_total_deductions' => 0
        ]
    ];
}

$sql = "SELECT c.*, m.id as member_id, m.name, m.phone_number, m.number
        FROM new_transaction c
        JOIN vehicle v ON c.number_plate = v.number_plate
        JOIN member m ON v.owner = m.id
        $where_sql
        ORDER BY m.id, c.number_plate, c.t_date DESC, c.t_time DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by member, then by vehicle
foreach ($results as $row) {
    $member_id = $row['member_id'];
    if (!isset($members[$member_id])) {
        // Should not happen, but just in case
        $members[$member_id] = [
            'member_id' => (string)$member_id,
            'member_name' => $row['name'],
            'member_phone' => $row['phone_number'] ?? null,
            'collection' => [],
            'totals' => [
                'operations' => 0,
                'loans' => 0,
                'seasonal_tickets' => 0,
                'savings' => 0,
                'insurance' => 0,
                'grand_total_deductions' => 0
            ]
        ];
    }
    // Always update member_phone if available
    if (isset($row['phone_number'])) {
        $members[$member_id]['member_phone'] = $row['phone_number'];
    }
    // Ensure amount is never null
    if (!isset($row['amount']) || $row['amount'] === null) {
        $row['amount'] = 0;
    }
    $number_plate = $row['number_plate'];
    // Per-vehicle aggregation
    if (!isset($members[$member_id]['collection'][$number_plate])) {
        $members[$member_id]['collection'][$number_plate] = [
            'number_plate' => $number_plate,
            'operations' => 0,
            'loans' => 0,
            'seasonal_tickets' => 0,
            'savings' => 0,
            'insurance' => 0,
            'total_deductions' => 0
        ];
    }
    // Sum up deductions for this transaction
    $fields = ['operations','loans','seasonal_tickets','savings','insurance'];
    $total = 0;
    foreach ($fields as $field) {
        $val = isset($row[$field]) ? (float)$row[$field] : 0;
        $members[$member_id]['collection'][$number_plate][$field] += $val;
        $members[$member_id]['totals'][$field] += $val;
        $total += $val;
    }
    $members[$member_id]['collection'][$number_plate]['total_deductions'] += $total;
    $members[$member_id]['totals']['grand_total_deductions'] += $total;
}

// Format collections as arrays
foreach ($members as &$member) {
    $member['collection'] = array_values($member['collection']);
}

// Return only the single matching member (or null if not found)
$final_data = count($members) > 0 ? array_values($members)[0] : null;

http_response_code(200);
echo json_encode([
    "message" => "Member collections and deductions retrieved successfully",
    "response" => "success",
    "data" => $final_data
]);