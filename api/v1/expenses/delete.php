<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed', 'response' => 'error']);
    exit();
}

include_once __DIR__ . '/../../../config/Database.php';

$database = new Database();
$db = $database->connect();

if($db === null) {
    http_response_code(503);
    echo json_encode(['message' => 'Failed to connect to the database.', 'response' => 'error']);
    exit();
}

try {
    // Get expense ID from both URL parameter and request body
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $expense_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($data['id']) ? (int)$data['id'] : 0);
    
    if (!$expense_id) {
        http_response_code(400);
        echo json_encode(['message' => 'Expense ID is required', 'response' => 'error']);
        exit();
    }
    
    // Check if expense exists and fetch it before deletion
    $check_stmt = $db->prepare('SELECT * FROM expenses WHERE id = ?');
    $check_stmt->execute([$expense_id]);
    $expense = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$expense) {
        http_response_code(404);
        echo json_encode(['message' => 'Expense not found', 'response' => 'error']);
        exit();
    }
    
    // Delete expense
    $stmt = $db->prepare('DELETE FROM expenses WHERE id = ?');
    
    if ($stmt->execute([$expense_id])) {
        echo json_encode([
            'message' => 'Expense deleted successfully',
            'response' => 'success',
            'deleted_expense' => $expense
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to delete expense', 'response' => 'error']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error: ' . $e->getMessage(), 'response' => 'error']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Server error: ' . $e->getMessage(), 'response' => 'error']);
}
?> 