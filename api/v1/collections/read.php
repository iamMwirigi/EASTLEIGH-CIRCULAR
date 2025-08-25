<?php
// Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include_once __DIR__ . '/../../../config/Database.php';
include_once __DIR__ . '/../auth/authorize.php';

$userData = authorize(['admin', 'user', 'member']);

$database = new Database();
$db = $database->connect();

if($db === null) {
    http_response_code(503);
    echo json_encode(['message' => 'Failed to connect to the database.', 'response' => 'error']);
    exit();
}

$data = json_decode(file_get_contents("php://input"));
$limit = isset($data->limit) ? (int)$data->limit : null;

$start_date = isset($data->start_date) ? $data->start_date : (isset($_GET['start_date']) ? $_GET['start_date'] : null);
$end_date = isset($data->end_date) ? $data->end_date : (isset($_GET['end_date']) ? $_GET['end_date'] : null);
$stage_name = isset($data->stage_name) ? $data->stage_name : (isset($_GET['stage_name']) ? $_GET['stage_name'] : null);
$account_type = isset($data->account_type) ? $data->account_type : (isset($_GET['account_type']) ? $_GET['account_type'] : null);
$receipt_number = isset($data->receipt_number) ? $data->receipt_number : (isset($_GET['receipt_number']) ? $_GET['receipt_number'] : null);

$where_clauses = [];
$params = [];
$source_data = [];

