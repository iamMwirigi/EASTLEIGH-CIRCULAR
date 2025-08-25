<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

$userData = authorize(['admin']);

$database = new Database();
$db = $database->connect();

if($db === null) {
    http_response_code(503);
    echo json_encode(['message' => 'Failed to connect to the database.', 'response' => 'error']);
    exit();
}

// Get filter parameters
$data = json_decode(file_get_contents("php://input"), true) ?: [];
$receipt_number = isset($_GET['receipt_number']) ? trim($_GET['receipt_number']) : (isset($data['receipt_number']) ? trim($data['receipt_number']) : null);

// Get all members
$member_query = 'SELECT id, name FROM member ORDER BY name ASC';
$member_stmt = $db->prepare($member_query);
$member_stmt->execute();
$members = $member_stmt->fetchAll(PDO::FETCH_ASSOC);

$deduction_fields = ['operations', 'loans', 'county', 'savings', 'insurance'];mysql>
$results = [];

foreach ($members as $member) {
    // Get all vehicles owned by the member
    $vehicle_query = 'SELECT number_plate FROM vehicle WHERE owner = :owner';
    $vehicle_stmt = $db->prepare($vehicle_query);
    $vehicle_stmt->bindParam(':owner', $member['id']);
    $vehicle_stmt->execute();
    $vehicles = $vehicle_stmt->fetchAll(PDO::FETCH_COLUMN);

    $per_vehicle = [];
    $overall_totals = array_fill_keys($deduction_fields, 0);
    $overall_totals['grand_total_deductions'] = 0;

    foreach ($vehicles as $number_plate) {
        $query = 'SELECT * FROM new_transaction WHERE number_plate = :number_plate';
        $params = [':number_plate' => $number_plate];
        if ($receipt_number) {
            $query .= ' AND receipt_no LIKE :receipt_number';
            $params[':receipt_number'] = '%' . $receipt_number . '%';
        }
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $deductions = array_fill_keys($deduction_fields, 0);
        $total_deductions = 0;
        $amount = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach ($deduction_fields as $field) {
                $deductions[$field] += is_numeric($row[$field]) ? (float)$row[$field] : 0;
            }
        }
        foreach ($deduction_fields as $field) {
            $total_deductions += $deductions[$field];
            $overall_totals[$field] += $deductions[$field];
            $amount += $deductions[$field];
        }
        $overall_totals['grand_total_deductions'] += $total_deductions;
        $per_vehicle[] = array_merge([
            'number_plate' => $number_plate,
            'amount' => $amount,
            'total_deductions' => $total_deductions
        ], $deductions);
    }

    $results[] = [
        'member_id' => $member['id'],
        'member_name' => $member['name'],
        'collection' => $per_vehicle,
        'totals' => $overall_totals
    ];
}

http_response_code(200);
echo json_encode([
    'message' => 'Member collections and deductions retrieved successfully',
    'response' => 'success',
    'collections' => $results
]); 