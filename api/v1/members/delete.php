<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: DELETE');
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

if (empty($data->id)) {
    http_response_code(400);
    echo json_encode(array("message" => "Member ID not provided.", "response" => "error"));
    return;
}
// Fetch member and accounts before deleting
$fetch_member = $db->prepare('SELECT id, name, phone_number, number FROM member WHERE id = ?');
$fetch_member->execute([$data->id]);
$member_row = $fetch_member->fetch(PDO::FETCH_ASSOC);
$accounts = [];
if ($member_row) {
    $accounts_stmt = $db->prepare('SELECT ma.id, ma.member_id, ma.account_typeid, at.name, at.description FROM member_account_types ma JOIN account_type at ON ma.account_typeid = at.id WHERE ma.member_id = ?');
    $accounts_stmt->execute([$data->id]);
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

$query = 'DELETE FROM member WHERE id = :id';

$stmt = $db->prepare($query);

// Clean data
$id = htmlspecialchars(strip_tags($data->id));

// Bind data
$stmt->bindParam(':id', $id);

if($stmt->execute()) {
    if($stmt->rowCount()) {
        $member = $member_row ? [
            'id' => $member_row['id'],
            'name' => $member_row['name'],
            'phone_number' => $member_row['phone_number'],
            'number' => $member_row['number'],
            'accounts' => $accounts
        ] : null;
        http_response_code(200);
        echo json_encode([
            'message' => 'Member deleted successfully',
            'response' => 'success',
            'data' => $member
        ]);
    } else {
        http_response_code(404);
        echo json_encode(
            array('message' => 'Member Not Found', 'response' => 'error')
        );
    }
} else {
    http_response_code(500);
    echo json_encode(
        array('message' => 'Member Not Deleted', 'response' => 'error')
    );
} 