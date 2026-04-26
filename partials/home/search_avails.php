<?php
include '../../includes/session_check.php';

// --- MODAL AJAX by avail_id ---
if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['ajax_get_avail_info'])) {
    $avail_id = intval($_POST['avail_id'] ?? 0);
    if ($avail_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        exit;
    }
    // Service Info
    $svc = mysqli_query($con, "SELECT psa.*, cst.service_name, pi.* 
        FROM patient_service_avail psa
        JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
        JOIN patient_info_tbl pi ON psa.patient_id = pi.patient_id
        WHERE psa.avail_id = $avail_id");
    $service = mysqli_fetch_assoc($svc);

    // BILLING
    $billing = [];
    $billing_q = mysqli_query($con, "
        SELECT * FROM billing_tbl WHERE avail_id = $avail_id LIMIT 1
    ");
    // $billing = mysqli_fetch_assoc($billing_q);
    // $billing_q = mysqli_query($con, "
    //     SELECT b.*, d.discount_name 
    //     FROM billing_tbl b
    //     LEFT JOIN discount_tbl d ON b.discount_value = d.discount_value 
    //     WHERE b.avail_id = $avail_id LIMIT 1
    // ");
    if ($row = mysqli_fetch_assoc($billing_q)) $billing = $row;


    // Procedures
    $procResult = mysqli_query($con,"SELECT psp.*, proc.procedure_name, pg.group_name 
        FROM patient_service_proc psp
        LEFT JOIN procedure_tbl proc ON psp.procedure_id = proc.procedure_id
        LEFT JOIN procedure_group_tbl pg ON psp.group_id = pg.group_id
        WHERE psp.avail_id = $avail_id");
    $procs = [];
    while ($pr = mysqli_fetch_assoc($procResult)) $procs[] = $pr;

    // echo json_encode([
    //     'status' => 'success',
    //     'service' => $service,
    //     'procedures' => $procs
    // ]);

    echo json_encode([
        'status' => 'success',
        'service' => $service,
        'procedures' => $procs,
        'patient' => [
            'name' => $service['patient_fname'] . ' ' . $service['patient_lname'],
            'dob' => $service['patient_dob'],
            'sex' => $service['patient_sex'],
            'phone' => $service['patient_phone'],
        ],
        'billing' => $billing
    ]);

    exit;
}

$role = $_SESSION['role'] ?? '';
$role_id = $_SESSION['role_id'] ?? 0;

$where = [];

// --- TABLE FILTER/SORT/SEARCH AJAX ---
$search = isset($_POST['search']) ? trim($_POST['search']) : '';
// $availStatus = $_POST['availStatus'] ?? 'all';
$sort = $_POST['sort'] ?? 'date_desc';

// Restrict by service for personnel only
$personnelRoles = [
    'Laboratory Personnel',
    'Ultrasound Personnel',
    '2D Echo Personnel',
    'ECG Personnel',
    'X-RAY Personnel'
];

if (in_array($role, $personnelRoles)) {
    $service_ids = [];
    $svc_res = mysqli_query($con, "SELECT service_id FROM clinic_service_tbl WHERE role_id = $role_id");
    while ($svc_row = mysqli_fetch_assoc($svc_res)) $service_ids[] = $svc_row['service_id'];
    $service_ids_csv = $service_ids ? implode(',', $service_ids) : '0';
    $where[] = "psa.service_id IN ($service_ids_csv)";
}

// statsu filter version 2
// $statusArr = $_POST['statusArr'] ?? [];
// $dateFrom = $_POST['dateFrom'] ?? '';
// $dateTo = $_POST['dateTo'] ?? '';

// if (!empty($statusArr)) {
//     $safeStatusArr = array_map(function($s) use ($con){
//         return "'" . mysqli_real_escape_string($con, $s) . "'";
//     }, $statusArr);
//     $where[] = "psa.status IN (" . implode(",", $safeStatusArr) . ")";
// }

// if ($dateFrom && $dateTo) {
//     $where[] = "DATE(psa.date_availed) BETWEEN '$dateFrom' AND '$dateTo'";
// }

// status filter version 3
$statusArr = $_POST['statusArr'] ?? [];
if (empty($statusArr)) {
    $statusArr = ['Pending', 'Completed', 'Canceled'];
}

$dateFrom = $_POST['dateFrom'] ?? '';
$dateTo = $_POST['dateTo'] ?? '';
// Defensive: If only one is set, treat as a single day filter
if ($dateFrom && !$dateTo) $dateTo = $dateFrom;
if ($dateTo && !$dateFrom) $dateFrom = $dateTo;
if ($dateFrom && $dateTo) {
    $where[] = "DATE(psa.date_availed) BETWEEN '$dateFrom' AND '$dateTo'";
}

// Always after your personnel service restriction, but before the query is built:
if (!empty($statusArr)) {
    $safeStatusArr = array_map(function($s) use ($con){
        return "'" . mysqli_real_escape_string($con, $s) . "'";
    }, $statusArr);
    $where[] = "psa.status IN (" . implode(",", $safeStatusArr) . ")";
}

// Only today's records (edit as needed!)
// date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');
$params = [];
if ($search !== '') {
    $searchSql = "(CONCAT(pi.patient_fname, ' ', pi.patient_lname) LIKE ? 
        OR pi.patient_fname LIKE ? 
        OR pi.patient_lname LIKE ? 
        OR psa.case_no LIKE ? 
        OR psa.requested_by LIKE ?
        OR cst.service_name LIKE ?
        OR psa.status LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params = array_fill(0, 7, $searchTerm);
    $where[] = $searchSql;
}

// FINAL GUARD: If $where is somehow empty, restrict to today (won't happen with above, but 100% bulletproof!)
if (empty($where)) {
    $today = date('Y-m-d');
    $where[] = "DATE(psa.date_availed) = '$today'";
}

// Sort
switch ($sort) {
    case 'date_asc': $orderBy = "psa.date_availed ASC"; break;
    case 'name_asc': $orderBy = "pi.patient_fname ASC, pi.patient_lname ASC"; break;
    case 'name_desc': $orderBy = "pi.patient_fname DESC, pi.patient_lname DESC"; break;
    default: $orderBy = "psa.date_availed DESC"; break;
}

$whereClause = implode(' AND ', $where);

$query = "
    SELECT pi.patient_id, psa.case_no, pi.patient_fname, pi.patient_lname, 
        psa.requested_by, cst.service_name,
        psa.date_availed, psa.status, psa.avail_id, psa.service_id
    FROM patient_service_avail psa
    JOIN patient_info_tbl pi ON psa.patient_id = pi.patient_id
    JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
    WHERE $whereClause
    ORDER BY $orderBy
";
// WHERE $statusCondition AND $dateCondition $searchSql

if (count($params) > 0) {
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, str_repeat("s", count($params)), ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($con, $query);
}

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>
            <td>" . htmlspecialchars($row['case_no']) . "</td>
            <td>" . htmlspecialchars($row['patient_fname'] . ' ' . $row['patient_lname']) . "</td>
            <td>" . htmlspecialchars($row['requested_by'] ?? '--') . "</td>
            <td>" . htmlspecialchars($row['service_name']) . "</td>
            <td>" . date("M d, Y H:i", strtotime($row['date_availed'])) . "</td>
            <td>" . htmlspecialchars($row['status']) . "</td>
            <td>
              <button type='button' class='btn btn-outline btn-sm view-avail-btn'
                data-avail_id='" . htmlspecialchars($row['avail_id']) . "'
                data-service_id='" . htmlspecialchars($row['service_id']) . "'>
                <i class='fas fa-eye'></i> View
              </button>
            </td>
          </tr>";
    }
} else {
    if ($search !== '') {
        // If user typed a search keyword, show what was searched
        echo "<tr><td colspan='7' style='text-align:center;'>
            No results found for <b>" . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . "</b>.
            </td></tr>";
    } else {
        // No filter/search, nothing for today
        echo "<tr><td colspan='7' style='text-align:center;color:#888;'>
            No services found for today.
            </td></tr>";
    }
}

?>