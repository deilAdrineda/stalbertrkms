<?php
include '../../includes/session_check.php';
requireRole(['Administrator', 'Receptionist', 'Laboratory Personnel', 'Ultrasound Personnel', '2D Echo Personnel', 'ECG Personnel', 'X-RAY Personnel']);

if($_SESSION['role'] == 'Administrator') {

    if (isset($_POST['avail_id'])) {
    $availId = intval($_POST['avail_id']);

//     $serviceQuery = "
//     SELECT psa.*, cst.service_name,
//            pi.patient_fname, pi.patient_lname, pi.patient_dob, pi.patient_sex, pi.patient_phone,
//            b.or_number, b.amount_total, b.discount_name, b.discount_value,
//            b.discount_amount, b.custom_discount_value
//     FROM patient_service_avail psa
//     JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
//     JOIN patient_info_tbl pi ON psa.patient_id = pi.patient_id
//     LEFT JOIN billing_tbl b ON psa.avail_id = b.avail_id
//     WHERE psa.avail_id = $availId
// ";

       $serviceQuery = "SELECT psa.*, cst.service_name,
    pi.patient_fname, pi.patient_lname, pi.patient_dob, pi.patient_sex, pi.patient_phone,
    psa.package_name,
    b.or_number, b.amount_total, b.discount_name, b.discount_value, b.discount_amount, b.custom_discount_value
FROM patient_service_avail psa
JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
JOIN patient_info_tbl pi ON psa.patient_id = pi.patient_id
LEFT JOIN billing_tbl b ON psa.avail_id = b.avail_id
WHERE psa.avail_id = $availId";




    $serviceResult = mysqli_query($con, $serviceQuery);
    $service = mysqli_fetch_assoc($serviceResult);

    $patient = [
        'name' => trim(($service['patient_fname'] ?? '') . ' ' . ($service['patient_lname'] ?? '')),
        'dob' => $service['patient_dob'] ?? '',
        'sex' => $service['patient_sex'] ?? '',
        'phone' => $service['patient_phone'] ?? ''
    ];

    $procQuery = "
            SELECT 
            psp.procedure_id,
            psp.custom_proc,
            psp.custom_group_proc,
            psp.group_id,
            pt.procedure_name,
            gp.group_name
        FROM patient_service_proc psp
        LEFT JOIN procedure_tbl pt ON psp.procedure_id = pt.procedure_id
        LEFT JOIN procedure_group_tbl gp ON psp.group_id = gp.group_id
        WHERE psp.avail_id = $availId
    ";
    $procResult = mysqli_query($con, $procQuery);
    ?>

    <?php // Here's whats inside the View Service Modal.. below ?>

   <div class="info-card">
    <div class="card-header"><h3>Patient Details</h3></div>
    <div class="info-grid">
        <div class="info-item"><label>Name</label><span><?= htmlspecialchars($service['patient_fname'] . ' ' . $service['patient_lname']) ?></span></div>
        <div class="info-item"><label>DOB</label><span><?= htmlspecialchars($service['patient_dob']) ?></span></div>
        <div class="info-item"><label>Sex</label><span><?= htmlspecialchars($service['patient_sex']) ?></span></div>
        <div class="info-item"><label>Phone</label><span><?= htmlspecialchars($service['patient_phone']) ?></span></div>
    </div>
    </div>


<div class="info-card">
  <div class="card-header"><h3>Service Details</h3></div>
  <div class="info-grid">
    <div class="info-item"><label>Service</label><span><?= htmlspecialchars($service['service_name'] ?? '') ?></span></div>
    <div class="info-item"><label>Requested By</label><span><?= htmlspecialchars($service['requested_by'] ?? '') ?></span></div>
    <div class="info-item"><label>Date Availed</label><span><?= date("Y-m-d - h:i A", strtotime($service['date_availed'])) ?></span></div>
    <div class="info-item"><label>Case No</label><span><?= htmlspecialchars($service['case_no'] ?? '') ?></span></div>
    <div class="info-item"><label>Package Name</label>
    <span>
        <?php
        if (!empty($service['package_name'])) {
            echo htmlspecialchars($service['package_name']);
        } else {
            echo "—";
        }
        ?>
    </span>
    </div>

    <div class="info-item"><label>Brief History</label><span><?= htmlspecialchars($service['brief_history'] ?? '') ?></span></div>
    <div class="info-item"><label>Status</label><span><?= htmlspecialchars($service['status'] ?? '') ?></span></div>
  </div>
</div>

<div class="info-card">
  <div class="card-header"><h3>Procedures</h3></div>
  <div class="info-grid">
    <?php
    
    $procQuery = "
      SELECT 
        psp.procedure_id,
        psp.custom_proc,
        psp.custom_group_proc,
        psp.group_id,
        pt.procedure_name,
        gp.group_name
      FROM patient_service_proc psp
      LEFT JOIN procedure_tbl pt ON psp.procedure_id = pt.procedure_id
      LEFT JOIN procedure_group_tbl gp ON psp.group_id = gp.group_id
      WHERE psp.avail_id = $availId
    ";
    $procResult = mysqli_query($con, $procQuery);

    $customSingle = [];
    $singleProcs = [];
    $groupedProcs = [];
    while ($proc = mysqli_fetch_assoc($procResult)) {
      $pname  = $proc['procedure_name'];
      $cproc  = $proc['custom_proc'];
      $cgproc = $proc['custom_group_proc'];
      $gname  = $proc['group_name'] ?? null;
      $gid    = $proc['group_id'] ?? null;
      if ($cproc && !$pname && (!$gid || $gid == 0)) {
        $customSingle[] = $cproc; continue;
      }
      if ($pname && (!$gid || $gid == 0)) {
        $singleProcs[] = $pname; continue;
      }
      if ($gid && ($pname || $cgproc)) {
        if (!isset($groupedProcs[$gid])) {
          $groupedProcs[$gid] = [
            'group_name' => $gname ?: 'Grouped Procedures',
            'procedures' => [],
            'others'     => null
          ];
        }
        if ($pname) $groupedProcs[$gid]['procedures'][] = $pname;
        if ($cgproc) $groupedProcs[$gid]['others'] = $cgproc;
      }
    }
    // Output custom singles
    foreach ($customSingle as $c) {
      echo '<div class="info-item"><label>Procedure</label><span>' . htmlspecialchars($c) . '</span></div>';
    }
    // Output single procedures
    if (!empty($singleProcs)) {
      echo '<div class="info-item"><label>Single Procedures</label><span>';
      foreach ($singleProcs as $sp) echo htmlspecialchars($sp) . '<br>';
      echo '</span></div>';
    }
    // Output grouped procedures
    foreach ($groupedProcs as $g) {
      echo '<div class="info-item"><label>' . htmlspecialchars($g['group_name']) . '</label><span>';
      if (!empty($g['procedures'])) foreach ($g['procedures'] as $p) echo htmlspecialchars($p) . '<br>';
      if (!empty($g['others'])) echo '<strong>Others:</strong> ' . htmlspecialchars($g['others']);
      echo '</span></div>';
    }
    // Empty fallback
    if (empty($customSingle) && empty($singleProcs) && empty($groupedProcs)) {
      echo '<div class="info-item"><label>Procedures</label><span>None</span></div>';
    }
    ?>
  </div>
</div>


<div class="info-card">
  <div class="card-header"><h3>Billing Details</h3></div>
  <div class="info-grid">
    <div class="info-item"><label>OR #</label><span><?= htmlspecialchars($service['or_number'] ?? '') ?></span></div>
    <div class="info-item"><label>Subtotal</label><span><?= htmlspecialchars($service['amount_total'] ?? '') ?></span></div>
    <div class="info-item"><label>Discount Applied</label>
      <span>
        <?php
        if (!empty($service['discount_name'])) {
          echo htmlspecialchars($service['discount_name']);
          if (!empty($service['discount_value'])) echo ' (' . htmlspecialchars($service['discount_value']) . '%)';
        } elseif (!empty($service['custom_discount_value'])) {
          echo 'Custom (' . htmlspecialchars($service['custom_discount_value']) . '%)';
        } else {
          echo 'None';
        }
        ?>
      </span>
    </div>
    <div class="info-item"><label>Total</label><span><?= htmlspecialchars($service['discount_amount'] ?? $service['amount_total'] ?? '') ?></span></div>
  </div>
</div>


<?php exit; 
}
    

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['patient_id'])) {
    $patientId = intval($_POST['patient_id']);

    $query = "SELECT * FROM patient_info_tbl WHERE patient_id = ? ";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, 'i', $patientId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    // last clinic visit fetch
    // Fetch last visit
    $lastVisitQuery = "
        SELECT psa.case_no, cst.service_name, psa.date_availed, psa.requested_by
        FROM patient_service_avail psa
        JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
        WHERE psa.patient_id = ?
        ORDER BY psa.date_availed DESC
        LIMIT 1
    ";
    $stmtLast = mysqli_prepare($con, $lastVisitQuery);
    mysqli_stmt_bind_param($stmtLast, "i", $patientId);
    mysqli_stmt_execute($stmtLast);
    $lastVisitResult = mysqli_stmt_get_result($stmtLast);
    $lastVisit = mysqli_fetch_assoc($lastVisitResult);

    // Fetch all availed services
    $availServicesQuery = "
        SELECT psa.avail_id, psa.case_no, cst.service_name, psa.date_availed, psa.requested_by
        FROM patient_service_avail psa
        JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
        WHERE psa.patient_id = ?
        ORDER BY psa.date_availed DESC
    ";
    $stmtAvail = mysqli_prepare($con, $availServicesQuery);
    mysqli_stmt_bind_param($stmtAvail, "i", $patientId);
    mysqli_stmt_execute($stmtAvail);
    $availServicesResult = mysqli_stmt_get_result($stmtAvail);

    // =====================fetch service details for modal=====================
    
    

    if ($row = mysqli_fetch_assoc($result)) {
        // continue to render
    } else {
        echo "<h1>Patient not found.</h1>"; exit;
    }

    mysqli_stmt_close($stmt);
} else {
    echo "<h2 style='text-align:center; margin-top: 100px;'>No patient selected. Please go back to the <a href=st_albert.php>dashboard</a>.</h2>";
    exit;
}

