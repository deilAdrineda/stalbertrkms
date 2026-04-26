<?php
include '../../includes/connection.php';
include '../../includes/session_check.php';

$search = isset($_POST['search']) ? trim($_POST['search']) : '';
$isPersonnel = in_array($_SESSION['role'], [
    'Laboratory Personnel',
    'Ultrasound Personnel',
    '2D Echo Personnel',
    'ECG Personnel',
    'X-RAY Personnel'
]);

if ($isPersonnel) {
    $role_id = $_SESSION['role_id'] ?? 0;
    $statusFilter = $_POST['status'] ?? 'active';
    $sortFilter = $_POST['sort'] ?? 'date_desc';
    $search = $_POST['search'] ?? '';

    $serviceIds = [];
    $svcRes = mysqli_query($con, "SELECT service_id FROM clinic_service_tbl WHERE role_id = $role_id");
    while ($svcRow = mysqli_fetch_assoc($svcRes)) {
        $serviceIds[] = $svcRow['service_id'];
    }
    $serviceIdsCsv = $serviceIds ? implode(',', $serviceIds) : '0';

    // Archive filter is applied to patient_info_tbl
    if ($statusFilter == 'archived') {
        $archiveCondition = "pi.is_archived = 1";
    } elseif ($statusFilter == 'active') {
        $archiveCondition = "pi.is_archived = 0";
    } else {
        $archiveCondition = "1=1";
    }

    // Sort logic
    switch ($sortFilter) {
        case 'date_asc':  
            $orderBy = "psa.date_availed ASC"; 
            break;
        case 'name_asc':  
            $orderBy = "pi.patient_fname ASC, pi.patient_lname ASC"; 
            break;
        case 'name_desc': 
            $orderBy = "pi.patient_fname DESC, pi.patient_lname DESC"; 
            break;
        default: 
            $orderBy = "psa.date_availed DESC"; 
            break;
    }

    $searchSql = '';
    $params = [];
    if ($search !== '') {
        $searchSql = " AND (CONCAT(pi.patient_fname, ' ', pi.patient_lname) LIKE ? OR pi.patient_fname LIKE ? OR pi.patient_lname LIKE ? OR pi.patient_phone LIKE ? OR psa.case_no LIKE ? OR psa.requested_by LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_fill(0, 6, $searchTerm);
    }

    $availStatus = $_POST['availStatus'] ?? 'all';

    if ($availStatus == 'completed') {
        $statusCondition = "psa.status = 'Completed'";
    } elseif ($availStatus == 'canceled') {
        $statusCondition = "psa.status = 'Canceled'";
    } else {
        $statusCondition = "psa.status IN ('Completed', 'Canceled')";
    }

    $query = "SELECT
                psa.case_no,
                pi.patient_id,
                pi.patient_fname,
                pi.patient_lname,
                psa.requested_by,
                cst.service_name,
                psa.date_availed,
                psa.status,
                psa.avail_id,
                psa.service_id,
                pi.is_archived
            FROM patient_service_avail psa
            JOIN patient_info_tbl pi ON psa.patient_id = pi.patient_id
            JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
            WHERE psa.service_id IN ($serviceIdsCsv)
              AND $statusCondition
              AND $archiveCondition
              $searchSql
            ORDER BY $orderBy";

    if (count($params) > 0) {
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, "ssssss", ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($con, $query);
    }

    $existResult = false;
    while ($row = mysqli_fetch_assoc($result)) {
        $existResult = true;
        $case_no = htmlspecialchars($row['case_no'] ?? '--');
        $fullName = htmlspecialchars($row['patient_fname'] . ' ' . $row['patient_lname']);
        $physician = htmlspecialchars($row['requested_by'] ?? '--');
        $serviceName = htmlspecialchars($row['service_name'] ?? '');
        $dateTime = htmlspecialchars(date("M d, Y H:i", strtotime($row['date_availed'] ?? '')));
        $status = htmlspecialchars($row['status'] ?? '');
        $avail_id = $row['avail_id'] ?? '';
        $service_id = $row['service_id'] ?? '';

        echo "<tr>";
        echo '<td>' . $case_no . '</td>';
        echo '<td>' . $fullName . '</td>';
        echo '<td>' . $physician . '</td>';
        echo '<td>' . $serviceName . '</td>';
        echo '<td>' . $dateTime . '</td>';
        echo '<td>' . $status . '</td>';
        echo '<td>
                <button type="button" class="btn btn-outline btn-sm view-patient-btn" 
                    data-patient_id="' . htmlspecialchars($row['patient_id']) . '">
                    <i class="fas fa-eye"></i> View
                </button>
            </td>';
        echo "</tr>";
    }
    if (!$existResult) {
        if ($search !== '') {
            echo "<tr><td colspan='7' style='text-align:center;'>No results found for <b>" . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . "</b>.</td></tr>";
        } else {
            echo "<tr><td colspan='7' style='text-align:center;color:#888;'>No patients available for your role/service.</td></tr>";
        }
    }

} else {
    // ADMIN/RECEPTIONIST info table search
    $search = $_POST['search'] ?? '';

    // $statusFilter = $_POST['status'] ?? 'active';
    $sortFilter = $_POST['sort'] ?? 'date_desc';
    

    // if ($statusFilter == 'archived') {
    //     $archiveCondition = "is_archived = 1";
    // } elseif ($statusFilter == 'active') {
    //     $archiveCondition = "is_archived = 0";
    // } else {
    //     $archiveCondition = "1=1";
    // }

    $statusFilter = $_POST['status'] ?? 'active';
    $allowedStatus = ['archived','active','all'];
    if (!in_array($statusFilter, $allowedStatus)) $statusFilter = 'active';
    if ($statusFilter == 'archived') {
        $archiveCondition = "is_archived = 1";
    } elseif ($statusFilter == 'active') {
        $archiveCondition = "is_archived = 0";
    } else {
        $archiveCondition = "1=1";
    }

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
            break;
    }

    $params = [];
    $searchSql = "";
    if ($search !== '') {
        $searchSql = " AND (CONCAT(patient_fname, ' ', patient_lname) LIKE ? OR patient_fname LIKE ? OR patient_lname LIKE ? OR patient_phone LIKE ? OR patient_code LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_fill(0, 5, $searchTerm);
    }

    $query = "SELECT patient_id, patient_code, patient_fname, patient_mname, patient_lname, patient_phone, patient_added, is_archived
              FROM patient_info_tbl
              WHERE $archiveCondition $searchSql
              ORDER BY $orderBy";

    if (count($params) > 0) {
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, "sssss", ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($con, $query);
    }

    $existResult = false;
    while ($row = mysqli_fetch_assoc($result)) {
        $existResult = true;
        $fullName = htmlspecialchars($row['patient_fname'] . ' ' . $row['patient_lname']);
        $addedDate = date("M d, Y", strtotime($row['patient_added']));
        $phone = htmlspecialchars($row['patient_phone']);
        $patient_id = htmlspecialchars($row['patient_code']);

        echo "<tr>";
        echo "<td>$patient_id</td>";
        echo "<td>$fullName</td>";
        echo "<td>$addedDate</td>";
        echo "<td>$phone</td>";
        echo "<td>
            <button type=\"button\" class=\"btn btn-outline btn-sm view-patient-btn\"
                data-patient_id=\"" . htmlspecialchars($row['patient_id']) . "\">
                <i class=\"fas fa-eye\"></i> View
            </button>
        </td>";
        echo "</tr>";
    }
    if (!$existResult) {
        $colspan = 5;
        if ($search !== '') {
            echo "<tr><td colspan='$colspan' style='text-align:center;'>No results found for <b>" . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . "</b>.</td></tr>";
        } else {
            echo "<tr><td colspan='$colspan' style='text-align:center;color:#888;'>No patients found.</td></tr>";
        }
    }
}
?>