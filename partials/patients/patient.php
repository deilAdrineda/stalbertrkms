<?php 
    include '../../includes/session_check.php';
    requireRole(['Administrator','Receptionist','Laboratory Personnel', 'Ultrasound Personnel', '2D Echo Personnel', 'ECG Personnel', 'X-RAY Personnel']);

    // put a condition, where admin and receptionist, can view all of the patient..
    if($_SESSION['role'] == 'Administrator') { ?>
    <div class="page-title">
            <div class="title">Patients</div>

            <!-- Search Bar -->
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="searchPatient" placeholder="Search Patient" />
            </div>

            <!-- Action Button -->
            <div class="action-buttons">
                <!-- <a href="st_albert.php?page=dashboard/add_patient.php" id="addPatientBtn" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Patient
                </a> -->
                <?php 
                    // leave this be, ni copy ko lang sa Receptionist, nag-iiba pwesto pag tinanggal div for button
                ?>
            </div>
            </div>

            <!-- Filter Bar for Patients -->
<div class="filter-bar" style="margin-bottom: 20px; padding: 15px; background: var(--color-surface); border-radius: var(--radius-lg); display: flex; gap: 15px; align-items: center; border: 1px solid var(--color-border);">
    <label style="font-weight: var(--font-weight-medium); color: var(--color-text);">
        <i class="fas fa-filter"></i> Status:
    </label>
    <select id="patientStatusFilter" class="form-control" style="width: 200px;">
        <option value="active" selected>Active Only</option>
        <option value="archived">Archived Only</option>
        <option value="all">All Patients</option>
    </select>

    <label style="font-weight: var(--font-weight-medium); color: var(--color-text); margin-left: 15px;">
        <i class="fas fa-sort"></i> Sort By:
    </label>
    <select id="patientSortFilter" class="form-control" style="width: 200px;">
        <option value="date_desc" selected>Newest First</option>
        <option value="date_asc">Oldest First</option>
        <option value="name_asc">Name (A-Z)</option>
        <option value="name_desc">Name (Z-A)</option>
    </select>
</div>


           <!-- Patients Table -->
            <div class="table-card">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Name</th>
                        <th>Date Created</th>
                        <th>Phone Number</th>
                        <th>Actions</th>
                    </tr>
                    </thead>

                    <tbody id="patientsTable">
                <?php

                    $statusFilter = $_POST['status'] ?? $_GET['status'] ?? 'active';
$sortFilter = $_POST['sort'] ?? $_GET['sort'] ?? 'date_desc';
$search = $_POST['search'] ?? $_GET['search'] ?? '';
// Archive status condition
if ($statusFilter == 'archived') {
    $archiveCondition = "is_archived = 1";
} elseif ($statusFilter == 'active') {
    $archiveCondition = "is_archived = 0";
} else {
    $archiveCondition = "1=1";
}
// Sorting
switch ($sortFilter) {
    case 'date_asc':
        $orderBy = "patient_added ASC";
        break;
    case 'name_asc':
        $orderBy = "patient_fname ASC, patient_lname ASC";
        break;
    case 'name_desc':
        $orderBy = "patient_fname DESC, patient_lname DESC";
        break;
    default:
        $orderBy = "patient_added DESC";
}
// Search
$searchSql = '';
if ($search !== '') {
    $safeSearch = mysqli_real_escape_string($con, $search);
    $searchSql = "AND (CONCAT(patient_fname, ' ', patient_lname) LIKE '%$safeSearch%' OR patient_code LIKE '%$safeSearch%')";
}
// Final query
$query = "SELECT * FROM patient_info_tbl WHERE $archiveCondition $searchSql ORDER BY $orderBy";


                    // $query = "SELECT * FROM patient_info_tbl WHERE is_archived = 0 ORDER BY patient_added DESC";
                    $result = mysqli_query($con, $query);

                    if ($result && mysqli_num_rows($result) > 0){

                    while ($row = mysqli_fetch_assoc($result)) {
                        // Format name, dates
                        $patientID = htmlspecialchars($row['patient_code']);
                        $fullName = htmlspecialchars($row['patient_fname'] . ' ' . $row['patient_lname']);
                        $addedDate = date("M d, Y", strtotime($row['patient_added']));
                        $phone = htmlspecialchars($row['patient_phone']);

                        // Output row
                        echo "<tr>";
                        echo '<td>' . $patientID . '</td>';
                        echo '<td>' . $fullName . '</td>';
                        echo '<td>' . $addedDate . '</td>';
                        echo '<td>' . $phone . '</td>';
                        echo '
                        <td>
                            <button type="button" class="btn btn-outline btn-sm view-patient-btn" 
                                        data-patient_id="' . htmlspecialchars($row['patient_id']) . '">
                                    <i class="fas fa-eye"></i> View
                                </button>
                        </td>';
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='5' style='text-align:center;color:#888;'>No patients found.</td></tr>";
                }
                ?>
                </tbody>
                
                </table>
            </div>

        <script src="js/main.js"></script>
            
    <?php
    }
    ?>
    
    <?php
    if($_SESSION['role'] == 'Receptionist') { ?>
       <div class="page-title">
            <div class="title">Patients</div>

            <!-- Search Bar -->
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="searchPatient" placeholder="Search Patient" />
            </div>

            <!-- Action Button -->
            <div class="action-buttons">
                <button id = "addPatientBtn" class = "btn btn-primary">
                    <i class = "fas fa-plus"></i>  Add Patient
                </button>
            </div>
        </div>

         <!-- Filter Bar for Patients -->
<div class="filter-bar" style="margin-bottom: 20px; padding: 15px; background: var(--color-surface); border-radius: var(--radius-lg); display: flex; gap: 15px; align-items: center; border: 1px solid var(--color-border);">
    <label style="font-weight: var(--font-weight-medium); color: var(--color-text);">
        <i class="fas fa-filter"></i> Status:
    </label>
    <select id="patientStatusFilter" class="form-control" style="width: 200px;">
        <option value="active" selected>Active Only</option>
        <option value="archived">Archived Only</option>
        <option value="all">All Patients</option>
    </select>

    <label style="font-weight: var(--font-weight-medium); color: var(--color-text); margin-left: 15px;">
        <i class="fas fa-sort"></i> Sort By:
    </label>
    <select id="patientSortFilter" class="form-control" style="width: 200px;">
        <option value="date_desc" selected>Newest First</option>
        <option value="date_asc">Oldest First</option>
        <option value="name_asc">Name (A-Z)</option>
        <option value="name_desc">Name (Z-A)</option>
    </select>
</div>

            <!-- Patients Table -->
            <div class="table-card">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Patient ID</th>
                    <th>Name</th>
                    <th>Date Created</th>
                    <th>Phone Number</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody id="patientsTable">
                <?php

                    $statusFilter = $_POST['status'] ?? $_GET['status'] ?? 'active';
$sortFilter = $_POST['sort'] ?? $_GET['sort'] ?? 'date_desc';
$search = $_POST['search'] ?? $_GET['search'] ?? '';
// Archive status condition
if ($statusFilter == 'archived') {
    $archiveCondition = "is_archived = 1";
} elseif ($statusFilter == 'active') {
    $archiveCondition = "is_archived = 0";
} else {
    $archiveCondition = "1=1";
}
// Sorting
switch ($sortFilter) {
    case 'date_asc':
        $orderBy = "patient_added ASC";
        break;
    case 'name_asc':
        $orderBy = "patient_fname ASC, patient_lname ASC";
        break;
    case 'name_desc':
        $orderBy = "patient_fname DESC, patient_lname DESC";
        break;
    default:
        $orderBy = "patient_added DESC";
}
// Search
$searchSql = '';
if ($search !== '') {
    $safeSearch = mysqli_real_escape_string($con, $search);
    $searchSql = "AND (CONCAT(patient_fname, ' ', patient_lname) LIKE '%$safeSearch%' OR patient_code LIKE '%$safeSearch%')";
}
// Final query
$query = "SELECT * FROM patient_info_tbl WHERE $archiveCondition $searchSql ORDER BY $orderBy";


                    // $query = "SELECT * FROM patient_info_tbl WHERE is_archived = 0 ORDER BY patient_added DESC";
                    $result = mysqli_query($con, $query);

                    if ($result && mysqli_num_rows($result) > 0){

                    while ($row = mysqli_fetch_assoc($result)) {
                        // Format name, dates
                        $patientID = htmlspecialchars($row['patient_code']);
                        $fullName = htmlspecialchars($row['patient_fname'] . ' ' . $row['patient_lname']);
                        $addedDate = date("M d, Y", strtotime($row['patient_added']));
                        $phone = htmlspecialchars($row['patient_phone']);

                        // Output row
                        echo "<tr>";
                        echo '<td>' . $patientID . '</td>';
                        echo '<td>' . $fullName . '</td>';
                        echo '<td>' . $addedDate . '</td>';
                        echo '<td>' . $phone . '</td>';
                        echo '
                        <td>
                            <button type="button" class="btn btn-outline btn-sm view-patient-btn" 
                                        data-patient_id="' . htmlspecialchars($row['patient_id']) . '">
                                    <i class="fas fa-eye"></i> View
                                </button>
                        </td>';
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='5' style='text-align:center;color:#888;'>No patients found.</td></tr>";
                }
                ?>
                </tbody>
            </table>
            </div>

            <!-- Scripts -->
             <script src="js/main.js"></script>
    <?php
    }
    ?>


<?php
// =========== CLINIC PERSONNELS CHECK (LAB, ECG, X-RAY ETC.)===============
        if (in_array($_SESSION['role'], [
            'Laboratory Personnel',
            'Ultrasound Personnel',
            '2D Echo Personnel',
            'ECG Personnel',
            'X-RAY Personnel'
        ])) {

    // Get the role's service(s)
    $role_id = $_SESSION['role_id'] ?? 0;

    $serviceIds = [];
    $svcRes = mysqli_query($con, "SELECT service_id FROM clinic_service_tbl WHERE role_id = $role_id");
    while ($svcRow = mysqli_fetch_assoc($svcRes)) {
        $serviceIds[] = $svcRow['service_id'];
    }
    $serviceIdsCsv = $serviceIds ? implode(',', $serviceIds) : '0';

    ?>
    <div class="page-title">
        <div class="title">
            Patients for <?= htmlspecialchars($_SESSION['role'] ?? '') ?>
        </div>
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="searchPatient" placeholder="Search Patient" />
        </div>
    </div>

    <div class="filter-bar" style="margin-bottom: 20px; padding: 15px; background: var(--color-surface); border-radius: var(--radius-lg); display: flex; gap: 15px; align-items: center; border: 1px solid var(--color-border);">
    <label><i class="fas fa-filter"></i> Status:</label>
    <select id="availStatusFilter" class="form-control" style="width:200px;">
        <option value="all" selected>All Avails</option>
        <option value="completed">Completed Only</option>
        <option value="canceled">Canceled Only</option>
    </select>
    <label style="margin-left: 15px;"><i class="fas fa-sort"></i> Sort By:</label>
    <select id="patientSortFilter" class="form-control" style="width: 200px;">
        <option value="date_desc" selected>Newest First</option>
        <option value="date_asc">Oldest First</option>
        <option value="name_asc">Name (A-Z)</option>
        <option value="name_desc">Name (Z-A)</option>
    </select>
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
            <tbody id="patientsTable">
            <?php

            $availStatus = $_POST['availStatus'] ?? 'all';

            if ($availStatus == 'completed') {
                $statusCondition = "psa.status = 'Completed'";
            } elseif ($availStatus == 'canceled') {
                $statusCondition = "psa.status = 'Canceled'";
            } else {
                $statusCondition = "psa.status IN ('Completed', 'Canceled')";
            }


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
                WHERE psa.service_id IN ($serviceIdsCsv)
                AND $statusCondition
                ORDER BY psa.date_availed DESC
            ";
            $result = mysqli_query($con, $query);

            $result = mysqli_query($con, $query);
            if ($result && mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {

                $case_no = htmlspecialchars($row['case_no'] ?? '--');
                $fullName = htmlspecialchars($row['patient_fname'] . ' ' . $row['patient_lname']);
                $physician = htmlspecialchars($row['requested_by'] ?? '--');
                $serviceName = htmlspecialchars($row['service_name'] ?? '');
                $dateTime = date("M d, Y H:i", strtotime($row['date_availed']));
                $status = htmlspecialchars($row['status'] ?? '');
                $avail_id = $row['avail_id'];
                $service_id = $row['service_id'];

                    // ===================================
                    echo "<tr>";
                    echo '<td>' . $case_no . '</td>';
                    echo '<td>' . $fullName . '</td>';
                    echo '<td>' . $physician . '</td>';
                    echo '<td>' . $serviceName . '</td>';
                    echo '<td>' . $dateTime . '</td>';
                    echo '<td>' . $status . '</td>';

                    echo '
                    <td>
                        <button type="button" class="btn btn-outline btn-sm view-patient-btn" 
                                    data-patient_id="' . htmlspecialchars($row['patient_id']) . '">
                                <i class="fas fa-eye"></i> View
                            </button>
                    </td>';
                    echo "</tr>";
            }

            } else {
                echo "<tr><td colspan='4' style='text-align:center;color:#888;'>No patients found for your role/service.</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
    <script src="js/main.js"></script>
    <?php
}
?>