?>

<!-- <link rel="stylesheet" href="../css/view_account.css"> -->

<div class="page-title d-flex justify-content-between align-items-center">
    <div class="title">View Patient</div>
</div>

<!-- Patient Info Box -->
<div class="info-card">
    <div class="card-header">
        <h3><i class="fas fa-user-injured"></i> Patient Information</h3>
        <!-- <button class="btn btn-outline btn-sm" id="editBtn"><i class="fas fa-pen"></i> Edit</button> -->
    </div>
    <div class="info-grid">
        <div class="info-item"><label>First Name</label><span><?= htmlspecialchars($row['patient_fname']) ?></span></div>
        <div class="info-item"><label>Middle Name</label><span><?= htmlspecialchars($row['patient_mname']) ?></span></div>
        <div class="info-item"><label>Last Name</label><span><?= htmlspecialchars($row['patient_lname']) ?></span></div>
        <div class="info-item"><label>Sex</label><span><?= htmlspecialchars($row['patient_sex']) ?></span></div>
        <div class="info-item"><label>Date of Birth</label><span><?= date("F d, Y", strtotime($row['patient_dob'])) ?></span></div>
        <div class="info-item"><label>Phone Number</label><span><?= htmlspecialchars($row['patient_phone']) ?></span></div>
        <div class="info-item"><label>Address</label><span><?= htmlspecialchars($row['patient_home_add']) ?></span></div>
    </div>
</div>

<div class="info-card">
    <div class="card-header">
        <h3><i class="fas fa-calendar-plus"></i> Additional Info</h3>
    </div>
    <div class="info-grid">
        <div class="info-item"><label>Date Added</label><span><?= date("M d, Y H:i:s a", strtotime($row['patient_added'])) ?></span></div>

        <!-- Archive Button -->
    </div>
