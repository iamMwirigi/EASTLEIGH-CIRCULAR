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
    // Get category ID from both URL parameter and request body
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $category_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($data['id']) ? (int)$data['id'] : 0);
    
    if (!$category_id) {
        http_response_code(400);
        echo json_encode(['message' => 'Category ID is required', 'response' => 'error']);
        exit();
    }
    
    // Check if category exists and fetch it before deletion
    $check_stmt = $db->prepare('SELECT * FROM expense_categories WHERE id = ?');
    $check_stmt->execute([$category_id]);
    $category = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        http_response_code(404);
        echo json_encode(['message' => 'Category not found', 'response' => 'error']);
        exit();
    }
    
    // Check if category is being used in expenses
    $usage_check_stmt = $db->prepare('SELECT COUNT(*) as count FROM expenses WHERE category_id = ?');
    $usage_check_stmt->execute([$category_id]);
    $usage_count = $usage_check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($usage_count > 0) {
        http_response_code(409);
        echo json_encode([
            'message' => 'Cannot delete category. It is being used by ' . $usage_count . ' expense(s).',
            'response' => 'error',
            'usage_count' => (int)$usage_count
        ]);
        exit();
    }
    
    // Delete category
    $stmt = $db->prepare('DELETE FROM expense_categories WHERE id = ?');
    
    if ($stmt->execute([$category_id])) {
        echo json_encode([
            'message' => 'Category deleted successfully',
            'response' => 'success',
            'deleted_category' => $category
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to delete category', 'response' => 'error']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error: ' . $e->getMessage(), 'response' => 'error']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Server error: ' . $e->getMessage(), 'response' => 'error']);
}
?> 