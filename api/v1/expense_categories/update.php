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
    
    // Get category ID from both URL parameter and request body
    $category_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($data['id']) ? (int)$data['id'] : 0);
    
    if (!$category_id) {
        http_response_code(400);
        echo json_encode(['message' => 'Category ID is required', 'response' => 'error']);
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
    
    // Check if category exists
    $check_stmt = $db->prepare('SELECT id FROM expense_categories WHERE id = ?');
    $check_stmt->execute([$category_id]);
    if (!$check_stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['message' => 'Category not found', 'response' => 'error']);
        exit();
    }
    
    // Check if name already exists (excluding current category)
    $name_check_stmt = $db->prepare('SELECT id FROM expense_categories WHERE name = ? AND id != ?');
    $name_check_stmt->execute([$name, $category_id]);
    if ($name_check_stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['message' => 'Category name already exists', 'response' => 'error']);
        exit();
    }
    
    // Update category
    $stmt = $db->prepare('UPDATE expense_categories SET name = ? WHERE id = ?');
    
    if ($stmt->execute([$name, $category_id])) {
        // Fetch the updated category
        $fetch_stmt = $db->prepare('SELECT * FROM expense_categories WHERE id = ?');
        $fetch_stmt->execute([$category_id]);
        $category = $fetch_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'message' => 'Category updated successfully',
            'response' => 'success',
            'category' => $category
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to update category', 'response' => 'error']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error: ' . $e->getMessage(), 'response' => 'error']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Server error: ' . $e->getMessage(), 'response' => 'error']);
}
?> 