</div>

 <div class="info-card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-plus"></i> Last Visit </h3>
        </div>
         <?php if ($lastVisit): ?>
        <div class="info-grid">
            <div class="info-item"><label>Service</label><span><?= htmlspecialchars($lastVisit['service_name']) ?></span></div>
            <div class="info-item"><label>Requested By</label><span><?= htmlspecialchars($lastVisit['requested_by']) ?></span></div>
            <div class="info-item"><label>Date & Time</label><span><?= date("Y-m-d - h:i A", strtotime($lastVisit['date_availed'])) ?></span></div>
        </div>
    <?php else: ?>
        <p style="padding: 10px;">No service availed yet.</p>
    <?php endif; ?>
</div>

    <div class="info-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Availed Services</h3>
        </div>
        <!-- table class data-table is used same as all other tables -->
        <table class="data-table">
            <thead>
                <tr>
                    <th>Case No.</th>
                    <th>Service Name</th>
                    <th>Date</th>
                    <th>Physician</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($availServicesResult) > 0): ?>
                <?php while ($service = mysqli_fetch_assoc($availServicesResult)): ?>
                    <tr>
                        <td><?= htmlspecialchars($service['case_no'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($service['service_name']) ?></td>
                        <td><?= date("Y-m-d - h:i A", strtotime($service['date_availed'])) ?></td>
                        <td><?= htmlspecialchars($service['requested_by']) ?></td>
                        <td><a href="#" class="view-service" data-avail-id="<?= $service['avail_id'] ?>">View</a></td>
                       
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5">No availed services yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- View Availed Service Modal -->
    <div id="viewServiceModal" class="modal">
        <div class="modal-content">
            <!-- <h3>Service Details</h3> -->
            <div id="serviceDetails">
                <!-- We'll load content here dynamically -->
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn btn-outline" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

<script src="javs/view_account.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
     function setTbodyMaxHeight() {
      const card = document.querySelector('.table-card');
      const thead = card.querySelector('thead');
      const tbody = card.querySelector('tbody');
      if (!card || !thead || !tbody) return;

      const cardHeight = card.clientHeight;
      const theadHeight = thead.offsetHeight;
      const paddingOffset = 0; // adjust if you have padding/margin in .table-card

      const maxHeight = cardHeight - theadHeight - paddingOffset;
      tbody.style.maxHeight = maxHeight + 'px';
    }

      // Run on page load and window resize
      window.addEventListener('load', setTbodyMaxHeight);
      window.addEventListener('resize', setTbodyMaxHeight);

</script>

<?php
}
?>



<?php 
    // ======================================RECEPTIONIST PART======================================
if($_SESSION['role'] == 'Receptionist') { 
    requireRole('Receptionist');

    if (isset($_POST['avail_id'])) {
    $availId = intval($_POST['avail_id']);

//    $serviceQuery = "
//             SELECT psa.*, cst.service_name,
//                 pi.patient_fname, pi.patient_lname, pi.patient_dob, pi.patient_sex, pi.patient_phone,
//                 psa.package_name
//             FROM patient_service_avail psa
//             JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
//             JOIN patient_info_tbl pi ON psa.patient_id = pi.patient_id
//             LEFT JOIN billing_tbl b ON psa.avail_id = b.avail_id
//             WHERE psa.avail_id = $availId
//         ";

       $serviceQuery = " SELECT psa.*, cst.service_name,
            pi.patient_fname, pi.patient_lname, pi.patient_dob, pi.patient_sex, pi.patient_phone,
            psa.package_name,
            b.or_number, b.amount_total, b.discount_name, b.discount_value, b.discount_amount, b.custom_discount_value
        FROM patient_service_avail psa
        JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
        JOIN patient_info_tbl pi ON psa.patient_id = pi.patient_id
        LEFT JOIN billing_tbl b ON psa.avail_id = b.avail_id
        WHERE psa.avail_id = $availId";


    $serviceResult = mysqli_query($con, $serviceQuery);
    $service = mysqli_fetch_assoc($serviceResult);

    $patient = [
        'name' => trim(($service['patient_fname'] ?? '') . ' ' . ($service['patient_lname'] ?? '')),
        'dob' => $service['patient_dob'] ?? '',
        'sex' => $service['patient_sex'] ?? '',
        'phone' => $service['patient_phone'] ?? ''
    ];

    $procQuery = "
            SELECT 
            psp.procedure_id,
            psp.custom_proc,
            psp.custom_group_proc,
            psp.group_id,
            pt.procedure_name,
            gp.group_name
        FROM patient_service_proc psp
        LEFT JOIN procedure_tbl pt ON psp.procedure_id = pt.procedure_id
        LEFT JOIN procedure_group_tbl gp ON psp.group_id = gp.group_id
        WHERE psp.avail_id = $availId
    ";
    $procResult = mysqli_query($con, $procQuery);
    ?>

    <?php // Here's whats inside the View Service Modal.. below ?>

   <div class="info-card">
    <div class="card-header"><h3>Patient Details</h3></div>
    <div class="info-grid">
        <div class="info-item"><label>Name</label><span><?= htmlspecialchars($service['patient_fname'] . ' ' . $service['patient_lname']) ?></span></div>
        <div class="info-item"><label>DOB</label><span><?= htmlspecialchars($service['patient_dob']) ?></span></div>
        <div class="info-item"><label>Sex</label><span><?= htmlspecialchars($service['patient_sex']) ?></span></div>
        <div class="info-item"><label>Phone</label><span><?= htmlspecialchars($service['patient_phone']) ?></span></div>
    </div>
    </div>


<div class="info-card">
  <div class="card-header"><h3>Service Details</h3></div>
  <div class="info-grid">
    <div class="info-item"><label>Service</label><span><?= htmlspecialchars($service['service_name'] ?? '') ?></span></div>
    <div class="info-item"><label>Requested By</label><span><?= htmlspecialchars($service['requested_by'] ?? '--') ?></span></div>
    <div class="info-item"><label>Date Availed</label><span><?= date("Y-m-d - h:i A", strtotime($service['date_availed'])) ?></span></div>
    <div class="info-item"><label>Case No</label><span><?= htmlspecialchars($service['case_no'] ?? '') ?></span></div>
    <div class="info-item"><label>Package Name</label>
  <span>
    <?php
      if (!empty($service['package_name'])) {
        echo htmlspecialchars($service['package_name']);
      } else {
        echo "—";
      }
    ?>
  </span>
</div>

    <div class="info-item"><label>Brief History</label><span><?= htmlspecialchars($service['brief_history'] ?? '--') ?></span></div>
    <div class="info-item"><label>Status</label><span><?= htmlspecialchars($service['status'] ?? '') ?></span></div>
  </div>
</div>

<div class="info-card">
  <div class="card-header"><h3>Procedures</h3></div>
  <div class="info-grid">
    <?php
    
    $procQuery = "
      SELECT 
        psp.procedure_id,
        psp.custom_proc,
        psp.custom_group_proc,
        psp.group_id,
        pt.procedure_name,
        gp.group_name
      FROM patient_service_proc psp
      LEFT JOIN procedure_tbl pt ON psp.procedure_id = pt.procedure_id
      LEFT JOIN procedure_group_tbl gp ON psp.group_id = gp.group_id
      WHERE psp.avail_id = $availId
    ";
    $procResult = mysqli_query($con, $procQuery);

    $customSingle = [];
    $singleProcs = [];
    $groupedProcs = [];
    while ($proc = mysqli_fetch_assoc($procResult)) {
      $pname  = $proc['procedure_name'];
      $cproc  = $proc['custom_proc'];
      $cgproc = $proc['custom_group_proc'];
      $gname  = $proc['group_name'] ?? null;
      $gid    = $proc['group_id'] ?? null;
      if ($cproc && !$pname && (!$gid || $gid == 0)) {
        $customSingle[] = $cproc; continue;
      }
      if ($pname && (!$gid || $gid == 0)) {
        $singleProcs[] = $pname; continue;
      }
      if ($gid && ($pname || $cgproc)) {
        if (!isset($groupedProcs[$gid])) {
          $groupedProcs[$gid] = [
            'group_name' => $gname ?: 'Grouped Procedures',
            'procedures' => [],
            'others'     => null
          ];
        }
        if ($pname) $groupedProcs[$gid]['procedures'][] = $pname;
        if ($cgproc) $groupedProcs[$gid]['others'] = $cgproc;
      }
    }
    // Output custom singles
    foreach ($customSingle as $c) {
      echo '<div class="info-item"><label>Procedure</label><span>' . htmlspecialchars($c) . '</span></div>';
    }
    // Output single procedures
    if (!empty($singleProcs)) {
      echo '<div class="info-item"><label>Single Procedures</label><span>';
      foreach ($singleProcs as $sp) echo htmlspecialchars($sp) . '<br>';
      echo '</span></div>';
    }
    // Output grouped procedures
    foreach ($groupedProcs as $g) {
      echo '<div class="info-item"><label>' . htmlspecialchars($g['group_name']) . '</label><span>';
      if (!empty($g['procedures'])) foreach ($g['procedures'] as $p) echo htmlspecialchars($p) . '<br>';
      if (!empty($g['others'])) echo '<strong>Others:</strong> ' . htmlspecialchars($g['others']);
      echo '</span></div>';
    }
    // Empty fallback
    if (empty($customSingle) && empty($singleProcs) && empty($groupedProcs)) {
      echo '<div class="info-item"><label>Procedures</label><span>None</span></div>';
    }
    ?>
  </div>
</div>


<div class="info-card">
  <div class="card-header"><h3>Billing Details</h3></div>
  <div class="info-grid">
    <div class="info-item"><label>OR #</label><span><?= htmlspecialchars($service['or_number'] ?? '') ?></span></div>
    <div class="info-item"><label>Subtotal</label><span><?= htmlspecialchars($service['amount_total'] ?? '') ?></span></div>
    <div class="info-item"><label>Discount Applied</label>
      <span>
        <?php
        if (!empty($service['discount_name'])) {
          echo htmlspecialchars($service['discount_name']);
          if (!empty($service['discount_value'])) echo ' (' . htmlspecialchars($service['discount_value']) . '%)';
        } elseif (!empty($service['custom_discount_value'])) {
          echo 'Custom (' . htmlspecialchars($service['custom_discount_value']) . '%)';
        } else {
          echo 'None';
        }
        ?>
      </span>
    </div>
    <div class="info-item"><label>Total</label><span><?= htmlspecialchars($service['discount_amount'] ?? $service['amount_total'] ?? '') ?></span></div>
  </div>
</div>


<?php exit; 

}

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_patient'])) {
        $patientId = intval($_POST['patient_id']);
        $patient_fname = trim(mysqli_real_escape_string($con, $_POST['patient_fname']));
        $patient_mname = trim(mysqli_real_escape_string($con, $_POST['patient_mname']));
        $patient_lname = trim(mysqli_real_escape_string($con, $_POST['patient_lname']));
        $patient_sex = trim(mysqli_real_escape_string($con, $_POST['patient_sex']));
        $patient_dob = trim(mysqli_real_escape_string($con, $_POST['patient_dob']));
        $patient_phone = trim(mysqli_real_escape_string($con, $_POST['patient_phone']));
        $patient_home_add = trim(mysqli_real_escape_string($con, $_POST['patient_home_add']));

        $namePattern = "/^[a-zA-Z\s\.]+$/";
        if (!preg_match($namePattern, $patient_fname)) { 
            echo 'Invalid First Name'; 
            exit; 
        }
        if (!preg_match($namePattern, $patient_mname)) { 
            echo 'Invalid Middle Name'; 
            exit; 
        }
        if (!preg_match($namePattern, $patient_lname)) { 
            echo 'Invalid Last Name'; 
            exit; 
        }

        if ($patient_sex !== 'Male' && $patient_sex !== 'Female') {
            echo 'Invalid Sex value'; exit;
        }

        if (!preg_match('/^\d{11}$/', $patient_phone)) {
            echo 'Phone number must be 11 digits.'; exit;
        }

        if (!DateTime::createFromFormat('Y-m-d', $patient_dob)) {
            echo 'Invalid Date of Birth format'; exit;
        }

        $query = "UPDATE patient_info_tbl SET 
                    patient_fname = ?, 
                    patient_mname = ?, 
                    patient_lname = ?, 
                    patient_sex = ?, 
                    patient_dob = ?, 
                    patient_phone = ?, 
                    patient_home_add = ? 
                WHERE patient_id = ?";

        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, "sssssssi", 
            $patient_fname, $patient_mname, $patient_lname, $patient_sex,
            $patient_dob, $patient_phone, $patient_home_add, $patientId);

        if (mysqli_stmt_execute($stmt)) {
            echo "success"; exit;
        } else {
            echo "Error updating patient: " . mysqli_error($con); exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['patient_id'])) {
        $patientId = intval($_POST['patient_id']);

        $query = "SELECT * FROM patient_info_tbl WHERE patient_id = ?";
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, 'i', $patientId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        // last clinic visit fetch
    // Fetch last visit
    $lastVisitQuery = "
        SELECT psa.case_no, cst.service_name, psa.date_availed, psa.requested_by, psa.status
        FROM patient_service_avail psa
        JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
        WHERE psa.patient_id = ?
        ORDER BY psa.date_availed DESC
        LIMIT 1
    ";
    $stmtLast = mysqli_prepare($con, $lastVisitQuery);
    mysqli_stmt_bind_param($stmtLast, "i", $patientId);
    mysqli_stmt_execute($stmtLast);
    $lastVisitResult = mysqli_stmt_get_result($stmtLast);
    $lastVisit = mysqli_fetch_assoc($lastVisitResult);

    // Fetch all availed services
    $availServicesQuery = "
        SELECT psa.avail_id, psa.case_no, cst.service_name, psa.date_availed, psa.requested_by, psa.status
        FROM patient_service_avail psa
        JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
        WHERE psa.patient_id = ?
        ORDER BY psa.date_availed DESC
    ";
    $stmtAvail = mysqli_prepare($con, $availServicesQuery);
    mysqli_stmt_bind_param($stmtAvail, "i", $patientId);
    mysqli_stmt_execute($stmtAvail);
    $availServicesResult = mysqli_stmt_get_result($stmtAvail);

        if ($row = mysqli_fetch_assoc($result)) {
            // continue to render
        } else {
            echo "<h1>Patient not found.</h1>"; exit;
        }

        mysqli_stmt_close($stmt);
    } else {
        echo "<h2 style='text-align:center; margin-top: 100px;'>No patient selected. Please go back to the <a href=st_albert.php>dashboard</a>.</h2>";
        exit;
    }

    // ARCHIVE
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_patient'])) {
        $idToArchive = intval($_POST['patient_id']);
        $query = "UPDATE patient_info_tbl SET is_archived = 1 WHERE patient_id = ?";
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, "i", $idToArchive);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success', 'message' => 'Patient archived successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error archiving patient: ' . mysqli_error($con)]);
        }
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_patient'])) {
        $idToRestore = intval($_POST['patient_id']);
        $query = "UPDATE patient_info_tbl SET is_archived = 0 WHERE patient_id = ?";
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, "i", $idToRestore);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success', 'message' => 'Patient restored successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error restoring patient: ' . mysqli_error($con)]);
        }
        exit;
    }



    ?>

    <!-- <link rel="stylesheet" href="../css/view_account.css"> -->

    <div class="page-title d-flex justify-content-between align-items-center">
        <div class="title">View Patient</div>
    </div>

    <!-- Patient Info Box -->
    <div class="info-card">
        <div class="card-header">
            <h3><i class="fas fa-user-injured"></i> Patient Information</h3>
            <button class="btn btn-outline btn-sm" id="editPatientBtn"><i class="fas fa-pen"></i> Edit</button>
        </div>
        <div class="info-grid">
            <div class="info-item"><label>First Name</label><span><?= htmlspecialchars($row['patient_fname']) ?></span></div>
            <div class="info-item"><label>Middle Name</label><span><?= htmlspecialchars($row['patient_mname']) ?></span></div>
            <div class="info-item"><label>Last Name</label><span><?= htmlspecialchars($row['patient_lname']) ?></span></div>
            <div class="info-item"><label>Sex</label><span><?= htmlspecialchars($row['patient_sex']) ?></span></div>
            <div class="info-item"><label>Date of Birth</label><span><?= date("F d, Y", strtotime($row['patient_dob'])) ?></span></div>
            <div class="info-item"><label>Phone Number</label><span><?= htmlspecialchars($row['patient_phone']) ?></span></div>
            <div class="info-item"><label>Address</label><span><?= htmlspecialchars($row['patient_home_add']) ?></span></div>
        </div>
    </div>

    <div class="info-card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-plus"></i> Additional Info</h3>
        </div>
        <div class="info-grid">
            <div class="info-item"><label>Date Added</label><span><?= date("M d, Y H:i:s a", strtotime($row['patient_added'])) ?></span></div>

            <!-- Archive Button -->
            <?php if ($row['is_archived'] == 1): ?>
                <form class="restorePatientForm">
                    <input type="hidden" name="patient_id" value="<?= htmlspecialchars($patientId) ?>">
                    <button type="submit" name="restore_patient" class="btn btn-primary">
                        <i class="fas fa-undo"></i> Restore Patient
                    </button>
                </form>
            <?php else: ?>
                <form class="archivePatientForm">
                    <input type="hidden" name="patient_id" value="<?= htmlspecialchars($patientId) ?>">
                    <button type="submit" name="archive_patient" class="btn btn-primary ">Archive Patient</button>
                </form>
            <?php endif; ?>
        </div>
    </div>


     <div class="info-card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-plus"></i> Last Visit </h3>
        </div>
         <?php if ($lastVisit): ?>
        <div class="info-grid">
            <div class="info-item"><label>Service</label><span><?= htmlspecialchars($lastVisit['service_name']) ?></span></div>
            <div class="info-item"><label>Requested By</label><span><?= htmlspecialchars($lastVisit['requested_by'] ?? '--') ?></span></div>
            <div class="info-item"><label>Date & Time</label><span><?= date("F d, Y h:i A", strtotime($lastVisit['date_availed'])) ?></span></div>
             <div class="info-item"><label>Status</label><span><?= htmlspecialchars($lastVisit['status']) ?></span></div>
        </div>
    <?php else: ?>
        <p style="padding: 10px;">No service availed yet.</p>
    <?php endif; ?>
