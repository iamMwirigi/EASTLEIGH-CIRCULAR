<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method Not Allowed. Use POST.', 'response' => 'error']);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

// Validate required fields
$required_fields = ['number_plate', 'start_date', 'end_date', 'account_type'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['message' => "Missing required field: $field", 'response' => 'error']);
        exit();
    }
}

if ($data['account_type'] !== 'operations') {
    http_response_code(400);
    echo json_encode(['message' => "This report only supports the 'operations' account type.", 'response' => 'error']);
    exit();
}

$number_plate = $data['number_plate'];
$start_date = $data['start_date'];
$end_date = $data['end_date'];

// Query to get individual operations collections for the specified vehicle and date range
$query = 'SELECT
            operations as amount,
            collected_by,
            t_date as date,
            stage_name
          FROM
            new_transaction
          WHERE
            number_plate = :number_plate
            AND t_date >= :start_date
            AND t_date <= :end_date
            AND operations > 0
          ORDER BY
            t_date DESC';

$stmt = $db->prepare($query);
$stmt->bindParam(':number_plate', $number_plate);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);

$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$num = count($results);

if ($num > 0) {
    $total_operations = 0;
    $collections_by_user = [];

    foreach ($results as $row) {
        $amount = (float)$row['amount'];
        $total_operations += $amount;
        $collections_by_user[] = [
            'collected_by' => $row['collected_by'],
            'amount' => $amount,
            'date' => $row['date'],
            'stage_name' => $row['stage_name']
        ];
    }

    $response_data = [
        'number_plate' => $number_plate,
        'date_range' => [
            'start' => $start_date,
            'end' => $end_date
        ],
        'summary' => [
            'total_operations' => $total_operations,
            'total_collections' => $num
        ],
        'collections_by_user' => $collections_by_user
    ];

    http_response_code(200);
    echo json_encode([
        'message' => 'Operations report retrieved successfully',
        'response' => 'success',
        'data' => $response_data
    ]);
} else {
    http_response_code(404);
    echo json_encode(['message' => 'No operations collections found for the specified vehicle and date range.', 'response' => 'error']);
}