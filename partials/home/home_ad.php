<?php
include '../../includes/session_check.php';
    requireRole('Administrator');
    $full_name = htmlspecialchars($_SESSION['full_name'] ?? '');
?>

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
            <div class="dashboard-box-desc">Number of newly added patients</div>
        </div>
        <div class="dbox-icon patients-total">
            <i class="fas fa-user-injured"></i>
        </div>
    </div>

    <div class="dashboard-box-modern">
        <div class="dbox-content">
            <?php
                $patientServedToday = 0;
                $query = mysqli_query($con, "SELECT COUNT(DISTINCT patient_id) AS total FROM patient_service_avail WHERE DATE(date_availed) = '$today'");
                if ($row = mysqli_fetch_assoc($query))
                    $patientServedToday = $row['total'];
            ?>
            <div class="dashboard-box-value"><?= number_format($patientServedToday) ?></div>
            <div class="dashboard-box-desc">Number of patients served today</div>
        </div>
        <div class="dbox-icon icon-info">
            <i class="fas fa-user-injured"></i>
        </div>
    </div>

    <div class="dashboard-box-modern">
        <div class="dbox-content">
            <?php
                $serviceCompletedToday = 0;
                $query = mysqli_query($con, "SELECT COUNT(*) AS total FROM patient_service_avail WHERE DATE(date_availed) = '$today' AND status = 'Completed'");
                if ($row = mysqli_fetch_assoc($query))
                    $serviceCompletedToday = $row['total'];
            ?>
            <div class="dashboard-box-value"><?= number_format($serviceCompletedToday) ?></div>
            <div class="dashboard-box-desc">Number of services completed today</div>
        </div>
        <div class="dbox-icon icon-success">
            <i class="fas fa-check-circle"></i>
        </div>
    </div>

    <div class="dashboard-box-modern">
        <div class="dbox-content">
            <?php
                $servicePendingToday = 0;
                $query = mysqli_query($con, "SELECT COUNT(*) AS total FROM patient_service_avail WHERE DATE(date_availed) = '$today' AND status = 'Pending'");
                if ($row = mysqli_fetch_assoc($query))
                    $servicePendingToday = $row['total'];
            ?>
            <div class="dashboard-box-value"><?= number_format($servicePendingToday) ?></div>
            <div class="dashboard-box-desc">Number of services pending today</div>
        </div>
        <div class="dbox-icon icon-warning">
            <i class="fas fa-clock"></i>
        </div>
    </div>
</div>

<!-- <div class="filter-bar">
    <label><i class="fas fa-filter"></i></label>
    <select id="adminAvailStatusFilter" class="form-select form-select-sm">
        <option value="all" selected>All status</option>
        <option value="completed">Completed</option>
        <option value="canceled">Canceled</option>
        <option value="pending">Pending</option>
    </select>
    <label><i class="fas fa-sort"></i></label>
    <select id="adminPatientSortFilter" class="form-select form-select-sm">
        <option value="date_desc" selected>Newest First</option>
        <option value="date_asc">Oldest First</option>
        <option value="name_asc">A-Z</option>
        <option value="name_desc">Z-A</option>
    </select>
    <div class="search-bar">
        <i class="fas fa-search"></i>
        <input type="text" id="adminSearchPatient" placeholder="Search Patient" />
    </div>
</div> -->

<div class="filter-bar">
  <div class="filter-daterange-group">
    <label>Date</label>
        <div>
            <select id="adminDatePreset">
            <option value="today">Today</option>
            <option value="yesterday">Yesterday</option>
            <option value="last7">Last 7 Days</option>
            <option value="last30">Last 30 Days</option>
            <option value="custom">Custom</option>
        </select>
        <div class ="custom-date">
            <input type="date" id="adminDateFrom">
            <span>to</span>
            <input type="date" id="adminDateTo">
        </div>
    </div>
</div>
  
  <div class="filter-sort-group">
    <label>Sort</label>
    <select id="adminPatientSortFilter">
      <option value="date_desc" selected>Newest First</option>
      <option value="date_asc">Oldest First</option>
      <option value="name_asc">A-Z</option>
      <option value="name_desc">Z-A</option>
    </select>
  </div>

  <div class="filter-status-group">
    <label>Status:</label>
        <select id="adminStatusDropdown">
            <option value="all" selected>All</option>
            <option value="Pending">Pending</option>
            <option value="Completed">Completed</option>
            <option value="Canceled">Canceled</option>
        </select>
  </div>

  <div class="search-bar">
    <i class="fas fa-search"></i>
    <input type="text" id="adminSearchPatient" placeholder="Search Patient" />
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
        <tbody id="adminPatientsTable">
<?php
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

    <!-- Admin modal for viewing service: update IDs for admin context -->
    <div id="viewAdminAvailModal" class="modal" style="display:none;">
        <div class="modal-content large-modal">

            <div class="info-card">
                <div class="card-header">
                    <h3>Patient Details</h3></div>
                <div class="info-grid" id="adminModalPatientGrid">
                    <div class="info-item"><label>Name</label><span id="adminModalPatientName"></span></div>
                    <div class="info-item"><label>DOB</label><span id="adminModalPatientDOB"></span></div>
                    <div class="info-item"><label>Sex</label><span id="adminModalPatientSex"></span></div>
                    <div class="info-item"><label>Phone</label><span id="adminModalPatientPhone"></span></div>
                </div>
            </div>

            <div class="info-card">
                <div class="card-header">
                    <h3>Service Details</h3></div>
                <div class="info-grid">
                    <div class="info-item"><label>Service</label><span id="adminModalServiceName"></span></div>
                    <div class="info-item"><label>Requested By</label><span id="adminModalRequestedBy"></span></div>
                    <div class="info-item"><label>Date Availed</label><span id="adminModalDateAvailed"></span></div>
                    <div class="info-item"><label>Case No</label><span id="adminModalCaseNo"></span></div>
                    <div class="info-item"><label>Package Name</label><span id="adminModalPackageName"></span></div>
                    <div class="info-item"><label>Brief History</label><span id="adminModalBriefHistory"></span></div>
                    <div class="info-item"><label>Status</label><span id="adminModalStatus"></span></div>
                    <div class="info-item"><label>Billing Status</label><span id="adminModalBillingStatus"></span></div>
                </div>
            </div>

            <div class="info-card">
                <div class="card-header"><h3>Procedures</h3></div>
                <div class="info-grid" id="adminModalProceduresGrid"></div>
            </div>

            <div class="info-card">
                <div class="card-header"><h3>Billing Details</h3></div>
                <div class="info-grid" id="adminModalBillingGrid">
                    <div class="info-item"><label>OR #</label><span id="adminModalOR"></span></div>
                    <div class="info-item"><label>Subtotal</label><span id="adminModalSubtotal"></span></div>
                    <div class="info-item"><label>Discount Applied</label><span id="adminModalDiscount"></span></div>
                    <div class="info-item"><label>Total</label><span id="adminModalTotal"></span></div>
                </div>
            </div>

            <div class="modal-buttons">
                <button type="button" id="adminCloseAvailModalBtn" class="btn btn-outline">Close</button>
            </div>
        </div>
    </div>
</div>