</div>

    <div class="info-card">
       <div class="card-header">
            <h3><i class="fas fa-list"></i> Availed Services</h3>
            <?php if ($row['is_archived'] != 1): ?>
                <button class="btn btn-primary" id="availServiceBtn" data-patient-id="<?= $patientId ?>">
                    <i class="fas fa-plus"></i> Avail New Service
                </button>
            <?php endif; ?>
    
</button>

        </div>
        <!-- table class data-table is used same as all other tables -->
        <table class="data-table">
            <thead>
                <tr>
                    <th>Case No.</th>
                    <th>Service Name</th>
                    <th>Date</th>
                    <th>Physician</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($availServicesResult) > 0): ?>
                <?php while ($service = mysqli_fetch_assoc($availServicesResult)): ?>
                    <tr>
                        <td><?= htmlspecialchars($service['case_no'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($service['service_name']) ?></td>
                        <td><?= date("M d, Y h:i A", strtotime($service['date_availed'])) ?></td>
                        <td><?= htmlspecialchars($service['requested_by'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($service['status'] ?? '—') ?></td>
                        <td><a href="#" class="view-service" data-avail-id="<?= $service['avail_id'] ?>">View</a></td>
                       
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5">No availed services yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- View Availed Service Modal -->
    <div id="viewServiceModal" class="modal">
        <div class="modal-content">
            <div id="serviceDetails">
                <!-- We'll load content here dynamically -->
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn btn-outline" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>


    <!-- Edit Modal -->
    <div id="editPatientModal" class="modal">
        <div class="modal-content">
            <h3>Edit Patient Information</h3>
            <form id="editPatientForm" method="POST" action="view_patient.php">
                <input type="hidden" id="patientId" name="patient_id" value="<?= htmlspecialchars($patientId) ?>">
                <input type="hidden" name="edit_patient" value="1">

                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" id="patientFirstName" name="patient_fname" value="<?= htmlspecialchars($row['patient_fname']) ?>" required pattern="[A-Za-z\s\-\.]+" maxlength="50">
                </div>
                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" id="patientMiddleName" name="patient_mname" value="<?= htmlspecialchars($row['patient_mname']) ?>" pattern="[A-Za-z\s\-\.]*" maxlength="50">
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" id="patientLastName" name="patient_lname" value="<?= htmlspecialchars($row['patient_lname']) ?>" required pattern="[A-Za-z\s\-\.]+" maxlength="50">
                </div>
                <div class="form-group">
                    <label>Sex</label>
                    <select id="patientSex" name="patient_sex" required>
                        <option value="Male" <?= $row['patient_sex'] === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $row['patient_sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" id="patientDob" name="patient_dob" value="<?= htmlspecialchars($row['patient_dob']) ?>">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" id="patientPhone" name="patient_phone" value="<?= htmlspecialchars($row['patient_phone']) ?>" pattern="\d{11}" maxlength="11">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" id="patientAddress" name="patient_home_add" value="<?= htmlspecialchars($row['patient_home_add']) ?>" maxlength="255">
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-outline" id="closeEditPatientModalBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- create a similar script for view_patient -->
    <script src="js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php
}
?>

<?php
    // ===================CLINIC PERSONNEL========================
     if (in_array($_SESSION['role'], [
            'Laboratory Personnel',
            'Ultrasound Personnel',
            '2D Echo Personnel',
            'ECG Personnel',
            'X-RAY Personnel'
        ])) {

    if (isset($_POST['avail_id'])) {
    $availId = intval($_POST['avail_id']);

    $role_id = $_SESSION['role_id'] ?? 0;
    $allowedServices = [];
    $svcRes = mysqli_query($con, "SELECT service_id FROM clinic_service_tbl WHERE role_id = $role_id");
    while ($svcRow = mysqli_fetch_assoc($svcRes)) {
        $allowedServices[] = $svcRow['service_id'];
    }
    $allowedCsv = $allowedServices ? implode(',', $allowedServices) : '0';

//     $serviceQuery = "
//     SELECT psa.*, cst.service_name,
//            pi.patient_fname, pi.patient_lname, pi.patient_dob, pi.patient_sex, pi.patient_phone,
//            b.or_number, b.amount_total, b.discount_name, b.discount_value,
//            b.discount_amount, b.custom_discount_value,
//                 psa.package_name
//     FROM patient_service_avail psa
//     JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
//     JOIN patient_info_tbl pi ON psa.patient_id = pi.patient_id
//     LEFT JOIN billing_tbl b ON psa.avail_id = b.avail_id
//     WHERE psa.avail_id = $availId
// ";

    $serviceQuery = "SELECT psa.*, cst.service_name,
        pi.patient_fname, pi.patient_lname, pi.patient_dob, pi.patient_sex, pi.patient_phone,
        psa.package_name,
        b.or_number, b.amount_total, b.discount_name, b.discount_value, b.discount_amount, b.custom_discount_value
    FROM patient_service_avail psa
    JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
    JOIN patient_info_tbl pi ON psa.patient_id = pi.patient_id
    LEFT JOIN billing_tbl b ON psa.avail_id = b.avail_id
    WHERE psa.avail_id = $availId";


    $serviceResult = mysqli_query($con, $serviceQuery);
    $service = mysqli_fetch_assoc($serviceResult);

    $patient = [
        'name' => trim(($service['patient_fname'] ?? '') . ' ' . ($service['patient_lname'] ?? '')),
        'dob' => $service['patient_dob'] ?? '',
        'sex' => $service['patient_sex'] ?? '',
        'phone' => $service['patient_phone'] ?? ''
    ];

    $procQuery = "
            SELECT 
            psp.procedure_id,
            psp.custom_proc,
            psp.custom_group_proc,
            psp.group_id,
            pt.procedure_name,
            gp.group_name
        FROM patient_service_proc psp
        LEFT JOIN procedure_tbl pt ON psp.procedure_id = pt.procedure_id
        LEFT JOIN procedure_group_tbl gp ON psp.group_id = gp.group_id
        WHERE psp.avail_id = $availId
    ";
    $procResult = mysqli_query($con, $procQuery);
    ?>

    <?php // Here's whats inside the View Service Modal.. below ?>

   <div class="info-card">
    <div class="card-header"><h3>Patient Details</h3></div>
    <div class="info-grid">
        <div class="info-item"><label>Name</label><span><?= htmlspecialchars($service['patient_fname'] . ' ' . $service['patient_lname']) ?></span></div>
        <div class="info-item"><label>DOB</label><span><?= htmlspecialchars($service['patient_dob']) ?></span></div>
        <div class="info-item"><label>Sex</label><span><?= htmlspecialchars($service['patient_sex']) ?></span></div>
        <div class="info-item"><label>Phone</label><span><?= htmlspecialchars($service['patient_phone']) ?></span></div>
    </div>
    </div>


<div class="info-card">
  <div class="card-header"><h3>Service Details</h3></div>
  <div class="info-grid">
    <div class="info-item"><label>Service</label><span><?= htmlspecialchars($service['service_name'] ?? '') ?></span></div>
    <div class="info-item"><label>Requested By</label><span><?= htmlspecialchars($service['requested_by'] ?? '') ?></span></div>
    <div class="info-item"><label>Date Availed</label><span><?= date("Y-m-d - h:i A", strtotime($service['date_availed'])) ?></span></div>
    <div class="info-item"><label>Case No</label><span><?= htmlspecialchars($service['case_no'] ?? '') ?></span></div>
    <div class="info-item"><label>Package Name</label>
  <span>
    <?php
      if (!empty($service['package_name'])) {
        echo htmlspecialchars($service['package_name']);
      } else {
        echo "—";
      }
    ?>
  </span>
</div>


    <div class="info-item"><label>Brief History</label><span><?= htmlspecialchars($service['brief_history'] ?? '') ?></span></div>
    <div class="info-item"><label>Status</label><span><?= htmlspecialchars($service['status'] ?? '') ?></span></div>
  </div>
</div>

<div class="info-card">
  <div class="card-header"><h3>Procedures</h3></div>
  <div class="info-grid">
    <?php
    
    $procQuery = "
      SELECT 
        psp.procedure_id,
        psp.custom_proc,
        psp.custom_group_proc,
        psp.group_id,
        pt.procedure_name,
        gp.group_name
      FROM patient_service_proc psp
      LEFT JOIN procedure_tbl pt ON psp.procedure_id = pt.procedure_id
      LEFT JOIN procedure_group_tbl gp ON psp.group_id = gp.group_id
      WHERE psp.avail_id = $availId
    ";
    $procResult = mysqli_query($con, $procQuery);

    $customSingle = [];
    $singleProcs = [];
    $groupedProcs = [];
    while ($proc = mysqli_fetch_assoc($procResult)) {
      $pname  = $proc['procedure_name'];
      $cproc  = $proc['custom_proc'];
      $cgproc = $proc['custom_group_proc'];
      $gname  = $proc['group_name'] ?? null;
      $gid    = $proc['group_id'] ?? null;
      if ($cproc && !$pname && (!$gid || $gid == 0)) {
        $customSingle[] = $cproc; continue;
      }
      if ($pname && (!$gid || $gid == 0)) {
        $singleProcs[] = $pname; continue;
      }
      if ($gid && ($pname || $cgproc)) {
        if (!isset($groupedProcs[$gid])) {
          $groupedProcs[$gid] = [
            'group_name' => $gname ?: 'Grouped Procedures',
            'procedures' => [],
            'others'     => null
          ];
        }
        if ($pname) $groupedProcs[$gid]['procedures'][] = $pname;
        if ($cgproc) $groupedProcs[$gid]['others'] = $cgproc;
      }
    }
    // Output custom singles
    foreach ($customSingle as $c) {
      echo '<div class="info-item"><label>Procedure</label><span>' . htmlspecialchars($c) . '</span></div>';
    }
    // Output single procedures
    if (!empty($singleProcs)) {
      echo '<div class="info-item"><label>Single Procedures</label><span>';
      foreach ($singleProcs as $sp) echo htmlspecialchars($sp) . '<br>';
      echo '</span></div>';
    }
    // Output grouped procedures
    foreach ($groupedProcs as $g) {
      echo '<div class="info-item"><label>' . htmlspecialchars($g['group_name']) . '</label><span>';
      if (!empty($g['procedures'])) foreach ($g['procedures'] as $p) echo htmlspecialchars($p) . '<br>';
      if (!empty($g['others'])) echo '<strong>Others:</strong> ' . htmlspecialchars($g['others']);
      echo '</span></div>';
    }
    // Empty fallback
    if (empty($customSingle) && empty($singleProcs) && empty($groupedProcs)) {
      echo '<div class="info-item"><label>Procedures</label><span>None</span></div>';
    }
    ?>
  </div>
</div>


<div class="info-card">
  <div class="card-header"><h3>Billing Details</h3></div>
  <div class="info-grid">
    <div class="info-item"><label>OR #</label><span><?= htmlspecialchars($service['or_number'] ?? '') ?></span></div>
    <div class="info-item"><label>Subtotal</label><span><?= htmlspecialchars($service['amount_total'] ?? '') ?></span></div>
    <div class="info-item"><label>Discount Applied</label>
      <span>
        <?php
        if (!empty($service['discount_name'])) {
          echo htmlspecialchars($service['discount_name']);
          if (!empty($service['discount_value'])) echo ' (' . htmlspecialchars($service['discount_value']) . '%)';
        } elseif (!empty($service['custom_discount_value'])) {
          echo 'Custom (' . htmlspecialchars($service['custom_discount_value']) . '%)';
        } else {
          echo 'None';
        }
        ?>
      </span>
    </div>
    <div class="info-item"><label>Total</label><span><?= htmlspecialchars($service['discount_amount'] ?? $service['amount_total'] ?? '') ?></span></div>
  </div>
</div>


<?php exit; 
}
    

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['patient_id'])) {
    $patientId = intval($_POST['patient_id']);

    $isPersonnel = in_array($_SESSION['role'], [
        'Laboratory Personnel',
        'Ultrasound Personnel',
        '2D Echo Personnel',
        'ECG Personnel',
        'X-RAY Personnel'
    ]);

    $serviceFilter = "";
    if ($isPersonnel) {
        $role_id = $_SESSION['role_id'] ?? 0;
        $allowedServices = [];
        $svcRes = mysqli_query($con, "SELECT service_id FROM clinic_service_tbl WHERE role_id = $role_id");

        while ($svcRow = mysqli_fetch_assoc($svcRes)) {
            $allowedServices[] = $svcRow['service_id'];
        }
        $allowedCsv = $allowedServices ? implode(',', $allowedServices) : '0';
        $serviceFilter = "AND psa.service_id IN ($allowedCsv)";
    }

    $query = "SELECT * FROM patient_info_tbl WHERE patient_id = ? AND is_archived = 0";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, 'i', $patientId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    // last clinic visit fetch
    // Fetch last visit
    $lastVisitQuery = "
        SELECT psa.case_no, cst.service_name, psa.date_availed, psa.requested_by, psa.status
        FROM patient_service_avail psa
        JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
        WHERE psa.patient_id = ? 
        $serviceFilter
        ORDER BY psa.date_availed DESC
        LIMIT 1
    ";
    $stmtLast = mysqli_prepare($con, $lastVisitQuery);
    mysqli_stmt_bind_param($stmtLast, "i", $patientId);
    mysqli_stmt_execute($stmtLast);
    $lastVisitResult = mysqli_stmt_get_result($stmtLast);
    $lastVisit = mysqli_fetch_assoc($lastVisitResult);



    // Fetch all availed services
    $availServicesQuery = "
        SELECT psa.avail_id, psa.case_no, cst.service_name, psa.date_availed, psa.requested_by
        FROM patient_service_avail psa
        JOIN clinic_service_tbl cst ON psa.service_id = cst.service_id
        WHERE psa.patient_id = ?
        $serviceFilter
        ORDER BY psa.date_availed DESC
    ";
    $stmtAvail = mysqli_prepare($con, $availServicesQuery);
    mysqli_stmt_bind_param($stmtAvail, "i", $patientId);
    mysqli_stmt_execute($stmtAvail);
    $availServicesResult = mysqli_stmt_get_result($stmtAvail);

    // =====================fetch service details for modal=====================
    
    

    if ($row = mysqli_fetch_assoc($result)) {
        // continue to render
    } else {
        echo "<h1>Patient not found.</h1>"; exit;
    }

    mysqli_stmt_close($stmt);
} else {
    echo "<h2 style='text-align:center; margin-top: 100px;'>No patient selected. Please go back to the <a href=st_albert.php>dashboard</a>.</h2>";
    exit;
}

?>

<!-- <link rel="stylesheet" href="../css/view_account.css"> -->

<div class="page-title d-flex justify-content-between align-items-center">
    <div class="title">View Patient</div>
</div>

<!-- Patient Info Box -->
<div class="info-card">
    <div class="card-header">
        <h3><i class="fas fa-user-injured"></i> Patient Information</h3>
        <!-- <button class="btn btn-outline btn-sm" id="editBtn"><i class="fas fa-pen"></i> Edit</button> -->
    </div>
    <div class="info-grid">
        <div class="info-item"><label>First Name</label><span><?= htmlspecialchars($row['patient_fname']) ?></span></div>
        <div class="info-item"><label>Middle Name</label><span><?= htmlspecialchars($row['patient_mname']) ?></span></div>
        <div class="info-item"><label>Last Name</label><span><?= htmlspecialchars($row['patient_lname']) ?></span></div>
        <div class="info-item"><label>Sex</label><span><?= htmlspecialchars($row['patient_sex']) ?></span></div>
        <div class="info-item"><label>Date of Birth</label><span><?= date("F d, Y", strtotime($row['patient_dob'])) ?></span></div>
        <div class="info-item"><label>Phone Number</label><span><?= htmlspecialchars($row['patient_phone']) ?></span></div>
        <div class="info-item"><label>Address</label><span><?= htmlspecialchars($row['patient_home_add']) ?></span></div>
    </div>
</div>

<div class="info-card">
    <div class="card-header">
        <h3><i class="fas fa-calendar-plus"></i> Additional Info</h3>
    </div>
    <div class="info-grid">
        <div class="info-item"><label>Date Added</label><span><?= date("M d, Y H:i:s a", strtotime($row['patient_added'])) ?></span></div>

        <!-- Archive Button -->
    </div>
</div>

 <div class="info-card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-plus"></i> Last Visit </h3>
        </div>
         <?php if ($lastVisit): ?>
        <div class="info-grid">
            <div class="info-item"><label>Service</label><span><?= htmlspecialchars($lastVisit['service_name']) ?></span></div>
            <div class="info-item"><label>Requested By</label><span><?= htmlspecialchars($lastVisit['requested_by'] ?? '--') ?></span></div>
            <div class="info-item"><label>Date & Time</label><span><?= date("Y-m-d - h:i A", strtotime($lastVisit['date_availed'])) ?></span></div>
            <div class="info-item"><label>Status</label><span><?= htmlspecialchars($lastVisit['status']) ?></span></div>

        </div>
    <?php else: ?>
        <p style="padding: 10px;">No service availed yet.</p>
    <?php endif; ?>
</div>

    <div class="info-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Availed Services</h3>
        </div>
        <!-- table class data-table is used same as all other tables -->
        <table class="data-table">
            <thead>
                <tr>
                    <th>Case No.</th>
                    <th>Service Name</th>
                    <th>Date</th>
                    <th>Physician</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($availServicesResult) > 0): ?>
                <?php while ($service = mysqli_fetch_assoc($availServicesResult)): ?>
                    <tr>
                        <td><?= htmlspecialchars($service['case_no'] ?? '--') ?></td>
                        <td><?= htmlspecialchars($service['service_name']) ?></td>
                        <td><?= date("Y-m-d - h:i A", strtotime($service['date_availed'])) ?></td>
                        <td><?= htmlspecialchars($service['requested_by'] ?? '--') ?></td>
                        <td><a href="#" class="view-service" data-avail-id="<?= $service['avail_id'] ?>">View</a></td>
                       
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5">No availed services yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- View Availed Service Modal -->
    <div id="viewServiceModal" class="modal">
        <div class="modal-content">
            <!-- <h3>Service Details</h3> -->
            <div id="serviceDetails">
                <!-- We'll load content here dynamically -->
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn btn-outline" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

<script src="javs/view_account.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php
        }
?>