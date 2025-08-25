<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

// Authorize admin or user
$userData = authorize(['admin', 'user']);

// Instantiate DB & connect
$database = new Database();
$db = $database->connect();

if ($db === null) {
    http_response_code(503);
    echo json_encode(['message' => 'Failed to connect to the database.', 'response' => 'error']);
    exit();
}

// Get filter parameters from POST request body
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$dateFrom = $input['dateFrom'] ?? null;
$dateTo = $input['dateTo'] ?? null;

if (!$dateFrom || !$dateTo) {
    http_response_code(400);
    echo json_encode(['message' => '`dateFrom` and `dateTo` are required.', 'response' => 'error']);
    exit();
}

// Prepare the query to get daily aggregated data
$query = 'SELECT
            sent_date,
            COUNT(id) AS daily_total_messages,
            SUM(cost) AS daily_total_cost,
            SUM(CASE WHEN sent_status = 1 THEN 1 ELSE 0 END) AS daily_success_count,
            SUM(CASE WHEN sent_status != 1 THEN 1 ELSE 0 END) AS daily_failed_count
          FROM
            sms
          WHERE
            sent_date >= :dateFrom AND sent_date <= :dateTo
          GROUP BY
            sent_date
          ORDER BY
            sent_date DESC';

$stmt = $db->prepare($query);
$stmt->bindParam(':dateFrom', $dateFrom);
$stmt->bindParam(':dateTo', $dateTo);
$stmt->execute();

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dailyReports = [];
$totalMessages = 0;
$totalCost = 0;
$totalSuccess = 0;

foreach ($results as $row) {
    $daily_total_messages = (int)$row['daily_total_messages'];
    $daily_total_cost = (float)$row['daily_total_cost'];
    $daily_success_count = (int)$row['daily_success_count'];
    $daily_failed_count = (int)$row['daily_failed_count'];

    $dailyReports[] = [
        'date' => $row['sent_date'],
        'totalMessages' => $daily_total_messages,
        'totalCost' => 'KES ' . number_format($daily_total_cost, 2),
        'statusBreakdown' => [
            'Success' => $daily_success_count,
            'Failed' => $daily_failed_count,
            'Sent' => 0, // Placeholder as we don't have this status
            'Queued' => 0, // Placeholder
            'Rejected' => 0, // Placeholder
        ]
    ];

    // Aggregate for the main summary
    $totalMessages += $daily_total_messages;
    $totalCost += $daily_total_cost;
    $totalSuccess += $daily_success_count;
}

$successRate = ($totalMessages > 0) ? round(($totalSuccess / $totalMessages) * 100, 2) : 0;

$summary = [
    'totalMessages' => $totalMessages,
    'totalCost' => 'KES ' . number_format($totalCost, 2),
    'successRate' => $successRate,
    'dateRange' => [
        'from' => $dateFrom,
        'to' => $dateTo
    ]
];

$final_response = [
    'success' => true,
    'data' => [
        'summary' => $summary,
        'dailyReports' => $dailyReports
    ]
];

http_response_code(200);
echo json_encode($final_response);