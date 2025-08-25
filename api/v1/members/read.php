<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

$userData = authorize(['admin', 'user']);

$database = new Database();
$db = $database->connect();

if($db === null) {
    http_response_code(503);
    echo json_encode(['message' => 'Failed to connect to the database.', 'response' => 'error']);
    exit();
}

$query = 'SELECT id, name, phone_number, number FROM member ORDER BY name ASC';

$stmt = $db->prepare($query);
$stmt->execute();

$num = $stmt->rowCount();

if($num > 0) {
    $members_arr = array();
    $members_arr['data'] = array();

    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        // Fetch accounts for this member
        $accounts_stmt = $db->prepare('SELECT ma.id, ma.member_id, ma.account_typeid, at.name, at.description FROM member_account_types ma JOIN account_type at ON ma.account_typeid = at.id WHERE ma.member_id = ?');
        $accounts_stmt->execute([$id]);
        $accounts = [];
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
        $member_item = array(
            'id' => $id,
            'name' => $name,
            'phone_number' => $phone_number,
            'number' => $number,
            'accounts' => $accounts
        );
        array_push($members_arr['data'], $member_item);
    }
    $members_arr['message'] = 'Members retrieved successfully';
    $members_arr['response'] = 'success';
    echo json_encode($members_arr);
} else {
    echo json_encode(
        array('message' => 'No Members Found', 'response' => 'success', 'data' => [])
    );
} 