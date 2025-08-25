<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed', 'response' => 'error']);
    exit();
}

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

// Authorize user (admin and user roles can create expenses)
$userData = authorize(['admin', 'user']);

$database = new Database();
$db = $database->connect();

if($db === null) {
    http_response_code(503);
    echo json_encode(['message' => 'Failed to connect to the database.', 'response' => 'error']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($data['category_id']) || !isset($data['amount']) || !isset($data['expense_date'])) {
        http_response_code(400);
        echo json_encode(['message' => 'category_id, amount, and expense_date are required', 'response' => 'error']);
        exit();
    }
    
    $category_id = (int)$data['category_id'];
    $amount = (float)$data['amount'];
    $description = isset($data['description']) ? trim($data['description']) : '';
    $expense_date = trim($data['expense_date']);
    $created_by = $userData->username; // Get username from JWT token
    
    // Validate amount
    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(['message' => 'Amount must be greater than 0', 'response' => 'error']);
        exit();
    }
    
    // Validate expense_date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expense_date)) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid expense_date format. Use YYYY-MM-DD', 'response' => 'error']);
        exit();
    }
    
    // Check if category exists
    $category_check_stmt = $db->prepare('SELECT id FROM expense_categories WHERE id = ?');
    $category_check_stmt->execute([$category_id]);
    if (!$category_check_stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['message' => 'Category not found', 'response' => 'error']);
        exit();
    }
    
    // Generate unique receipt number
    $receipt_number = 'dix-expense-' . bin2hex(random_bytes(4)); // 8 character random hex
    
    // Insert expense with receipt number
    $stmt = $db->prepare('INSERT INTO expenses (receipt_number, category_id, amount, description, expense_date, created_by) VALUES (?, ?, ?, ?, ?, ?)');
    
    if ($stmt->execute([$receipt_number, $category_id, $amount, $description, $expense_date, $created_by])) {
        $expense_id = $db->lastInsertId();
        
        // Fetch the created expense with category name
        $fetch_stmt = $db->prepare('
            SELECT e.*, ec.name as category_name 
            FROM expenses e 
            JOIN expense_categories ec ON e.category_id = ec.id 
            WHERE e.id = ?
        ');
        $fetch_stmt->execute([$expense_id]);
        $expense = $fetch_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get company details
        $company_stmt = $db->prepare('SELECT * FROM organization_details LIMIT 1');
        $company_stmt->execute();
        $company = $company_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get user's stage
        $stage_stmt = $db->prepare('SELECT s.name as stage_name FROM _user_ u JOIN stage s ON u.stage = s.id WHERE u.username = ?');
        $stage_stmt->execute([$created_by]);
        $stage = $stage_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Add company details and stage to the expense data
        $expense['company_name'] = $company['name'] ?? 'iGuru';
        $expense['company_contacts'] = $company['contacts'] ?? '';
        $expense['stage'] = $stage['stage_name'] ?? 'UNKNOWN';
        
        echo json_encode([
            'message' => 'Expense created successfully',
            'response' => 'success',
            'data' => $expense
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to create expense', 'response' => 'error']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error: ' . $e->getMessage(), 'response' => 'error']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Server error: ' . $e->getMessage(), 'response' => 'error']);
}
?> 