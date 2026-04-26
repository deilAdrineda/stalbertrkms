<?php
include '../../includes/session_check.php';
requireRole('Receptionist');
$full_name = htmlspecialchars($_SESSION['full_name'] ?? '');
?>

<!-- <div class="page-title">
    <div class="title">
        Receptionist page's Dashboard
    </div>
</div> -->

<div class="page-title">
    <div class="title">
        Welcome, <?= $full_name ?>!
    </div>
</div>

<div class="dashboard-boxes">
    <!-- total patients -->
    <div class="dashboard-box-modern">
        <div class="dbox-content">
            <?php
                $today = date('Y-m-d');
                $patientsToday = 0;
                $query = mysqli_query($con, "SELECT COUNT(*) AS total FROM patient_info_tbl WHERE DATE(patient_added) = '$today'");
                if ($row = mysqli_fetch_assoc($query))
                    $patientsToday = $row['total'];
            ?>
            <div class="dashboard-box-value"><?= number_format($patientsToday) ?></div>
            <div class="dashboard-box-desc">Newly added patients</div>
        </div>
        <div class="dbox-icon icon-primary">
            <i class="fas fa-user-plus"></i>
        </div>
    </div>

    <!-- patients who availed today -->
    <div class="dashboard-box-modern">
        <div class="dbox-content">
            <?php
                $patientServedToday = 0;
                $query = mysqli_query($con, "SELECT COUNT(DISTINCT patient_id) AS total FROM patient_service_avail WHERE DATE(date_availed) = '$today'");
                if ($row = mysqli_fetch_assoc($query))
                    $patientServedToday = $row['total'];
            ?>
            <div class="dashboard-box-value"><?= number_format($patientServedToday) ?></div>
            <div class="dashboard-box-desc">Patients served today</div>
        </div>
        <div class="dbox-icon icon-info">
            <i class="fas fa-user-injured"></i>
        </div>
    </div>

    <!-- services completed today -->
    <div class="dashboard-box-modern">
        <div class="dbox-content">
            <?php
                $serviceCompletedToday = 0;
                $query = mysqli_query($con, "SELECT COUNT(*) AS total FROM patient_service_avail WHERE DATE(date_availed) = '$today' AND status = 'Completed'");
                if ($row = mysqli_fetch_assoc($query))
                    $serviceCompletedToday = $row['total'];
            ?>
            <div class="dashboard-box-value"><?= number_format($serviceCompletedToday) ?></div>
            <div class="dashboard-box-desc">Services completed today</div>
        </div>
        <div class="dbox-icon icon-success">
            <i class="fas fa-check-circle"></i>
        </div>
    </div>

    <!-- pending services today -->
    <div class="dashboard-box-modern">
        <div class="dbox-content">
            <?php
                $servicePendingToday = 0;
                $query = mysqli_query($con, "SELECT COUNT(*) AS total FROM patient_service_avail WHERE DATE(date_availed) = '$today' AND status = 'Pending'");
                if ($row = mysqli_fetch_assoc($query))
                    $servicePendingToday = $row['total'];
            ?>
            <div class="dashboard-box-value"><?= number_format($servicePendingToday) ?></div>
            <div class="dashboard-box-desc">Pending services</div>
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
            <select id="recepDatePreset">
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="last7">Last 7 Days</option>
                <option value="last30">Last 30 Days</option>
                <option value="custom">Custom</option>
            </select>
            <div class="custom-date">
                <input type="date" id="recepDateFrom">
                <span>to</span>
                <input type="date" id="recepDateTo">
            </div>
        </div>
    </div>

    <div class="filter-sort-group">
        <label>Sort</label>
        <select id="recepPatientSortFilter">
            <option value="date_desc" selected>Newest First</option>
            <option value="date_asc">Oldest First</option>
            <option value="name_asc">A-Z</option>
            <option value="name_desc">Z-A</option>
        </select>
    </div>

    <div class="filter-status-group">
        <label>Status</label>
        <select id="recepStatusDropdown">
            <option value="all" selected>All</option>
            <option value="Pending">Pending</option>
            <option value="Completed">Completed</option>
            <option value="Canceled">Canceled</option>
        </select>
    </div>

    <div class="search-bar">
        <i class="fas fa-search"></i>
        <input type="text" id="recepSearchPatient" placeholder="Search Patient">
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
            <tbody id="recepPatientsTable">
<?php
// Default: today's avails (this runs on every page load)
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');
$statusCondition = "psa.status IN ('Completed', 'Canceled', 'Pending')";
$dateCondition = "DATE(psa.date_availed) = '$today'";

$query = "
    SELECT
        pi.patient_id,
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
    JOIN patient_info_tbl pi ON psa.patient_id = pi.patient_id
    JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
    WHERE $statusCondition
    AND $dateCondition
    ORDER BY psa.date_availed DESC
";
$result = mysqli_query($con, $query);
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
    echo "<tr><td colspan='7' style='text-align:center;color:#888;'>No completed or canceled services found today.</td></tr>";
}

?>
</tbody>

        </table>

        <div id="viewAvailModal" class="modal" style="display:none;">
  <div class="modal-content large-modal">

   <div class="info-card">
        <div class="card-header"><h3>Patient Details</h3></div>
        <div class="info-grid" id="recModalPatientGrid">
            <div class="info-item"><label>Name</label><span id="recModalPatientName"></span></div>
            <div class="info-item"><label>DOB</label><span id="recModalPatientDOB"></span></div>
            <div class="info-item"><label>Sex</label><span id="recModalPatientSex"></span></div>
            <div class="info-item"><label>Phone</label><span id="recModalPatientPhone"></span></div>
        </div>
    </div>

    <div class="info-card">
      <div class="card-header"><h3>Service Details</h3></div>
      <div class="info-grid">
        <div class="info-item"><label>Service</label><span id="recModalServiceName"></span></div>
        <div class="info-item"><label>Requested By</label><span id="recModalRequestedBy"></span></div>
        <div class="info-item"><label>Date Availed</label><span id="recModalDateAvailed"></span></div>
        <div class="info-item"><label>Case No</label><span id="recModalCaseNo"></span></div>
        <div class="info-item"><label>Package Name</label><span id="recModalPackageName"></span></div>
        <div class="info-item"><label>Brief History</label><span id="recModalBriefHistory"></span></div>
        <div class="info-item"><label>Status</label><span id="recModalStatus"></span></div>
        <div class="info-item"><label>Billing Status</label><span id="recModalBillingStatus"></span></div>
      </div>
    </div>

    <div class="info-card">
      <div class="card-header"><h3>Procedures</h3></div>
      <div class="info-grid" id="recModalProceduresGrid"></div>
    </div>

    <div class="info-card">
        <div class="card-header"><h3>Billing Details</h3></div>
        <div class="info-grid" id="recModalBillingGrid">
            <div class="info-item"><label>OR #</label><span id="recModalOR"></span></div>
            <div class="info-item"><label>Subtotal</label><span id="recModalSubtotal"></span></div>
            <div class="info-item"><label>Discount Applied</label><span id="recModalDiscount"></span></div>
            <div class="info-item"><label>Total</label><span id="recModalTotal"></span></div>
        </div>
    </div>

    <div class="modal-buttons">
      <button type="button" id="recCloseAvailModalBtn" class="btn btn-outline">Close</button>
    </div>
  </div>
</div>



    </div>

