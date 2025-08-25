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

// Authorize user (only admin can create categories)
$userData = authorize(['admin']);

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
    
    // Validate required fields
    if (!isset($data['name']) || trim($data['name']) === '') {
        http_response_code(400);
        echo json_encode(['message' => 'name is required', 'response' => 'error']);
        exit();
    }
    
    // Sanitize and validate data
    $name = trim($data['name']);
    
    // Validate name length
    if (strlen($name) > 100) {
        http_response_code(400);
        echo json_encode(['message' => 'name must be 100 characters or less', 'response' => 'error']);
        exit();
    }
    
    // Check if category already exists
    $check_stmt = $db->prepare('SELECT id FROM expense_categories WHERE name = ?');
    $check_stmt->execute([$name]);
    if ($check_stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['message' => 'Category already exists', 'response' => 'error']);
        exit();
    }
    
    // Insert category
    $stmt = $db->prepare('INSERT INTO expense_categories (name) VALUES (?)');
    
    if ($stmt->execute([$name])) {
        $category_id = $db->lastInsertId();
        
        // Fetch the created category
        $fetch_stmt = $db->prepare('SELECT * FROM expense_categories WHERE id = ?');
        $fetch_stmt->execute([$category_id]);
        $category = $fetch_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'message' => 'Expense category created successfully',
            'response' => 'success',
            'category' => $category
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to create expense category', 'response' => 'error']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error: ' . $e->getMessage(), 'response' => 'error']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Server error: ' . $e->getMessage(), 'response' => 'error']);
}
?> 