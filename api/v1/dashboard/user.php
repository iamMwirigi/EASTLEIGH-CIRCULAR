<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

$userData = authorize(['user', 'admin']);

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
    $where_clauses = ["collected_by = :username"];
    $params = [":username" => $userData->username];
    if ($start_date) {
        $where_clauses[] = "t_date >= :start_date";
        $params[":start_date"] = $start_date;
    }
    if ($end_date) {
        $where_clauses[] = "t_date <= :end_date";
        $params[":end_date"] = $end_date;
    }
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);

    $transactions_query = "SELECT COUNT(*) as transactions, SUM(amount) as total_amount FROM new_transaction" . $where_sql;

    $stmt_transactions = $db->prepare($transactions_query);
    $stmt_transactions->execute($params);
    $transactions_data = $stmt_transactions->fetch(PDO::FETCH_ASSOC);

    $response_data = [
        "transactions" => (int)($transactions_data['transactions'] ?? 0),
        "total_amount" => (float)($transactions_data['total_amount'] ?? 0)
    ];

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