<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid JSON data', 'response' => 'error']);
        exit();
    }
    
    // Get expense ID from both URL parameter and request body
    $expense_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($data['id']) ? (int)$data['id'] : 0);
    
    if (!$expense_id) {
        http_response_code(400);
        echo json_encode(['message' => 'Expense ID is required', 'response' => 'error']);
        exit();
    }
    
    // Validate required fields
    if (!isset($data['category']) || trim($data['category']) === '') {
        http_response_code(400);
        echo json_encode(['message' => 'category is required', 'response' => 'error']);
        exit();
    }
    
    if (!isset($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
        http_response_code(400);
        echo json_encode(['message' => 'amount is required and must be a positive number', 'response' => 'error']);
        exit();
    }
    
    // Sanitize and validate data
    $category = trim($data['category']);
    $amount = (float)$data['amount'];
    $description = isset($data['description']) ? trim($data['description']) : '';
    
    // Validate category length
    if (strlen($category) > 100) {
        http_response_code(400);
        echo json_encode(['message' => 'category must be 100 characters or less', 'response' => 'error']);
        exit();
    }
    
    // Check if expense exists
    $check_stmt = $db->prepare('SELECT id FROM expenses WHERE id = ?');
    $check_stmt->execute([$expense_id]);
    if (!$check_stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['message' => 'Expense not found', 'response' => 'error']);
        exit();
    }
    
    // Update expense
    $stmt = $db->prepare('UPDATE expenses SET category = ?, amount = ?, description = ? WHERE id = ?');
    
    if ($stmt->execute([$category, $amount, $description, $expense_id])) {
        // Fetch the updated expense
        $fetch_stmt = $db->prepare('SELECT * FROM expenses WHERE id = ?');
        $fetch_stmt->execute([$expense_id]);
        $expense = $fetch_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'message' => 'Expense updated successfully',
            'response' => 'success',
            'expense' => $expense
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to update expense', 'response' => 'error']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error: ' . $e->getMessage(), 'response' => 'error']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Server error: ' . $e->getMessage(), 'response' => 'error']);
}
?> 