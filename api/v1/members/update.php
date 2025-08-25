<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

$userData = authorize(['admin']);

$database = new Database();
$db = $database->connect();

if($db === null) {
    http_response_code(503);
    echo json_encode(['message' => 'Failed to connect to the database.', 'response' => 'error']);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->id) || empty($data->name) || empty($data->phone_number) || empty($data->number)) {
    http_response_code(400);
    echo json_encode(array("message" => "Incomplete data for member update.", "response" => "error"));
    return;
}

$query = 'UPDATE member SET name = :name, phone_number = :phone_number, number = :number WHERE id = :id';

$stmt = $db->prepare($query);

// Clean data
$id = htmlspecialchars(strip_tags($data->id));
$name = htmlspecialchars(strip_tags($data->name));
$phone_number = htmlspecialchars(strip_tags($data->phone_number));
$number = htmlspecialchars(strip_tags($data->number));

// Bind data
$stmt->bindParam(':id', $id);
$stmt->bindParam(':name', $name);
$stmt->bindParam(':phone_number', $phone_number);
$stmt->bindParam(':number', $number);

// Check if member exists first
$check_stmt = $db->prepare('SELECT id FROM member WHERE id = ?');
$check_stmt->execute([$id]);
if (!$check_stmt->fetch()) {
        http_response_code(404);
        echo json_encode(
            array('message' => 'Member Not Found', 'response' => 'error')
        );
    return;
}

if($stmt->execute()) {
    // Handle accounts if provided (replace all existing)
    $accounts = [];
    if (!empty($data->accounts) && is_array($data->accounts)) {
        // Delete existing accounts
        $del_stmt = $db->prepare('DELETE FROM member_account_types WHERE member_id = ?');
        $del_stmt->execute([$id]);
        // Insert new accounts
        foreach ($data->accounts as $account_type_id) {
            $insert_acc = $db->prepare('INSERT INTO member_account_types (member_id, account_typeid) VALUES (?, ?)');
            $insert_acc->execute([$id, $account_type_id]);
        }
    }
    // Fetch accounts with joined account_type info
    $accounts_stmt = $db->prepare('SELECT ma.id, ma.member_id, ma.account_typeid, at.name, at.description FROM member_account_types ma JOIN account_type at ON ma.account_typeid = at.id WHERE ma.member_id = ?');
    $accounts_stmt->execute([$id]);
    while ($acc_row = $accounts_stmt->fetch(PDO::FETCH_ASSOC)) {
        $accounts[] = [
            'id' => $acc_row['id'],
            'member_id' => $acc_row['member_id'],
            'account_type' => [
                'id' => $acc_row['account_typeid'],
                'name' => $acc_row['name'],
                'description' => $acc_row['description']
            ]
        ];
    }
    $member = [
        'id' => $id,
        'name' => $name,
        'phone_number' => $phone_number,
        'number' => $number,
        'accounts' => $accounts
    ];
    http_response_code(200);
    echo json_encode([
        'message' => 'Member updated successfully',
        'response' => 'success',
        'data' => $member
    ]);
} else {
    http_response_code(500);
    echo json_encode(
        array('message' => 'Member Not Updated', 'response' => 'error')
    );
} 