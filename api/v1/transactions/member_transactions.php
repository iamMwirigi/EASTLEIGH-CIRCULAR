<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

$userData = authorize(['admin', 'user']);

$database = new Database();
$db = $database->connect();

// Allow filtering by member_id, account_type_id, date range
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$member_id = $input['member_id'] ?? $_GET['member_id'] ?? null;
$account_type_id = $input['account_type_id'] ?? $_GET['account_type_id'] ?? null;
$start_date = $input['start_date'] ?? $_GET['start_date'] ?? null;
$end_date = $input['end_date'] ?? $_GET['end_date'] ?? null;
$limit = $input['limit'] ?? $_GET['limit'] ?? 50;
$offset = $input['offset'] ?? $_GET['offset'] ?? 0;

if (!$member_id) {
    http_response_code(400);
    echo json_encode(['message' => 'member_id is required', 'response' => 'error']);
    exit();
}

// Build WHERE clauses
$where = ['t.member_id = ?'];
$params = [$member_id];

if ($account_type_id) {
    $where[] = 't.account_type_id = ?';
    $params[] = $account_type_id;
}
if ($start_date) {
    $where[] = 't.transaction_date >= ?';
    $params[] = $start_date;
}
if ($end_date) {
    $where[] = 't.transaction_date <= ?';
    $params[] = $end_date;
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

// Get total count for pagination
$count_query = 'SELECT COUNT(*) as total FROM transactions t ' . $where_sql;
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get transactions with member and account type details
$query = 'SELECT 
    t.*, 
    m.name as member_name, 
    m.phone_number,
    m.number as member_number,
    at.name as account_type_name,
    at.description as account_type_description
FROM transactions t 
JOIN member m ON t.member_id = m.id 
JOIN account_type at ON t.account_type_id = at.id 
' . $where_sql . ' 
ORDER BY t.transaction_date DESC, t.created_at DESC 
LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

$stmt = $db->prepare($query);
$stmt->execute($params);

$transactions = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $transactions[] = [
        'id' => (int)$row['id'],
        'member_id' => (int)$row['member_id'],
        'member_name' => $row['member_name'],
        'member_phone' => $row['phone_number'],
        'member_number' => (int)$row['member_number'],
        'account_type_id' => (int)$row['account_type_id'],
        'account_type_name' => $row['account_type_name'],
        'account_type_description' => $row['account_type_description'],
        'transaction_type' => $row['transaction_type'],
        'amount' => (float)$row['amount'],
        'balance_before' => (float)$row['balance_before'],
        'balance_after' => (float)$row['balance_after'],
        'description' => $row['description'],
        'reference_number' => $row['reference_number'],
        'transaction_date' => $row['transaction_date'],
        'created_at' => date('Y-m-d H:i:s', strtotime($row['created_at'])),
        'formatted_date' => date('M j, Y, g:i:s A', strtotime($row['created_at']))
    ];
}

// Get member details
$member_stmt = $db->prepare('SELECT id, name, phone_number, number FROM member WHERE id = ?');
$member_stmt->execute([$member_id]);
$member = $member_stmt->fetch(PDO::FETCH_ASSOC);

// Check if member exists
if (!$member) {
    http_response_code(404);
    echo json_encode(['message' => 'Member not found with the provided ID.', 'response' => 'error']);
    exit();
}

// Get account summary
$account_summary_stmt = $db->prepare('
    SELECT 
        at.name as account_type_name,
        COUNT(t.id) as transaction_count,
        SUM(CASE WHEN t.transaction_type IN ("deposit", "interest") THEN t.amount ELSE 0 END) as total_deposits,
        SUM(CASE WHEN t.transaction_type IN ("withdrawal", "fee") THEN t.amount ELSE 0 END) as total_withdrawals
    FROM member_account_types mat
    JOIN account_type at ON mat.account_typeid = at.id
    LEFT JOIN transactions t ON mat.member_id = t.member_id AND mat.account_typeid = t.account_type_id
    WHERE mat.member_id = ?
    GROUP BY at.id, at.name
');
$account_summary_stmt->execute([$member_id]);
$account_summary = $account_summary_stmt->fetchAll(PDO::FETCH_ASSOC);

$response = [
    'message' => 'Member transactions retrieved successfully',
    'response' => 'success',
    'data' => [
        'member' => [
            'id' => (int)$member['id'],
            'name' => $member['name'],
            'phone_number' => $member['phone_number'],
            'number' => (int)$member['number']
        ],
        'transactions' => $transactions,
        'account_summary' => $account_summary,
        'pagination' => [
            'total' => (int)$total_count,
            'limit' => (int)$limit,
            'offset' => (int)$offset,
            'total_pages' => ceil($total_count / $limit)
        ]
    ]
];

echo json_encode($response); 