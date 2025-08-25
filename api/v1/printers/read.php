<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

$userData = authorize(['admin', 'user', 'member']);

$database = new Database();
$db = $database->connect();

if ($userData->role === 'admin') {
    // Admin: see all printers
    $stmt = $db->prepare('SELECT id, name FROM printers ORDER BY id ASC');
    $stmt->execute();
    $printers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["printers" => $printers]);
} else if ($userData->role === 'user') {
    // User: see only their assigned printer
    $stmt = $db->prepare('SELECT printer_id FROM _user_ WHERE id = ?');
    $stmt->execute([$userData->id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['printer_id']) {
        $printer_stmt = $db->prepare('SELECT id, name FROM printers WHERE id = ?');
        $printer_stmt->execute([$row['printer_id']]);
        $printer = $printer_stmt->fetch(PDO::FETCH_ASSOC);
        $printers = $printer ? [$printer] : [];
        echo json_encode(["printers" => $printers]);
    } else {
        echo json_encode(["printers" => []]);
    }
} else {
    // Member: see no printers
    echo json_encode(["printers" => []]);
} 