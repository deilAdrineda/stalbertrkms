<?php
include '../../includes/session_check.php';
requireRole(['Laboratory Personnel', 'Ultrasound Personnel', '2D Echo Personnel', 'ECG Personnel', 'X-RAY Personnel']);


if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['ajax_set_avail_status'])) {
    header('Content-Type: application/json; charset=utf-8');
    $avail_id = intval($_POST['avail_id'] ?? 0);
    $service_id = intval($_POST['service_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $username = $_SESSION['username'] ?? '';
    $user_q = mysqli_query($con, "SELECT user_account_id FROM user_account WHERE username = '$username' LIMIT 1");
    $user_row = mysqli_fetch_assoc($user_q);
    $user_account_id = $user_row['user_account_id'] ?? 0;

    $now = date('Y-m-d H:i:s');
   // Only allow setting to Pending if date_availed is today's date
    if ($status === 'Pending') {
        $check = mysqli_query($con, "SELECT DATE(date_availed) as d FROM patient_service_avail WHERE avail_id = $avail_id");
        $row = mysqli_fetch_assoc($check);
        $avail_date = $row['d'] ?? '';
        $today = date('Y-m-d');
        if ($avail_date !== $today) {
            echo json_encode(['status'=>'error','message'=>'You may only revert to Pending on the day it was availed.']);
            exit;
        }
    }

    // Update the service_task first
    // $sql = "UPDATE service_task SET status = ?, actioned_on = ? WHERE avail_id = ? AND service_id = ? AND user_account_id = ?";
    // $stmt = mysqli_prepare($con, $sql);
    // mysqli_stmt_bind_param($stmt, 'ssiii', $status, $now, $avail_id, $service_id, $user_account_id);
    // mysqli_stmt_execute($stmt);
    // mysqli_stmt_close($stmt);

    $sql = "INSERT INTO service_task (avail_id, service_id, user_account_id, status, actioned_on) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'iiiss', $avail_id, $service_id, $user_account_id, $status, $now);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);


    // Update the main avail record as well
    mysqli_query($con, "UPDATE patient_service_avail SET status = '$status' WHERE avail_id = $avail_id");

    echo json_encode(['status'=>'success','message'=>"Task marked as $status"]);
    exit;
}


  if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['ajax_get_avail_info'])) {
    $avail_id = intval($_POST['avail_id'] ?? 0);
    if ($avail_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        exit;
    }
    // Service info
    $svc = mysqli_query($con, "SELECT psa.*, cst.service_name, pi.* 
        FROM patient_service_avail psa
        JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
        JOIN patient_info_tbl pi ON psa.patient_id = pi.patient_id
        WHERE psa.avail_id = $avail_id");
    $service = mysqli_fetch_assoc($svc);

    // Build patient array
    $patient = [
        'name' => trim(($service['patient_fname'] ?? '') . ' ' . ($service['patient_lname'] ?? '')),
        'dob' => $service['patient_dob'] ?? '',
        'sex' => $service['patient_sex'] ?? '',
        'phone' => $service['patient_phone'] ?? ''
    ];

    // Billing info (may be null)
    $billRes = mysqli_query($con, "SELECT * FROM billing_tbl WHERE avail_id = $avail_id LIMIT 1");
    $billing = mysqli_fetch_assoc($billRes) ?: null;

    // Procedures array
    $procResult = mysqli_query($con,"SELECT psp.*, proc.procedure_name, pg.group_name 
        FROM patient_service_proc psp
        LEFT JOIN procedure_tbl proc ON psp.procedure_id = proc.procedure_id
        LEFT JOIN procedure_group_tbl pg ON psp.group_id = pg.group_id
        WHERE psp.avail_id = $avail_id");
    $procs = [];
    while ($pr = mysqli_fetch_assoc($procResult)) $procs[] = $pr;

    echo json_encode([
        'status' => 'success',
        'service' => $service,
        'patient' => $patient,
        'billing' => $billing,
        'procedures' => $procs
    ]);
    exit;
}


// USER INFO
$full_name = htmlspecialchars($_SESSION['full_name'] ?? '');

// Get the user's role_id using the username (you could use any unique field here)
$username = $_SESSION['username'] ?? '';
$role_id = 0;
$user_q = mysqli_query($con, "SELECT role_id FROM user_account WHERE username = '$username' LIMIT 1");
if ($user_row = mysqli_fetch_assoc($user_q)) {
    $role_id = $user_row['role_id'];
    $_SESSION['role_id'] = $role_id; // store for later use!
}

// DYNAMIC ROLE DISPLAY FROM DB
$role_display = 'Personnel';
if ($role_id) {
    $role_query = mysqli_query($con, "SELECT role_name FROM roles WHERE role_id = $role_id");
    if ($role_row = mysqli_fetch_assoc($role_query)) {
        $role_display = htmlspecialchars($role_row['role_name']);
    }
}

// FETCH ALL SERVICE IDs FOR THIS ROLE
// $service_ids = [];
// $svc_result = mysqli_query($con, "SELECT service_id FROM clinic_service_tbl WHERE role_id = $role_id");
// while ($svc_row = mysqli_fetch_assoc($svc_result)) {
//     $service_ids[] = $svc_row['service_id'];
// }
// $service_ids_csv = $service_ids ? implode(',', $service_ids) : '0';


// ...session check and $role_id acquisition...

$today = date('Y-m-d');

// 1. Get all service IDs for this role (so it works for Lab, ECG, etc.)
$service_ids = [];
$res = mysqli_query($con, "SELECT service_id FROM clinic_service_tbl WHERE role_id = $role_id");
while ($r = mysqli_fetch_assoc($res)) $service_ids[] = $r['service_id'];
$service_ids_csv = $service_ids ? implode(',', $service_ids) : '0';


// --- AJAX: DASHBOARD COUNTS ---
if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['ajax_get_dashboard_counts'])) {
    // Use the same code as below for initial load!
    $q1 = mysqli_query($con, "SELECT COUNT(DISTINCT psa.patient_id) as n
        FROM patient_service_avail psa
        WHERE psa.service_id IN ($service_ids_csv) AND DATE(psa.date_availed) = '$today'");
    $patientsServedToday = (mysqli_fetch_assoc($q1)['n']) ?? 0;

    $q2 = mysqli_query($con, "SELECT COUNT(*) as n
        FROM patient_service_avail psa
        WHERE psa.service_id IN ($service_ids_csv) AND DATE(psa.date_availed) = '$today'");
    $totalRequestsToday = (mysqli_fetch_assoc($q2)['n']) ?? 0;

    $q3 = mysqli_query($con, "SELECT COUNT(*) as n
        FROM patient_service_avail psa
        WHERE psa.service_id IN ($service_ids_csv) AND psa.status = 'Completed' AND DATE(psa.date_availed) = '$today'");
    $completedToday = (mysqli_fetch_assoc($q3)['n']) ?? 0;

    $q4 = mysqli_query($con, "SELECT COUNT(*) as n
        FROM patient_service_avail psa
        WHERE psa.service_id IN ($service_ids_csv) AND psa.status = 'Pending' AND DATE(psa.date_availed) = '$today'");
    $pendingToday = (mysqli_fetch_assoc($q4)['n']) ?? 0;

    echo json_encode([
        'patients_served' => $patientsServedToday,
        'total_requests' => $totalRequestsToday,
        'completed_today' => $completedToday,
        'pending_today' => $pendingToday
    ]);
    exit;
}

// 2. Patients Served Today (unique)
/* Use a general label for all personnel roles */
$q1 = mysqli_query($con, "SELECT COUNT(DISTINCT psa.patient_id) as n
    FROM patient_service_avail psa
    WHERE psa.service_id IN ($service_ids_csv) AND DATE(psa.date_availed) = '$today'");
$patientsServedToday = (mysqli_fetch_assoc($q1)['n']) ?? 0;

// 3. Total Requests Today (all avails)
$q2 = mysqli_query($con, "SELECT COUNT(*) as n
    FROM patient_service_avail psa
    WHERE psa.service_id IN ($service_ids_csv) AND DATE(psa.date_availed) = '$today'");
$totalRequestsToday = (mysqli_fetch_assoc($q2)['n']) ?? 0;

// 4. Completed Today
$q3 = mysqli_query($con, "SELECT COUNT(*) as n
    FROM patient_service_avail psa
    WHERE psa.service_id IN ($service_ids_csv) AND psa.status = 'Completed' AND DATE(psa.date_availed) = '$today'");
$completedToday = (mysqli_fetch_assoc($q3)['n']) ?? 0;

// 5. Pending Today
$q4 = mysqli_query($con, "SELECT COUNT(*) as n
    FROM patient_service_avail psa
    WHERE psa.service_id IN ($service_ids_csv) AND psa.status = 'Pending' AND DATE(psa.date_availed) = '$today'");
$pendingToday = (mysqli_fetch_assoc($q4)['n']) ?? 0;




// GET ALL PENDING AVALS FOR THESE SERVICES
$query = "
    SELECT
        psa.case_no,
        pi.patient_fname,
        pi.patient_lname,
        psa.requested_by,
        cst.service_name,
        psa.date_availed,
        psa.status,
        psa.avail_id,
        psa.service_id
    FROM patient_service_avail psa
    JOIN patient_info_tbl pi  ON psa.patient_id = pi.patient_id
    JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
    WHERE psa.service_id IN ($service_ids_csv)
      AND psa.status = 'Pending'
    ORDER BY psa.date_availed DESC
";


$result = mysqli_query($con, $query);


?>

<div class="page-title">
    <div class="title">
        Welcome, <?= $full_name ?>!
    </div>
</div>

<div class="dashboard-boxes">
  <div class="dashboard-box-modern">
    <div class="dbox-content">
      <div class="dashboard-box-value"><?= number_format($patientsServedToday) ?></div>
      <div class="dashboard-box-desc">Patients Served Today</div>
    </div>
    <div class="dbox-icon icon-info">
            <i class="fas fa-user-injured"></i>
        </div>
  </div>

  <div class="dashboard-box-modern">
    <div class="dbox-content">
      <div class="dashboard-box-value"><?= number_format($totalRequestsToday) ?></div>
      <div class="dashboard-box-desc">Total Requests Today</div>
    </div>
    <div class="dbox-icon icon-primary"><i class="fas fa-clipboard-list"></i></div>
  </div>

  <div class="dashboard-box-modern">
    <div class="dbox-content">
      <div class="dashboard-box-value"><?= number_format($completedToday) ?></div>
      <div class="dashboard-box-desc">Completed Today</div>
    </div>
    <div class="dbox-icon icon-success">
            <i class="fas fa-check-circle"></i>
        </div>
  </div>
  
  <div class="dashboard-box-modern">
    <div class="dbox-content">
      <div class="dashboard-box-value"><?= number_format($pendingToday) ?></div>
      <div class="dashboard-box-desc">Pending Requests</div>
    </div>
    <div class="dbox-icon icon-warning">
            <i class="fas fa-clock"></i>
        </div>
  </div>
</div>


<div class="filter-bar">

  <div class="filter-daterange-group">
    <label>Date</label>
        <div class="date-inline-wrapper">
            <select id="personnelDatePreset">
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="last7">Last 7 Days</option>
                <option value="last30">Last 30 Days</option>
                <option value="custom">Custom</option>
            </select>
            <div class ="custom-date">
                <input type="date" id="personnelDateFrom">
                <span>to</span>
                <input type="date" id="personnelDateTo">
            </div>
        </div>
  </div>

  <div class="filter-sort-group">
    <label>Sort</label>
    <select id="personnelSortFilter">
      <option value="date_desc" selected>Newest First</option>
      <option value="date_asc">Oldest First</option>
      <option value="name_asc">A-Z</option>
      <option value="name_desc">Z-A</option>
    </select>
  </div>

  <div class="filter-status-group">
        <label>Status</label>
        <select id="personnelStatusDropdown">
            <option value="all" selected>All</option>
            <option value="Pending">Pending</option>
            <option value="Completed">Completed</option>
            <option value="Canceled">Canceled</option>
        </select>
    </div>

  <div class="search-bar">
    <i class="fas fa-search"></i>
    <input type="text" id="personnelSearchPatient" placeholder="Search Patient" />
  </div>
</div>



<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Case No.</th>
                <th>Patient Name</th>
                <th>Physician/Requested By</th>
                <th>Service Name</th>
                <th>Date/Time</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="pendingRequestsTable">
            <?php
                if ($result && mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $case_no     = htmlspecialchars($row['case_no'] ?? '--');
                        $fullName    = htmlspecialchars($row['patient_fname'] . ' ' . $row['patient_lname']);
                        $physician   = htmlspecialchars($row['requested_by'] ?? '--');
                        $serviceName = htmlspecialchars($row['service_name'] ?? '--');
                        $dateTime    = date("M d, Y H:i", strtotime($row['date_availed']));
                        $status      = htmlspecialchars($row['status'] ?? '--');
                        $avail_id    = $row['avail_id'];

                        echo "<tr>
                                <td>$case_no</td>
                                <td>$fullName</td>
                                <td>$physician</td>
                                <td>$serviceName</td>
                                <td>$dateTime</td>
                                <td>$status</td>
                                <td>
                                    <button type=\"button\" class=\"btn btn-outline btn-sm view-avail-btn\" 
                                        data-avail_id=\"$avail_id\" 
                                        data-service_id=\"{$row['service_id']}\">
                                        <i class=\"fas fa-eye\"></i> View
                                    </button>
                                </td>
                            </tr>";

                    }
                } else {
                    echo "<tr><td colspan='7' style='text-align:center;color:#888;'>No pending request forms found.</td></tr>";
                }
            ?>
             <tr id="noPendingRow" style="display:none;">
    <td colspan="7" style="text-align:center;color:#888;">No pending request forms found.</td>
  </tr>
        </tbody>
    </table>
</div>

<div id="viewRequestModal" class="modal" style="display:none;">
  <div class="modal-content large-modal">

    <div class="info-card">
        <div class="card-header">
            <h3>Patient Details</h3></div>
        <div class="info-grid" id="modalPatientGrid">
            <div class="info-item"><label>Name</label><span id="modalPatientName"></span></div>
            <div class="info-item"><label>DOB</label><span id="modalPatientDOB"></span></div>
            <div class="info-item"><label>Sex</label><span id="modalPatientSex"></span></div>
            <div class="info-item"><label>Phone</label><span id="modalPatientPhone"></span></div>
        </div>
    </div>
      
    <div class="info-card">
      <div class="card-header"><h3>Service Details</h3></div>
      <div class="info-grid">
        <div class="info-item"><label>Service</label><span id="modalServiceName"></span></div>
        <div class="info-item"><label>Requested By</label><span id="modalRequestedBy"></span></div>
        <div class="info-item"><label>Date Availed</label><span id="modalDateAvailed"></span></div>
        <div class="info-item"><label>Case No</label><span id="modalCaseNo"></span></div>
        <div class="info-item"><label>Package Name</label><span id="modalPackageName"></span></div>
        <div class="info-item"><label>Brief History</label><span id="modalBriefHistory"></span></div>
        <div class="info-item"><label>Status</label><span id="modalStatus"></span></div>
        <div class="info-item"><label>Billing Status</label><span id="modalBillingStatus"></span></div>
      </div>
    </div>


    <div class="info-card">
      <div class="card-header"><h3>Procedures</h3></div>
      <div class="info-grid" id="modalProceduresGrid"></div>
    </div>

     <div class="info-card">
          <div class="card-header"><h3>Billing Details</h3></div>
          <div class="info-grid" id="modalBillingGrid">
              <div class="info-item"><label>OR #</label><span id="modalOR"></span></div>
              <div class="info-item"><label>Subtotal</label><span id="modalSubtotal"></span></div>
              <div class="info-item"><label>Discount Applied</label><span id="modalDiscount"></span></div>
              <div class="info-item"><label>Total</label><span id="modalTotal"></span></div>
          </div>
      </div>           

    <div class="modal-buttons">
      <button type="button" id="completeRequestBtn" class="btn btn-success">Completed</button>
      <button type="button" id="cancelRequestBtn" class="btn btn-danger">Cancel</button>
      <button type="button" id="pendingRequestBtn" class="btn btn-warning">Pending</button>
      <button type="button" id="closeRequestModalBtn" class="btn btn-outline">Close</button>
    </div>
  </div>
</div>