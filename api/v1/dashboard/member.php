<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

$userData = authorize(['member']);

$database = new Database();
$db = $database->connect();

if ($db === null) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed.", "response" => "error"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

$start_date = isset($data->start_date) ? $data->start_date : (isset($_GET['start_date']) ? $_GET['start_date'] : null);
$end_date = isset($data->end_date) ? $data->end_date : (isset($_GET['end_date']) ? $_GET['end_date'] : null);

try {
    // Find the member's vehicles
    $stmt = $db->prepare('SELECT number_plate FROM vehicle WHERE owner = :owner_id');
    $stmt->bindParam(':owner_id', $userData->id);
    $stmt->execute();
    $vehicle_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $number_plates = array_column($vehicle_rows, 'number_plate');

    if (empty($number_plates)) {
        // Member owns no vehicles
        $response_data = [
            "transactions" => 0,
            "total_amount" => 0,
            "vehicles" => 0
        ];
        http_response_code(200);
        echo json_encode([
            "message" => "Dashboard data retrieved successfully",
            "response" => "success",
            "data" => $response_data
        ]);
        exit();
    }

    // Build placeholders for IN clause
    $in_clause = implode(',', array_fill(0, count($number_plates), '?'));
    $params = $number_plates;
    $date_clauses = [];
    if ($start_date) {
        $date_clauses[] = "t_date >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $date_clauses[] = "t_date <= ?";
        $params[] = $end_date;
    }
    $where_sql = " WHERE number_plate IN ($in_clause)";
    if (count($date_clauses) > 0) {
        $where_sql .= " AND " . implode(" AND ", $date_clauses);
    }

    $transactions_query = "SELECT COUNT(*) as transactions, SUM(amount) as total_amount FROM new_transaction" . $where_sql;
    $vehicles_query = "SELECT COUNT(DISTINCT number_plate) as vehicles FROM new_transaction" . $where_sql;

    // Execute queries
    $stmt_transactions = $db->prepare($transactions_query);
    $stmt_transactions->execute($params);
    $transactions_data = $stmt_transactions->fetch(PDO::FETCH_ASSOC);

    $stmt_vehicles = $db->prepare($vehicles_query);
    $stmt_vehicles->execute($params);
    $vehicles_data = $stmt_vehicles->fetch(PDO::FETCH_ASSOC);

    // Prepare response data
    $response_data = [
        "transactions" => $transactions_data['transactions'] ?? 0,
        "total_amount" => (int)($transactions_data['total_amount'] ?? 0),
        "vehicles" => $vehicles_data['vehicles'] ?? 0
    ];
    
    // Final Response
    http_response_code(200);
    echo json_encode([
        "message" => "Dashboard data retrieved successfully",
        "response" => "success",
        "data" => $response_data
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Error: " . $e->getMessage(), "response" => "error"]);
} 