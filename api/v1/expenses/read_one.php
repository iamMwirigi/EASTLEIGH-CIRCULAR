<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed', 'response' => 'error']);
    exit();
}

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

// Authorize user (admin and user roles can view expenses)
$userData = authorize(['admin', 'user']);

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
    
    // Fetch expense with category name
    $stmt = $db->prepare('
        SELECT e.*, ec.name as category_name 
        FROM expenses e 
        JOIN expense_categories ec ON e.category_id = ec.id 
        WHERE e.id = ?
    ');
    $stmt->execute([$expense_id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$expense) {
        http_response_code(404);
        echo json_encode(['message' => 'Expense not found', 'response' => 'error']);
        exit();
    }
    
    // Get company details
    $company_stmt = $db->prepare('SELECT * FROM organization_details LIMIT 1');
    $company_stmt->execute();
    $company = $company_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user's stage
    $stage_stmt = $db->prepare('SELECT s.name as stage_name FROM _user_ u LEFT JOIN stage s ON u.stage = s.id WHERE u.username = ?');
    $stage_stmt->execute([$expense['created_by']]);
    $stage = $stage_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Add company details and stage to the expense
    $expense['company_name'] = $company['name'] ?? 'DIX-HUIT SUPREME';
    $expense['company_contacts'] = $company['contacts'] ?? '';
    $expense['stage'] = $stage['stage_name'] ?? 'UNKNOWN';
    
    echo json_encode([
        'message' => 'Expense retrieved successfully',
        'response' => 'success',
        'data' => $expense
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error: ' . $e->getMessage(), 'response' => 'error']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Server error: ' . $e->getMessage(), 'response' => 'error']);
}
?> 