if ($userData->role === 'member') {
    // For members, get collections for vehicles they own
    $query = 'SELECT t.* FROM new_transaction t JOIN vehicle v ON t.number_plate = v.number_plate WHERE v.owner = :owner';
    $params[':owner'] = $userData->id;
    if ($start_date) {
        $where_clauses[] = "t.t_date >= :start_date";
        $params[':start_date'] = $start_date;
    }
    if ($end_date) {
        $where_clauses[] = "t.t_date <= :end_date";
        $params[':end_date'] = $end_date;
    }
    if ($stage_name) {
        $where_clauses[] = "t.stage_name = :stage_name";
        $params[':stage_name'] = $stage_name;
    }
    if ($receipt_number) {
        $where_clauses[] = "t.receipt_no = :receipt_number";
        $params[':receipt_number'] = $receipt_number;
    }
    if (count($where_clauses) > 0) {
        $query .= ' AND ' . implode(' AND ', $where_clauses);
    }
    $query .= ' ORDER BY t.t_date DESC, t.t_time DESC, t.id DESC';
    if ($limit) {
        $query .= ' LIMIT ' . $limit;
    }
    $stmt_member = $db->prepare($query);
    $stmt_member->execute($params);
    $source_data = $stmt_member->fetchAll(PDO::FETCH_ASSOC);
} else {
    // For admin/user roles
    if ($account_type === 'operations') {
        // Special logic: Get all vehicles and LEFT JOIN their collections for the date range
        // to see who paid and who defaulted on operations.
        // 1. Get all vehicles from the company
        $vehicle_stmt = $db->prepare('SELECT number_plate FROM vehicle ORDER BY number_plate ASC');
        $vehicle_stmt->execute();
        $all_vehicle_plates = $vehicle_stmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. Build query to get collections within the date range, applying filters
        $collection_params = [];
        $collection_where = [];
        if ($start_date) {
            $collection_where[] = "t_date >= :start_date";
            $collection_params[':start_date'] = $start_date;
        }
        if ($end_date) {
            $collection_where[] = "t_date <= :end_date";
            $collection_params[':end_date'] = $end_date;
        }
        if ($userData->role === 'user') {
            $collection_where[] = "collected_by = :username";
            $collection_params[':username'] = $userData->username;
        }
        if ($stage_name) {
            $collection_where[] = "stage_name = :stage_name";
            $collection_params[':stage_name'] = $stage_name;
        }
        if ($receipt_number) {
            $collection_where[] = "receipt_no = :receipt_number";
            $collection_params[':receipt_number'] = $receipt_number;
        }

        $where_sql = !empty($collection_where) ? 'WHERE ' . implode(' AND ', $collection_where) : '';
        // Fetch individual transactions to aggregate collectors in PHP
        $collection_query = "SELECT number_plate, operations, collected_by, t_date FROM new_transaction $where_sql";
        $collection_stmt = $db->prepare($collection_query);
        $collection_stmt->execute($collection_params);
        $operations_transactions = $collection_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Aggregate transactions by number plate in PHP
        $aggregated_collections = [];
        foreach ($operations_transactions as $tx) {
            $plate = $tx['number_plate'];
            if (!isset($aggregated_collections[$plate])) {
                $aggregated_collections[$plate] = [
                    'total_operations' => 0,
                    'collectors' => [], // This will be an associative array [lowercase_name => original_name]
                    'latest_date' => '0000-00-00'
                ];
            }
            $aggregated_collections[$plate]['total_operations'] += (float)$tx['operations'];
            
            if ($tx['t_date'] > $aggregated_collections[$plate]['latest_date']) {
                $aggregated_collections[$plate]['latest_date'] = $tx['t_date'];
            }

            $collector_name = trim($tx['collected_by']);
            if (!empty($collector_name)) {
                // Standardize the name format (e.g., "Billy", not "billy" or "BILLY")
                $standardized_name = ucwords(strtolower($collector_name));
                $lower_case_key = strtolower($collector_name);
                $aggregated_collections[$plate]['collectors'][$lower_case_key] = $standardized_name;
            }
        }

        // 3. Create the final list, merging all vehicles with their payments
        $today = date('Y-m-d');
        foreach ($all_vehicle_plates as $plate) {
            $operations_paid = isset($aggregated_collections[$plate]) ? $aggregated_collections[$plate]['total_operations'] : 0;
            $collectors = isset($aggregated_collections[$plate]) ? implode(', ', array_values($aggregated_collections[$plate]['collectors'])) : null;
            $latest_date = isset($aggregated_collections[$plate]['latest_date']) ? $aggregated_collections[$plate]['latest_date'] : null;
            if ($collectors === '') $collectors = null;
            
            $source_data[] = [
                'id' => null,
                'number_plate' => $plate,
                't_time' => null,
                't_date' => $start_date ?? $today,
                's_time' => null,
                's_date' => null,
                'client_side_id' => null,
                'receipt_no' => null,
                'collected_by' => $collectors,
                'stage_name' => null,
                'delete_status' => 0,
                'for_date' => $start_date ?? $today,
                'operations' => $operations_paid,
                'loans' => 0,
                'county' => 0,
                'savings' => 0,
                'insurance' => 0,
                'amount' => $operations_paid,
                '_sort_date' => $latest_date // Temporary key for sorting
            ];
        }

        // Sort the data: paid vehicles first, sorted by most recent transaction, then non-paying vehicles sorted by number plate.
        usort($source_data, function($a, $b) {
            $a_paid = $a['operations'] > 0;
            $b_paid = $b['operations'] > 0;

            // If one paid and the other didn't, the one that paid comes first.
            if ($a_paid !== $b_paid) {
                return $a_paid ? -1 : 1;
            }

            // If both paid, sort by latest transaction date descending.
            if ($a_paid && $b_paid) {
                // If dates are the same, sort by number plate as a secondary criterion
                if ($a['_sort_date'] === $b['_sort_date']) {
                    return strcmp($a['number_plate'], $b['number_plate']);
                }
                return strcmp($b['_sort_date'], $a['_sort_date']);
            }

            // If neither paid, sort by number plate ascending.
            return strcmp($a['number_plate'], $b['number_plate']);
        });

        // Remove the temporary sort key before final processing
        array_walk($source_data, function(&$row) { unset($row['_sort_date']); });

    } else {
        // Original logic for non-operations or no account_type filter
        $query = 'SELECT * FROM new_transaction';
        if ($userData->role === 'user') {
            $where_clauses[] = "collected_by = :username";
            $params[':username'] = $userData->username;
        } else if ($stage_name) {
            $where_clauses[] = "stage_name = :stage_name";
            $params[':stage_name'] = $stage_name;
        }
        if ($start_date) {
            $where_clauses[] = "t_date >= :start_date";
            $params[':start_date'] = $start_date;
        }
        if ($end_date) {
            $where_clauses[] = "t_date <= :end_date";
            $params[':end_date'] = $end_date;
        }
        if ($receipt_number) {
            $where_clauses[] = "receipt_no = :receipt_number";
            $params[':receipt_number'] = $receipt_number;
        }
        if (count($where_clauses) > 0) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        $query .= ' ORDER BY t_date DESC, t_time DESC, id DESC';
        $stmt_default = $db->prepare($query);
        $stmt_default->execute($params);
        $source_data = $stmt_default->fetchAll(PDO::FETCH_ASSOC);
    }
}

$num = count($source_data);

