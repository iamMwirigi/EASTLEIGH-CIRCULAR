<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

// Allow both admin/user and member
$userData = authorize(['admin', 'user', 'member']);

$database = new Database();
$db = $database->connect();

if ($db === null) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed.", "response" => "error"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

$start_date = isset($data->start_date) ? $data->start_date : (isset($_GET['start_date']) ? $_GET['start_date'] : null);
$end_date = isset($data->end_date) ? $data->end_date : (isset($_GET['end_date']) ? $_GET['end_date'] : null);
$filter_type = isset($data->filter_type) ? $data->filter_type : (isset($_GET['filter_type']) ? $_GET['filter_type'] : null);

// Valid filter types
$valid_filter_types = ['operations', 'loans', 'county', 'savings', 'insurance'];

try {
    // Deduction fields to include
    $deduction_fields = ['operations', 'loans', 'county', 'savings', 'insurance'];
    
    // If filter_type is provided and valid, only include that field
    if ($filter_type && in_array($filter_type, $valid_filter_types)) {
        $deduction_fields = [$filter_type];
    }

    if ($userData->role === 'member') {
        // --- MEMBER DASHBOARD LOGIC ---
        $stmt = $db->prepare('SELECT id, number_plate FROM vehicle WHERE owner = :owner_id');
        $stmt->bindParam(':owner_id', $userData->id);
        $stmt->execute();
        $vehicle_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $vehicle_ids = array_column($vehicle_rows, 'id');
        $number_plates = array_column($vehicle_rows, 'number_plate');

        if (empty($number_plates)) {
            $response_data = [
                'collection' => [],
                'totals' => array_merge(array_fill_keys($deduction_fields, 0), ['grand_total_deductions' => 0]),
                'stages_transactions' => [],
                'totals_summary' => array_merge(array_fill_keys($deduction_fields, 0), ['grand_total_deductions' => 0])
            ];
            http_response_code(200);
            echo json_encode([
                'message' => 'Member collections and deductions retrieved successfully',
                'response' => 'success',
                'data' => $response_data
            ]);
            exit();
        }

        // Prepare per-vehicle aggregation
        $collection = [];
        $totals = array_fill_keys($deduction_fields, 0);
        $totals['grand_total_deductions'] = 0;

        foreach ($vehicle_rows as $vehicle) {
            $query = 'SELECT ' . implode(', ', $deduction_fields) . ' FROM new_transaction WHERE number_plate = :number_plate';
            $params = [':number_plate' => $vehicle['number_plate']];
            if ($start_date) {
                $query .= ' AND t_date >= :start_date';
                $params[':start_date'] = $start_date;
            }
            if ($end_date) {
                $query .= ' AND t_date <= :end_date';
                $params[':end_date'] = $end_date;
            }
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $deductions = array_fill_keys($deduction_fields, 0);
            $total_deductions = 0;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                foreach ($deduction_fields as $field) {
                    $deductions[$field] += is_numeric($row[$field]) ? (float)$row[$field] : 0;
                }
            }
            foreach ($deduction_fields as $field) {
                $total_deductions += $deductions[$field];
                $totals[$field] += $deductions[$field];
            }
            $totals['grand_total_deductions'] += $total_deductions;
            $collection[] = array_merge([
                'vehicle_id' => $vehicle['id'],
                'number_plate' => $vehicle['number_plate'],
                'total_deductions' => $total_deductions
            ], $deductions);
        }

        // --- STAGE TRANSACTIONS LOGIC (MEMBER) ---
        $stage_query = 'SELECT id, t_date as date, stage_name, ' . implode(', ', $deduction_fields) . ' FROM new_transaction WHERE number_plate IN (' . implode(',', array_fill(0, count($number_plates), '?')) . ')';
        $stage_params = $number_plates;
        if ($start_date) {
            $stage_query .= ' AND t_date >= ?';
            $stage_params[] = $start_date;
        }
        if ($end_date) {
            $stage_query .= ' AND t_date <= ?';
            $stage_params[] = $end_date;
        }
        $stage_query .= ' ORDER BY t_date DESC, id DESC';
        $stmt = $db->prepare($stage_query);
        $stmt->execute($stage_params);
        $stages_transactions = [];
        $totals_summary = array_fill_keys($deduction_fields, 0);
        $totals_summary['grand_total_deductions'] = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $total_amount = 0;
            foreach ($deduction_fields as $field) {
                $row[$field] = is_numeric($row[$field]) ? (float)$row[$field] : 0;
                $totals_summary[$field] += $row[$field];
                $total_amount += $row[$field];
            }
            $totals_summary['grand_total_deductions'] += $total_amount;
            $stages_transactions[] = [
                'id' => $row['id'],
                'date' => $row['date'],
                'total_amount' => $total_amount,
                'stage_name' => $row['stage_name'],
                'operations' => $row['operations'],
                'loans' => $row['loans'],
                'county' => $row['county'],
                'savings' => $row['savings'],
                'insurance' => $row['insurance']
            ];
        }
        $response_data = [
            'stages_transactions' => $stages_transactions,
            'totals_summary' => $totals_summary
        ];
        http_response_code(200);
        echo json_encode($response_data);
        exit();
    }
    // --- END MEMBER LOGIC ---

    // --- ADMIN/USER DASHBOARD LOGIC ---
    $stage_query = 'SELECT stage_name, collected_by, '
        . 'SUM(operations) as operations, '
        . 'SUM(loans) as loans, '
        . 'SUM(county) as county, '
        . 'SUM(savings) as savings, '
        . 'SUM(insurance) as insurance '
        . 'FROM new_transaction WHERE 1=1';
    $stage_params = [];
    if ($start_date) {
        $stage_query .= ' AND t_date >= ?';
        $stage_params[] = $start_date;
    }
    if ($end_date) {
        $stage_query .= ' AND t_date <= ?';
        $stage_params[] = $end_date;
    }
    $stage_query .= ' GROUP BY stage_name, collected_by';
    $stmt = $db->prepare($stage_query);
    $stmt->execute($stage_params);
    $stages_transactions = [];
    $totals_summary = array_fill_keys($deduction_fields, 0);
    $totals_summary['grand_total_deductions'] = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stage_total = 0;
        $stage_obj = [
            'stage_name' => $row['stage_name'],
            'collected_by' => $row['collected_by']
        ];
        foreach ($deduction_fields as $field) {
            $row[$field] = is_numeric($row[$field]) ? (float)$row[$field] : 0;
            $stage_obj[$field] = $row[$field];
            $totals_summary[$field] += $row[$field];
            $stage_total += $row[$field];
        }
        $stage_obj['grand_total_deductions'] = $stage_total;
        
        // Only include if there's actual data (not all zeros)
        if ($stage_total > 0) {
            $stages_transactions[] = $stage_obj;
            $totals_summary['grand_total_deductions'] += $stage_total;
        }
    }
    $response_data = [
        'stages_transactions' => $stages_transactions,
        'totals_summary' => $totals_summary
    ];
    
    // If filtering by a specific type, add filter info to response
    if ($filter_type && in_array($filter_type, $valid_filter_types)) {
        $response_data['filter_type'] = $filter_type;
        $response_data['filtered_total'] = $totals_summary[$filter_type];
    }
    
    http_response_code(200);
    echo json_encode($response_data);
    // --- END ADMIN/USER LOGIC ---

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Error: " . $e->getMessage(), "response" => "error"]);
} 