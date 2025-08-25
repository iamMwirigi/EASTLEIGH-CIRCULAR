<?php
// required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../../../models/User.php';

$database = new Database();
$db = $database->connect();

$user = new User($db);

$stmt = $user->read();
$num = $stmt->rowCount();

if ($num > 0) {
    $users_arr = array();
    $users_arr["records"] = array();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        // Fetch stage_name and printer_name
        $stage_name = null;
        $printer_name = null;
        
        // Try stage_id first, then fall back to stage column
        $stage_column = !empty($stage_id) ? $stage_id : (!empty($stage) ? $stage : null);
        if ($stage_column) {
            $stage_stmt = $db->prepare('SELECT name FROM stage WHERE id = ? LIMIT 1');
            $stage_stmt->execute([$stage_column]);
            $stage_row = $stage_stmt->fetch(PDO::FETCH_ASSOC);
            if ($stage_row) $stage_name = $stage_row['name'];
        }
        
        // Always use printer_id to get the correct printer name from printers table
        if (!empty($printer_id)) {
            $printer_stmt = $db->prepare('SELECT name FROM printers WHERE id = ? LIMIT 1');
            $printer_stmt->execute([$printer_id]);
            $printer_row = $printer_stmt->fetch(PDO::FETCH_ASSOC);
            if ($printer_row) {
                $printer_name = $printer_row['name'];
            } else {
                // If printer not found, set printer_name to null but keep printer_id
                $printer_name = null;
            }
        } else {
            // If no printer_id, set both to null
            $printer_id = null;
            $printer_name = null;
        }
        
        $user_item = array(
            "id" => $id,
            "username" => $username,
            "password" => $password,
            "name" => $name,
            "stage_id" => $stage_id,
            "stage_name" => $stage_name,
            "phone_number" => $phone_number,
            "prefix" => $prefix,
            "printer_id" => $printer_id,
            "printer_name" => $printer_name
        );
        array_push($users_arr["records"], $user_item);
    }
    http_response_code(200);
    echo json_encode($users_arr);
} else {
    http_response_code(404);
    echo json_encode(
        array("message" => "No users found.")
    );
} 