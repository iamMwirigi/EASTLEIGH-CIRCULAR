<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';

$database = new Database();
$db = $database->connect();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["message" => "Failed to connect to the database.", "response" => "error"]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['id']) || empty($data['member_id']) || empty($data['account_type_id'])) {
    http_response_code(400);
    echo json_encode(["message" => "'id', 'member_id', and 'account_type_id' are required.", "response" => "error"]);
    exit();
}

// First get member details
$member_stmt = $db->prepare('SELECT id, name, phone_number, number FROM member WHERE id = ?');
$member_stmt->execute([$data['member_id']]);
$member = $member_stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    http_response_code(404);
    echo json_encode(["message" => "Member not found.", "response" => "error"]);
    exit();
}

$stmt = $db->prepare('UPDATE member_account_types SET member_id = ?, account_typeid = ? WHERE id = ?');
$stmt->execute([$data['member_id'], $data['account_type_id'], $data['id']]);
$fetch = $db->prepare('SELECT ma.id, ma.member_id, ma.account_typeid, at.name, at.description FROM member_account_types ma JOIN account_type at ON ma.account_typeid = at.id WHERE ma.id = ?');
$fetch->execute([$data['id']]);
$row = $fetch->fetch(PDO::FETCH_ASSOC);
$account = [
    'id' => $row['id'],
    'member_id' => $row['member_id'],
    'account_type' => [
        'id' => $row['account_typeid'],
        'name' => $row['name'],
        'description' => $row['description']
    ]
];

echo json_encode([
    "message" => "Member account updated successfully.",
    "response" => "success",
    "data" => [
        "member" => [
            "id" => (int)$member['id'],
            "name" => $member['name'],
            "phone_number" => $member['phone_number'],
            "number" => (int)$member['number']
        ],
        "account" => $account
    ]
]); 