if($num > 0) {
    $transactions_arr = array();
    $transactions_arr['data'] = array();

    // Initialize totals
    $totals = [
        'total_operations' => 0,
        'total_loans' => 0,
        'total_county' => 0,
        'total_savings' => 0,
        'total_insurance' => 0,
        'total_amount' => 0,
        'transactions' => 0
    ];

    $filtered_rows = [];
    $deduction_fields = ['operations', 'loans', 'county', 'savings', 'insurance'];

    foreach ($source_data as $row) {
        // Ensure all deduction fields are present and are numeric (int for whole numbers)
        foreach ($deduction_fields as $field) {
            $value = isset($row[$field]) ? (float)$row[$field] : 0;
            // If it's a whole number, cast to int, otherwise keep as float.
            // This ensures 0 is output as 0, not 0.0.
            if ($value == (int)$value) {
                $row[$field] = (int)$value;
            } else {
                $row[$field] = $value;
            }
        }
        // Recalculate amount to ensure consistency
        $row['amount'] = array_sum(array_intersect_key($row, array_flip($deduction_fields)));

        if ($account_type) {
            // When filtering by account type, we always include the row.
            // We just format it to show only the relevant data.
            $account_amount = (float)($row[$account_type] ?? 0);
            
            // Remove other account type fields from the response
            $account_types = ['operations', 'loans', 'county', 'savings', 'insurance'];
            foreach ($deduction_fields as $type) {
                if ($type !== $account_type) {
                    unset($row[$type]);
                }
            }
            
            // Update the total amount to only include the filtered account type
            $row['amount'] = $account_amount;
            $filtered_rows[] = $row;
        } else {
            // Original behavior: Only include collections where at least one deduction is non-zero
            if ($row['amount'] > 0) {
                $filtered_rows[] = $row;
            }
        }
    }
    // Apply the limit AFTER filtering
    if ($limit !== null && $limit > 0) {
        $filtered_rows = array_slice($filtered_rows, 0, $limit);
    }
    // Now calculate the summary only from $filtered_rows
    $vehicle_operations_status = []; // To track unique vehicles and their payment status
    foreach ($filtered_rows as $row) {
        // If the final displayed amount is zero, it's confusing to show who "collected" it.
        // To improve clarity, we'll clear the collector's name for such records.
        // This is done here to correctly handle cases where an account_type filter is applied.
        if (isset($row['amount']) && (float)$row['amount'] == 0) {
            $row['collected_by'] = '';
        }

        $transactions_arr['data'][] = $row;
        
        // If filtering by account type, only count that specific account type
        if ($account_type) {
            $totals['total_' . $account_type] += (float)($row[$account_type] ?? 0);
            $totals['total_amount'] += (float)($row['amount'] ?? 0);
        } else {
            // Count all account types if no filtering
            $totals['total_operations'] += (float)($row['operations'] ?? 0);
            $totals['total_loans'] += (float)($row['loans'] ?? 0);
            $totals['total_county'] += (float)($row['county'] ?? 0);
            $totals['total_savings'] += (float)($row['savings'] ?? 0);
            $totals['total_insurance'] += (float)($row['insurance'] ?? 0);
            $totals['total_amount'] += (float)($row['amount'] ?? 0);
        }
        $totals['transactions']++;

        // Track vehicle payment status for 'operations'
        $number_plate = $row['number_plate'];
        $has_operations = isset($row['operations']) && (float)$row['operations'] > 0;

        if ($has_operations) {
            $vehicle_operations_status[$number_plate] = 'paid';
        } else {
            // Only mark as default if not already marked as paid
            if (!isset($vehicle_operations_status[$number_plate])) {
                $vehicle_operations_status[$number_plate] = 'default';
            }
        }
    }
    $status_counts = array_count_values($vehicle_operations_status);
    $totals['vehicles_paid'] = $status_counts['paid'] ?? 0;
    $totals['vehicles_in_default'] = $status_counts['default'] ?? 0;

    // Ensure summary totals are integers if they are whole numbers
    foreach ($totals as $key => &$value) {
        if (is_numeric($value) && $value == (int)$value) {
            $value = (int)$value;
        }
    }
    unset($value);
    // If filtering by account type, only show that account type in summary
    if ($account_type) {
        $filtered_summary = [
            'total_' . $account_type => $totals['total_' . $account_type],
            'total_amount' => $totals['total_amount'],
            'transactions' => $totals['transactions'],
            'vehicles_paid' => $totals['vehicles_paid'],
            'vehicles_in_default' => $totals['vehicles_in_default']
        ];
        $transactions_arr['summary'] = $filtered_summary;
    } else {
        $transactions_arr['summary'] = $totals;
    }
    $transactions_arr['message'] = 'Collections retrieved successfully';
    $transactions_arr['response'] = 'success';

    // Fetch company name and contacts from organization_details
    $org_stmt = $db->prepare('SELECT name, contacts FROM organization_details ORDER BY id ASC LIMIT 1');
    $org_stmt->execute();
    $org = $org_stmt->fetch(PDO::FETCH_ASSOC);
    $company_name = $org ? $org['name'] : null;
    $company_contacts = $org ? $org['contacts'] : null;

    // Add company_name and contacts to each collection in the data array
    foreach ($transactions_arr['data'] as &$collection) {
        $collection['company_name'] = $company_name;
        $collection['company_contacts'] = $company_contacts;
    }
    unset($collection);
    $transactions_arr['company_name'] = $company_name;
    $transactions_arr['company_contacts'] = $company_contacts;

    echo json_encode($transactions_arr);
} else {
    // Fetch company name and contacts from organization_details
    $org_stmt = $db->prepare('SELECT name, contacts FROM organization_details ORDER BY id ASC LIMIT 1');
    $org_stmt->execute();
    $org = $org_stmt->fetch(PDO::FETCH_ASSOC);
    $company_name = $org ? $org['name'] : null;
    $company_contacts = $org ? $org['contacts'] : null;

    echo json_encode(
        array(
            'message' => 'No Collections Found',
            'response' => 'success',
            'data' => [],
            'company_name' => $company_name,
            'company_contacts' => $company_contacts
        )
    );
} 