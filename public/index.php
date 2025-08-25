<?php
// Enable error reporting for local development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// This will be our simple router.
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Remove query string from URI
$base_uri = strtok($request_uri, '?');

// Dynamically determine the base path to handle running in a subdirectory.
// This makes the routing logic independent of the deployment folder.
$base_path = dirname(dirname($_SERVER['SCRIPT_NAME']));
if ($base_path === '/' || $base_path === '\\') {
    $base_path = '';
}

// Remove the base path from the URI
if ($base_path && strpos($base_uri, $base_path) === 0) {
    $uri = substr($base_uri, strlen($base_path));
} else {
    $uri = $base_uri;
}

$uri = $uri ?: '/';

// Handle pre-flight requests for CORS
if ($request_method == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Simple routing
switch ($uri) {
    case '/':
        echo json_encode([
            "message" => "Welcome to Dix Huit Collections API.",
            "bikegame_endpoints" => [
                "/api/v1/bikegame/start_game" => ["POST (register player and start game: {player_name, bike})"],
                "/api/v1/bikegame/stop_game" => ["POST (stop game: {session_id, time_played})"],
                "/api/v1/bikegame/game_sessions" => ["GET (list sessions)", "POST (add session: {player_id, bike_id, time_played})"],
                "/api/v1/bikegame/players" => ["GET (list players)", "POST (add player: {name})"],
                "/api/v1/bikegame/bikes" => ["GET (list bikes)", "POST (add bike: {name})"]
            ]
        ]);
        break;
    
    // Auth
    case '/api/v1/auth/login':
        require __DIR__ . '/../api/v1/auth/login.php';
        break;
    case '/api/v1/auth/reset_password':
        require __DIR__ . '/../api/v1/auth/reset_password.php';
        break;
    case '/api/v1/auth/member/login':
        require __DIR__ . '/../api/v1/auth/member/login.php';
        break;

    // Dashboard
    case '/api/v1/dashboard/admin':
        require __DIR__ . '/../api/v1/dashboard/admin.php';
        break;
    case '/api/v1/dashboard/member':
        require __DIR__ . '/../api/v1/dashboard/member.php';
        break;
    case '/api/v1/dashboard/user':
        require __DIR__ . '/../api/v1/dashboard/user.php';
        break;

    // Members
    case '/api/v1/members/create':
        require __DIR__ . '/../api/v1/members/create.php';
        break;
    case '/api/v1/members/read':
        require __DIR__ . '/../api/v1/members/read.php';
        break;
    case '/api/v1/members/read_one':
        require __DIR__ . '/../api/v1/members/read_one.php';
        break;
    case '/api/v1/members/update':
        require __DIR__ . '/../api/v1/members/update.php';
        break;
    case '/api/v1/members/delete':
        require __DIR__ . '/../api/v1/members/delete.php';
        break;
    case '/api/v1/members/patch':
        require __DIR__ . '/../api/v1/members/patch.php';
        break;
    case '/api/v1/members/initialize_accounts':
        require __DIR__ . '/../api/v1/members/initialize_accounts.php';
        break;
    case '/api/v1/members/check_balances':
        require __DIR__ . '/../api/v1/members/check_balances.php';
        break;
    case '/debug_member_balances':
        require __DIR__ . '/../debug_member_balances.php';
        break;

    // Vehicles
    case '/api/v1/vehicles/create':
        require __DIR__ . '/../api/v1/vehicles/create.php';
        break;
    case '/api/v1/vehicles/read':
        require __DIR__ . '/../api/v1/vehicles/read.php';
        break;
    case '/api/v1/vehicles/read_one':
        require __DIR__ . '/../api/v1/vehicles/read_one.php';
        break;
    case '/api/v1/vehicles/update':
        require __DIR__ . '/../api/v1/vehicles/update.php';
        break;
    case '/api/v1/vehicles/delete':
        require __DIR__ . '/../api/v1/vehicles/delete.php';
        break;
    case '/api/v1/vehicles/report':
        require __DIR__ . '/../api/v1/vehicles/report.php';
        break;
    case '/api/v1/vehicles/detailed-vehicle-collection-report':
        require __DIR__ . '/../api/v1/vehicles/detailed-vehicle-collection-report.php';
        break;

    // Collections
    case '/api/v1/collections/read':
        require __DIR__ . '/../api/v1/collections/read.php';
        break;
    case '/api/v1/collections/read_one':
        require __DIR__ . '/../api/v1/collections/read_one.php';
        break;
    case '/api/v1/collections/member_collections':
        require __DIR__ . '/../api/v1/collections/member_collections.php';
        break;
    case '/api/v1/collections/create':
        require __DIR__ . '/../api/v1/collections/create.php';
        break;
    case '/api/all_member_collections':
    case '/api/v1/collections/all_members_collections':
        require __DIR__ . '/../api/v1/collections/all_members_collections.php';
        break;
    case '/api/v1/collections/search':
        require __DIR__ . '/../api/v1/collections/search.php';
        break;
    case '/api/v1/collections/reports':
        require __DIR__ . '/../api/v1/collections/reports.php';
        break;
    case '/api/v1/collections/operations_status':
        require __DIR__ . '/../api/v1/collections/operations_status.php';
        break;

    // Stages
    case '/api/v1/stages/create':
        require __DIR__ . '/../api/v1/stages/create.php';
        break;
    case '/api/v1/stages/read':
        require __DIR__ . '/../api/v1/stages/read.php';
        break;
    case '/api/v1/stages/update':
        require __DIR__ . '/../api/v1/stages/update.php';
        break;
    case '/api/v1/stages/delete':
        require __DIR__ . '/../api/v1/stages/delete.php';
        break;

    // SMS
    case '/api/v1/sms/read':
        require __DIR__ . '/../api/v1/sms/read.php';
        break;
    case '/api/v1/sms/summary':
        require __DIR__ . '/../api/v1/sms/summary.php';
        break;
    case '/api/v1/sms/send':
        require __DIR__ . '/../api/v1/sms/send.php';
        break;
    case '/api/v1/sms/members':
        require __DIR__ . '/../api/v1/sms/members.php';
        break;

    // Users
    case '/api/v1/users/create':
        require __DIR__ . '/../api/v1/users/create.php';
        break;

    case '/api/v1/users/read':
        require __DIR__ . '/../api/v1/users/read.php';
        break;

    case '/api/v1/users/read_one':
        require __DIR__ . '/../api/v1/users/read_one.php';
        break;
    
    case '/api/v1/users/update':
        require __DIR__ . '/../api/v1/users/update.php';
        break;
    
    case '/api/v1/users/delete':
        require __DIR__ . '/../api/v1/users/delete.php';
        break;

    // Printers
    case '/api/v1/printers/create':
        require __DIR__ . '/../api/v1/printers/create.php';
        break;
    case '/api/v1/printers/read':
        require __DIR__ . '/../api/v1/printers/read.php';
        break;
    case '/api/v1/printers/update':
        require __DIR__ . '/../api/v1/printers/update.php';
        break;
    case '/api/v1/printers/delete':
        require __DIR__ . '/../api/v1/printers/delete.php';
        break;

    // Bikegame
    case '/api/v1/bikegame/players':
        require __DIR__ . '/../api/v1/bikegame/players.php';
        break;
    case '/api/v1/bikegame/bikes':
        require __DIR__ . '/../api/v1/bikegame/bikes.php';
        break;
    case '/api/v1/bikegame/game_sessions':
        require __DIR__ . '/../api/v1/bikegame/game_sessions.php';
        break;
    case '/api/v1/bikegame/start_game':
        require __DIR__ . '/../api/v1/bikegame/start_game.php';
        break;
    case '/api/v1/bikegame/stop_game':
        require __DIR__ . '/../api/v1/bikegame/stop_game.php';
        break;

    // Account Types
    case '/api/v1/account_types/create':
        require __DIR__ . '/../api/v1/account_types/create.php';
        break;
    case '/api/v1/account_types/read':
        require __DIR__ . '/../api/v1/account_types/read.php';
        break;
    case '/api/v1/account_types/update':
        require __DIR__ . '/../api/v1/account_types/update.php';
        break;
    case '/api/v1/account_types/delete':
        require __DIR__ . '/../api/v1/account_types/delete.php';
        break;

    // Member Accounts
    case '/api/v1/member_accounts/create':
        require __DIR__ . '/../api/v1/member_accounts/create.php';
        break;
    case '/api/v1/member_accounts/read':
        require __DIR__ . '/../api/v1/member_accounts/read.php';
        break;
    case '/api/v1/member_accounts/update':
        require __DIR__ . '/../api/v1/member_accounts/update.php';
        break;
    case '/api/v1/member_accounts/delete':
        require __DIR__ . '/../api/v1/member_accounts/delete.php';
        break;

    // Transactions
    case '/api/v1/transactions/create':
        require __DIR__ . '/../api/v1/transactions/create.php';
        break;
    case '/api/v1/transactions/read':
        require __DIR__ . '/../api/v1/transactions/read.php';
        break;
    case '/api/v1/transactions/update':
        require __DIR__ . '/../api/v1/transactions/update.php';
        break;
    case '/api/v1/transactions/delete':
        require __DIR__ . '/../api/v1/transactions/delete.php';
        break;
    case '/api/v1/transactions/transfer':
        require __DIR__ . '/../api/v1/transactions/transfer.php';
        break;
    case '/api/v1/transactions/member_transactions':
        require __DIR__ . '/../api/v1/transactions/member_transactions.php';
        break;

    // Expenses
    case '/api/v1/expenses/create':
        require __DIR__ . '/../api/v1/expenses/create.php';
        break;
    case '/api/v1/expenses/read':
        require __DIR__ . '/../api/v1/expenses/read.php';
        break;
    case '/api/v1/expenses/read_one':
        require __DIR__ . '/../api/v1/expenses/read_one.php';
        break;
    case '/api/v1/expenses/update':
        require __DIR__ . '/../api/v1/expenses/update.php';
        break;
    case '/api/v1/expenses/delete':
        require __DIR__ . '/../api/v1/expenses/delete.php';
        break;
    case '/api/v1/expenses/receipt':
        require __DIR__ . '/../api/v1/expenses/receipt.php';
        break;

    // Expense Categories
    case '/api/v1/expense_categories/create':
        require __DIR__ . '/../api/v1/expense_categories/create.php';
        break;
    case '/api/v1/expense_categories/read':
        require __DIR__ . '/../api/v1/expense_categories/read.php';
        break;
    case '/api/v1/expense_categories/read_one':
        require __DIR__ . '/../api/v1/expense_categories/read_one.php';
        break;
    case '/api/v1/expense_categories/update':
        require __DIR__ . '/../api/v1/expense_categories/update.php';
        break;
    case '/api/v1/expense_categories/delete':
        require __DIR__ . '/../api/v1/expense_categories/delete.php';
        break;

    // Default
    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found."]);
        break;
} 