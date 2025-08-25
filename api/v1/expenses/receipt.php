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

// Authorize user (admin and user roles can view receipts)
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
    
    // Use stored receipt number
    $receipt_no = $expense['receipt_number'];
    
    // Build receipt array for frontend
    $receipt = [];
    
    // Receipt number
    $receipt[] = [
        "text_size" => "small",
        "is_bold" => false,
        "content" => $receipt_no,
        "pre_text" => "Receipt No: ",
        "end_1" => "\n"
    ];
    
    // Separator
    $receipt[] = [
        "text_size" => "normal",
        "is_bold" => false,
        "content" => "-----------------------------",
        "pre_text" => "",
        "end_1" => "\n"
    ];
    
    // Category
    $receipt[] = [
        "text_size" => "normal",
        "is_bold" => false,
        "content" => strtoupper($expense['category_name']),
        "pre_text" => "Category: ",
        "end_1" => "\n"
    ];
    
    // Description
    $receipt[] = [
        "text_size" => "normal",
        "is_bold" => false,
        "content" => $expense['description'],
        "pre_text" => "Description: ",
        "end_1" => "\n"
    ];
    
    // Amount
    $receipt[] = [
        "text_size" => "normal",
        "is_bold" => true,
        "content" => "Ksh " . number_format($expense['amount'], 2),
        "pre_text" => "Amount: ",
        "end_1" => "\n"
    ];
    
    // Separator
    $receipt[] = [
        "text_size" => "normal",
        "is_bold" => false,
        "content" => "-----------------------------",
        "pre_text" => "",
        "end_1" => "\n"
    ];
    
    // Expense Date
    $receipt[] = [
        "text_size" => "normal",
        "is_bold" => false,
        "content" => date('d/m/Y', strtotime($expense['expense_date'])),
        "pre_text" => "Expense Date: ",
        "end_1" => "\n"
    ];
    
    // Created At
    $receipt[] = [
        "text_size" => "normal",
        "is_bold" => false,
        "content" => date('d/m/Y H:i:s', strtotime($expense['created_at'])),
        "pre_text" => "Created At: ",
        "end_1" => "\n"
    ];
    
    // Created By
    $receipt[] = [
        "text_size" => "normal",
        "is_bold" => false,
        "content" => $expense['created_by'],
        "pre_text" => "Created By: ",
        "end_1" => "\n"
    ];
    
    // Separator
    $receipt[] = [
        "text_size" => "normal",
        "is_bold" => false,
        "content" => "-----------------------------",
        "pre_text" => "",
        "end_1" => "\n"
    ];
    
    // Company Name
    $receipt[] = [
        "text_size" => "normal",
        "is_bold" => false,
        "content" => $company['name'] ?? 'iGuru',
        "pre_text" => "Company: ",
        "end_1" => "\n"
    ];
    
    // Company Contacts (if available)
    if (!empty($company['contacts'])) {
        $receipt[] = [
            "text_size" => "normal",
            "is_bold" => false,
            "content" => $company['contacts'],
            "pre_text" => "Contacts: ",
            "end_1" => "\n"
        ];
    }
    
    // Served By (current user)
    $receipt[] = [
        "text_size" => "normal",
        "is_bold" => false,
        "content" => $userData->username,
        "pre_text" => "Served By: ",
        "end_1" => "\n"
    ];
    
    echo json_encode([
        "success" => true,
        "expense_id" => $expense['id'],
        "receipt" => $receipt,
        "company_name" => $company['name'] ?? 'iGuru',
        "company_email" => $company['email'] ?? '',
        "served_by" => $userData->username
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error: ' . $e->getMessage(), 'response' => 'error']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Server error: ' . $e->getMessage(), 'response' => 'error']);
}
?> 