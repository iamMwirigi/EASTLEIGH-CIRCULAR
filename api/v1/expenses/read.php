<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    // Get filters from both URL parameters and request body (URL takes precedence)
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($data['limit']) ? (int)$data['limit'] : 50);
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : (isset($data['offset']) ? (int)$data['offset'] : 0);
    $category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : (isset($data['category_id']) ? (int)$data['category_id'] : null);
    $category_name = isset($_GET['category_name']) ? trim($_GET['category_name']) : (isset($data['category_name']) ? trim($data['category_name']) : null);
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : (isset($data['start_date']) ? trim($data['start_date']) : null);
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : (isset($data['end_date']) ? trim($data['end_date']) : null);
    $min_amount = isset($_GET['min_amount']) ? (float)$_GET['min_amount'] : (isset($data['min_amount']) ? (float)$data['min_amount'] : null);
    $max_amount = isset($_GET['max_amount']) ? (float)$_GET['max_amount'] : (isset($data['max_amount']) ? (float)$data['max_amount'] : null);
    $created_by = isset($_GET['created_by']) ? trim($_GET['created_by']) : (isset($data['created_by']) ? trim($data['created_by']) : null);
    $receipt_number = isset($_GET['receipt_number']) ? trim($_GET['receipt_number']) : (isset($data['receipt_number']) ? trim($data['receipt_number']) : null);
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($data['id']) ? (int)$data['id'] : null);
    
    // Build query
    $query = 'SELECT e.*, ec.name as category_name FROM expenses e JOIN expense_categories ec ON e.category_id = ec.id WHERE 1=1';
    $params = [];
    $types = [];
    
    // Add filters
    if ($category_id) {
        $query .= ' AND e.category_id = ?';
        $params[] = $category_id;
        $types[] = PDO::PARAM_INT;
    }
    
    if ($category_name) {
        $query .= ' AND ec.name LIKE ?';
        $params[] = '%' . $category_name . '%';
        $types[] = PDO::PARAM_STR;
    }
    
    if ($start_date) {
        $query .= ' AND DATE(e.created_at) >= ?'; // Changed from expense_date
        $params[] = $start_date;
        $types[] = PDO::PARAM_STR;
    }
    
    if ($end_date) {
        $query .= ' AND DATE(e.created_at) <= ?'; // Changed from expense_date
        $params[] = $end_date;
        $types[] = PDO::PARAM_STR;
    }
    
    if ($min_amount !== null) {
        $query .= ' AND e.amount >= ?';
        $params[] = $min_amount;
        $types[] = PDO::PARAM_STR;
    }
    
    if ($max_amount !== null) {
        $query .= ' AND e.amount <= ?';
        $params[] = $max_amount;
        $types[] = PDO::PARAM_STR;
    }
    
    if ($created_by) {
        $query .= ' AND e.created_by LIKE ?';
        $params[] = '%' . $created_by . '%';
        $types[] = PDO::PARAM_STR;
    }
    
    if ($receipt_number) {
        $query .= ' AND e.receipt_number LIKE ?';
        $params[] = '%' . $receipt_number . '%';
        $types[] = PDO::PARAM_STR;
    }
    
    if ($id) {
        $query .= ' AND e.id = ?';
        $params[] = $id;
        $types[] = PDO::PARAM_INT;
    }
    
    // Count total records
    $count_query = 'SELECT COUNT(*) as total FROM expenses e JOIN expense_categories ec ON e.category_id = ec.id WHERE 1=1';
    $count_params = [];
    $count_types = [];
    
    // Add same filters to count query
    if ($category_id) {
        $count_query .= ' AND e.category_id = ?';
        $count_params[] = $category_id;
        $count_types[] = PDO::PARAM_INT;
    }
    
    if ($category_name) {
        $count_query .= ' AND ec.name LIKE ?';
        $count_params[] = '%' . $category_name . '%';
        $count_types[] = PDO::PARAM_STR;
    }
    
    if ($start_date) {
        $count_query .= ' AND DATE(e.created_at) >= ?';
        $count_params[] = $start_date;
        $count_types[] = PDO::PARAM_STR;
    }
    
    if ($end_date) {
        $count_query .= ' AND DATE(e.created_at) <= ?';
        $count_params[] = $end_date;
        $count_types[] = PDO::PARAM_STR;
    }
    
    if ($min_amount !== null) {
        $count_query .= ' AND e.amount >= ?';
        $count_params[] = $min_amount;
        $count_types[] = PDO::PARAM_STR;
    }
    
    if ($max_amount !== null) {
        $count_query .= ' AND e.amount <= ?';
        $count_params[] = $max_amount;
        $count_types[] = PDO::PARAM_STR;
    }
    
    if ($created_by) {
        $count_query .= ' AND e.created_by LIKE ?';
        $count_params[] = '%' . $created_by . '%';
        $count_types[] = PDO::PARAM_STR;
    }
    
    if ($receipt_number) {
        $count_query .= ' AND e.receipt_number LIKE ?';
        $count_params[] = '%' . $receipt_number . '%';
        $count_types[] = PDO::PARAM_STR;
    }
    
    if ($id) {
        $count_query .= ' AND e.id = ?';
        $count_params[] = $id;
        $count_types[] = PDO::PARAM_INT;
    }
    
    $count_stmt = $db->prepare($count_query);
    foreach ($count_params as $i => $param) {
        $count_stmt->bindValue($i + 1, $param, $count_types[$i]);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Add pagination
    $query .= ' ORDER BY e.created_at DESC LIMIT ? OFFSET ?';
    $params[] = $limit;
    $types[] = PDO::PARAM_INT;
    $params[] = $offset;
    $types[] = PDO::PARAM_INT;
    
    // Execute main query
    $stmt = $db->prepare($query);
    foreach ($params as $i => $param) {
        $stmt->bindValue($i + 1, $param, $types[$i]);
    }
    $stmt->execute();
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get company details
    $company_stmt = $db->prepare('SELECT * FROM organization_details LIMIT 1');
    $company_stmt->execute();
    $company = $company_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Add company details and stage to each expense
    foreach ($expenses as &$expense) {
        $expense['company_name'] = $company['name'] ?? 'iGuru';
        $expense['company_contacts'] = $company['contacts'] ?? '';
        
        // Get user's stage - handle case where user might not have a stage
        $stage_stmt = $db->prepare('SELECT s.name as stage_name FROM _user_ u LEFT JOIN stage s ON u.stage = s.id WHERE u.username = ?');
        $stage_stmt->execute([$expense['created_by']]);
        $stage = $stage_stmt->fetch(PDO::FETCH_ASSOC);
        $expense['stage'] = $stage['stage_name'] ?? 'UNKNOWN';
    }
    
    // Calculate summary
    $total_amount = 0;
    foreach ($expenses as $expense) {
        $total_amount += (float)$expense['amount'];
    }
    
    echo json_encode([
        'message' => 'Expenses retrieved successfully',
        'response' => 'success',
        'data' => $expenses,
        'summary' => [
            'total_records' => (int)$total_records,
            'total_amount' => $total_amount,
            'limit' => $limit,
            'offset' => $offset
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