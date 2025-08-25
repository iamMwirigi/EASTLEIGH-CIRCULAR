<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: PATCH');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

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

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'] ?? null;
if (empty($id)) {
    http_response_code(400);
    echo json_encode(["message" => "Member ID must be provided in the request body.", "response" => "error"]);
    exit();
}
// Update fields if provided
if (!empty($data['name'])) {
    $stmt = $db->prepare('UPDATE member SET name = ? WHERE id = ?');
    $stmt->execute([$data['name'], $id]);
}
if (!empty($data['phone_number'])) {
    $stmt = $db->prepare('UPDATE member SET phone_number = ? WHERE id = ?');
    $stmt->execute([$data['phone_number'], $id]);
}
if (!empty($data['number'])) {
    $stmt = $db->prepare('UPDATE member SET number = ? WHERE id = ?');
    $stmt->execute([$data['number'], $id]);
}
// Handle accounts
if (!empty($data['accounts']) && is_array($data['accounts'])) {
    if (!empty($data['accounts']['add']) && is_array($data['accounts']['add'])) {
        foreach ($data['accounts']['add'] as $account_type_id) {
            $insert_acc = $db->prepare('INSERT IGNORE INTO member_account_types (member_id, account_typeid) VALUES (?, ?)');
            $insert_acc->execute([$id, $account_type_id]);
        }
    }
    if (!empty($data['accounts']['remove']) && is_array($data['accounts']['remove'])) {
        foreach ($data['accounts']['remove'] as $account_type_id) {
            $del_acc = $db->prepare('DELETE FROM member_account_types WHERE member_id = ? AND account_typeid = ?');
            $del_acc->execute([$id, $account_type_id]);
        }
    }
}
// Fetch member
$fetch_member = $db->prepare('SELECT id, name, phone_number, number FROM member WHERE id = ?');
$fetch_member->execute([$id]);
$member_row = $fetch_member->fetch(PDO::FETCH_ASSOC);
$accounts = [];
if ($member_row) {
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
}
$member = $member_row ? [
    'id' => $member_row['id'],
    'name' => $member_row['name'],
    'phone_number' => $member_row['phone_number'],
    'number' => $member_row['number'],
    'accounts' => $accounts
] : null;
http_response_code(200);
echo json_encode([
    'message' => 'Member updated successfully',
    'response' => 'success',
    'data' => $member
]); 