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

// Authorize user (admin and user roles can read categories)
$userData = authorize(['admin', 'user']);

$database = new Database();
$db = $database->connect();

if($db === null) {
    http_response_code(503);
    echo json_encode(['message' => 'Failed to connect to the database.', 'response' => 'error']);
    exit();
}

try {
    // Get filters from both URL parameters and request body
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    
    // Get query parameters (URL takes precedence over body)
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($data['limit']) ? (int)$data['limit'] : 50);
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : (isset($data['offset']) ? (int)$data['offset'] : 0);
    $name = isset($_GET['name']) ? trim($_GET['name']) : (isset($data['name']) ? trim($data['name']) : '');
    
    // Build query
    $query = 'SELECT * FROM expense_categories WHERE 1=1';
    $params = [];
    $types = [];
    
    // Add filters
    if ($name) {
        $query .= ' AND name LIKE ?';
        $params[] = '%' . $name . '%';
        $types[] = PDO::PARAM_STR;
    }
    
    // Add ordering and pagination
    $query .= ' ORDER BY name ASC LIMIT ? OFFSET ?';
    $params[] = $limit;
    $params[] = $offset;
    $types[] = PDO::PARAM_INT;
    $types[] = PDO::PARAM_INT;
    
    $stmt = $db->prepare($query);
    
    // Bind parameters with proper types
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value, $types[$key]);
    }
    
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $count_query = 'SELECT COUNT(*) as total FROM expense_categories WHERE 1=1';
    $count_params = [];
    $count_types = [];
    
    if ($name) {
        $count_query .= ' AND name LIKE ?';
        $count_params[] = '%' . $name . '%';
        $count_types[] = PDO::PARAM_STR;
    }
    
    $count_stmt = $db->prepare($count_query);
    
    // Bind count parameters with proper types
    foreach ($count_params as $key => $value) {
        $count_stmt->bindValue($key + 1, $value, $count_types[$key]);
    }
    
    $count_stmt->execute();
    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'message' => 'Expense categories retrieved successfully',
        'response' => 'success',
        'data' => $categories,
        'pagination' => [
            'total' => (int)$total_count,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total_count
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error: ' . $e->getMessage(), 'response' => 'error']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Server error: ' . $e->getMessage(), 'response' => 'error']);
}
?> 