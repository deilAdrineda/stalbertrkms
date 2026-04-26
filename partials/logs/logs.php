<?php
include '../../includes/session_check.php';
requireRole(['Administrator','Receptionist','Laboratory Personnel', 'Ultrasound Personnel', '2D Echo Personnel', 'ECG Personnel', 'X-RAY Personnel']);
$con = $con ?? null;

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $search   = trim($_POST['search'] ?? '');
    $status   = $_POST['status'] ?? '';
    $dateFrom = $_POST['dateFrom'] ?? '';
    $dateTo   = $_POST['dateTo'] ?? '';
    $sort     = $_POST['sort'] ?? 'date_desc';

    $filters = [];
    // Search by full name, case_no, or status (LIKE)
    if ($search) {
        $searchEsc = mysqli_real_escape_string($con, $search);
        $filters[] = "(CONCAT(ui.user_fname, ' ', ui.user_lname) LIKE '%$searchEsc%' 
                       OR psa.case_no LIKE '%$searchEsc%' 
                       OR st.status LIKE '%$searchEsc%')";
    }
    // Filter by status only if not "all"
    if ($status && $status !== "all") {
        $statusEsc = mysqli_real_escape_string($con, $status);
        $filters[] = "st.status = '$statusEsc'";
    }
    if ($dateFrom) {
        $dateFromEsc = mysqli_real_escape_string($con, $dateFrom);
        $filters[] = "DATE(st.actioned_on) >= '$dateFromEsc'";
    }
    if ($dateTo) {
        $dateToEsc = mysqli_real_escape_string($con, $dateTo);
        $filters[] = "DATE(st.actioned_on) <= '$dateToEsc'";
    }
    $where = $filters ? ("WHERE ".implode(' AND ', $filters)) : '';

    // Sorting
    switch ($sort) {
        case 'date_asc':   $orderBy = "st.actioned_on ASC"; break;
        case 'name_asc':   $orderBy = "ui.user_fname ASC, ui.user_lname ASC"; break;
        case 'name_desc':  $orderBy = "ui.user_fname DESC, ui.user_lname DESC"; break;
        default:           $orderBy = "st.actioned_on DESC"; break;
    }

    $q = mysqli_query($con, "
    SELECT st.*, CONCAT(ui.user_fname, ' ', ui.user_lname) AS full_name, psa.case_no, r.role_name
    FROM service_task st
    JOIN user_account ua ON st.user_account_id = ua.user_account_id
    JOIN user_info ui ON ua.user_info_id = ui.user_info_id
    JOIN roles r ON ua.role_id = r.role_id
    LEFT JOIN patient_service_avail psa ON st.avail_id = psa.avail_id
    $where
    ORDER BY $orderBy
    LIMIT 100
");


    if ($q && mysqli_num_rows($q) > 0) {
        while ($log = mysqli_fetch_assoc($q)) {
            $name = htmlspecialchars($log['full_name']) ?: 'Unknown';
            $activity = "Set status to <strong>".htmlspecialchars($log['status'])."</strong>";
            $caseNo = htmlspecialchars($log['case_no'] ?? "#".$log['avail_id']);
            $date = date("M d, Y H:i:s", strtotime($log['actioned_on']));
            echo "<tr>
                <td>{$name}</td>
                <td>{$activity}</td>
                <td>{$caseNo}</td>
                <td>{$date}</td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='4' style='text-align:center;color:#888;'>No logs found.</td></tr>";
    }
    exit;
}
?>

<div class="page-title">
    <div class="title">Logs</div>
</div>

<div class="filter-bar">
  <div class="filter-daterange-group">
    <label>Date</label>
    <select id="logDatePreset">
      <option value="today">Today</option>
      <option value="yesterday">Yesterday</option>
      <option value="last7">Last 7 Days</option>
      <option value="last30">Last 30 Days</option>
      <option value="custom">Custom</option>
    </select>
    <input type="date" id="logDateFrom" style="display:none;">
    <input type="date" id="logDateTo" style="display:none;">
  </div>

  <div class="filter-sort-group">
    <label>Sort</label>
    <select id="logSortFilter">
      <option value="date_desc" selected>Newest First</option>
      <option value="date_asc">Oldest First</option>
      <option value="name_asc">A-Z</option>
      <option value="name_desc">Z-A</option>
    </select>
  </div>

  <div class="filter-status-group">
    <label>Status</label>
    <select id="logStatusDropdown">
      <option value="all" selected>All</option>
      <option value="Pending">Pending</option>
      <option value="Completed">Completed</option>
      <option value="Canceled">Canceled</option>
    </select>
  </div>

  <div class ="search-bar">
        <i class ="fas fa-search"></i>
        <input type="text" id="logSearch" placeholder="Name, activity, case no..." />
    </div>
</div>


<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Role</th>
                <th>Name</th>
                <th>Activity</th>
                <th>Case No.</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
<?php
$q = mysqli_query($con, "
    SELECT st.*, CONCAT(ui.user_fname, ' ', ui.user_lname) AS full_name, psa.case_no, r.role_name
    FROM service_task st
    JOIN user_account ua ON st.user_account_id = ua.user_account_id
    JOIN user_info ui ON ua.user_info_id = ui.user_info_id
    JOIN roles r ON ua.role_id = r.role_id
    LEFT JOIN patient_service_avail psa ON st.avail_id = psa.avail_id
    ORDER BY st.actioned_on DESC
    LIMIT 100
");
if ($q && mysqli_num_rows($q) > 0) {
    while ($log = mysqli_fetch_assoc($q)) {
        $name = htmlspecialchars($log['full_name']) ?: 'Unknown';
        $activity = "Set status to <strong>".htmlspecialchars($log['status'])."</strong>";
        $caseNo = htmlspecialchars($log['case_no']) ? htmlspecialchars($log['case_no']) : "#".$log['avail_id'];
        $date = date("M d, Y H:i:s", strtotime($log['actioned_on']));
        $roleName = htmlspecialchars($log['role_name']) ?: 'Unknown';
        echo "<tr>
            <td>{$roleName}</td>
            <td>{$name}</td>
            <td>{$activity}</td>
            <td>{$caseNo}</td>
            <td>{$date}</td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='4' style='text-align:center;color:#888;'>No logs found.</td></tr>";
}
?>
        </tbody>
    </table>
</div>