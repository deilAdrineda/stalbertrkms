<?php
include '../../includes/connection.php';
include '../../includes/session_check.php';
requireRole(['Administrator','Receptionist','Laboratory Personnel', 'Ultrasound Personnel', '2D Echo Personnel', 'ECG Personnel', 'X-RAY Personnel']);

$search    = isset($_POST['search']) ? trim($_POST['search']) : '';
$status    = isset($_POST['status']) ? $_POST['status'] : 'all';
$sort      = isset($_POST['sort']) ? $_POST['sort'] : 'date_desc';
$dateFrom  = isset($_POST['dateFrom']) ? $_POST['dateFrom'] : '';
$dateTo    = isset($_POST['dateTo']) ? $_POST['dateTo'] : '';

// ===== Order By =====
switch ($sort) {
    case 'date_asc':   $orderBy = "st.actioned_on ASC"; break;
    case 'name_asc':   $orderBy = "ui.user_fname ASC, ui.user_lname ASC"; break;
    case 'name_desc':  $orderBy = "ui.user_fname DESC, ui.user_lname DESC"; break;
    default:           $orderBy = "st.actioned_on DESC"; break;
}

// ===== Build WHERE conditions =====
$filters = [];
$params = [];
$types = '';

// Search by full name OR case_no OR status
if ($search !== '') {
    $filters[] = "(CONCAT(ui.user_fname, ' ', ui.user_lname) LIKE ? OR psa.case_no LIKE ? OR st.status LIKE ? OR r.role_name LIKE ?)";
    $wild = "%$search%";
    $params[] = $wild; $params[] = $wild; $params[] = $wild; $params[] = $wild;
    $types .= 'ssss';
}
// Status filter (except "all")
if ($status !== "" && $status !== "all") {
    $filters[] = "st.status = ?";
    $params[] = $status;
    $types .= 's';
}
// Filter by date range
if ($dateFrom !== '') {
    $filters[] = "DATE(st.actioned_on) >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}
if ($dateTo !== '') {
    $filters[] = "DATE(st.actioned_on) <= ?";
    $params[] = $dateTo;
    $types .= 's';
}
$where = $filters ? ("WHERE ".implode(" AND ", $filters)) : '';

// ===== Prepared Query for Security and Search =====
$query = "
  SELECT st.*, CONCAT(ui.user_fname, ' ', ui.user_lname) AS full_name, psa.case_no, r.role_name
  FROM service_task st
  JOIN user_account ua ON st.user_account_id = ua.user_account_id
  JOIN user_info ui ON ua.user_info_id = ui.user_info_id
  JOIN roles r ON ua.role_id = r.role_id
  LEFT JOIN patient_service_avail psa ON st.avail_id = psa.avail_id
  $where
  ORDER BY $orderBy
  LIMIT 100
";

// Use prepared statement if there are param filters, fallback to direct query
if ($params) {
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($con, $query);
}

// ===== Output Table Rows =====
$existResult = false;
while ($row = mysqli_fetch_assoc($result)) {
    $existResult = true;
    $name = htmlspecialchars($row['full_name']) ?: 'Unknown';
    $activity = "Set status to <strong>" . htmlspecialchars($row['status']) . "</strong>";
    $caseNo = htmlspecialchars($row['case_no'] ?? "#" . $row['avail_id']);
    $date = date("M d, Y H:i:s", strtotime($row['actioned_on']));
    $roleName = htmlspecialchars($row['role_name']) ?: 'Unknown';
    echo "<tr>
        <td>{$roleName}</td>
        <td>{$name}</td>
        <td>{$activity}</td>
        <td>{$caseNo}</td>
        <td>{$date}</td>
    </tr>";
}
if (!$existResult) {
    if ($search !== '') {
        echo "<tr><td colspan='4' style='text-align:center; padding: 30px; color: #888;'>
                <i class='fas fa-search' style='font-size: 48px; opacity: 0.3; display: block; margin-bottom: 10px;'></i>
                No results found for <b>" . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . "</b>.
              </td></tr>";
    } else {
        echo "<tr><td colspan='4' style='text-align:center; color: #888;'>No logs found.</td></tr>";
    }
}
?>
