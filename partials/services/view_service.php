<?php
require_once '../../includes/connection.php';
require_once '../../includes/session_check.php';
requireRole(['Administrator', 'Receptionist', 'Laboratory Personnel', 'Ultrasound Personnel', '2D Echo Personnel', 'ECG Personnel', 'X-ray Personnel']);

    if ($_SESSION['role'] == 'Administrator') {

         // === 1. NEW: Serve Existing Procedures as JSON for Prefill ===
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_procedures' && isset($_POST['service_id'])) {
        $serviceId = intval($_POST['service_id']);

        $query = "
            SELECT 
                p.procedure_id, p.procedure_name, p.group_id, pg.group_name, pp.procedure_price
            FROM procedure_tbl AS p
            LEFT JOIN procedure_group_tbl AS pg ON p.group_id = pg.group_id
            LEFT JOIN procedure_price_tbl AS pp ON p.procedure_id = pp.procedure_id
            WHERE p.service_id = ? AND (p.is_archived=0 OR p.is_archived IS NULL)
            ORDER BY pg.group_name, p.procedure_name
        ";
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, 'i', $serviceId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $singles = [];
        $groups = [];
        while ($row = mysqli_fetch_assoc($result)) {
            if (!$row['group_id']) {
                $singles[] = [
                    'id' => $row['procedure_id'],
                    'name' => $row['procedure_name'],
                    'price' => $row['procedure_price']
                ];
            } else {
                $groups[$row['group_id']]['groupId'] = $row['group_id'];
                $groups[$row['group_id']]['groupName'] = $row['group_name'];
                $groups[$row['group_id']]['procs'][] = [
                    'id' => $row['procedure_id'],
                    'name' => $row['procedure_name'],
                    'price' => $row['procedure_price']
                ];
            }
        }
        $groupsArr = array_values($groups); // reindex for JS

        header('Content-Type: application/json');
        echo json_encode(['singles' => $singles, 'groups' => $groupsArr]);
        exit;
    }


        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_procedure') {
    // ✅ AJAX request to add procedure
    $serviceId = intval($_POST['service_id']);
    mysqli_begin_transaction($con);

    // ✅ IMPROVED: Archive procedures AND orphaned groups
if (!empty($_POST['archived_procedure_ids'])) {
    $toArchive = json_decode($_POST['archived_procedure_ids'], true);
    if (is_array($toArchive) && count($toArchive)) {
        foreach ($toArchive as $procId) {
            $procId = intval($procId);
            // Archive the procedure
            mysqli_query($con, "UPDATE procedure_tbl SET is_archived=1 WHERE procedure_id = $procId");
            
            // ✅ Check if this was the last procedure in a group
            $checkGroup = mysqli_query($con, "
                SELECT p.group_id 
                FROM procedure_tbl p 
                WHERE p.procedure_id = $procId AND p.group_id IS NOT NULL
            ");
            if ($groupRow = mysqli_fetch_assoc($checkGroup)) {
                $groupId = $groupRow['group_id'];
                // Count remaining active procedures in this group
                $countActive = mysqli_query($con, "
                    SELECT COUNT(*) as cnt 
                    FROM procedure_tbl 
                    WHERE group_id = $groupId AND (is_archived = 0 OR is_archived IS NULL)
                ");
                $countRow = mysqli_fetch_assoc($countActive);
                if ($countRow['cnt'] == 0) {
                    // No more active procedures in this group, archive it
                    mysqli_query($con, "DELETE FROM procedure_group_tbl WHERE group_id = $groupId");
                }
            }
        }
    }
}


    try {
        // ================== SINGLE PROCEDURES ================== //
        if (!empty($_POST['procedure_name']) && is_array($_POST['procedure_name'])) {
            foreach ($_POST['procedure_name'] as $index => $procName) {
                $procName = trim($procName);
                $procPrice = floatval($_POST['procedure_price'][$index] ?? 0);

                if ($procName === '') continue;

                $procId = isset($_POST['procedure_id'][$index]) ? intval($_POST['procedure_id'][$index]) : 0;
                if ($procId) {
                    // update existing procedure & price
                    $stmt_proc = mysqli_prepare($con, "UPDATE procedure_tbl SET procedure_name = ? WHERE procedure_id = ?");
                    mysqli_stmt_bind_param($stmt_proc, "si", $procName, $procId);
                    mysqli_stmt_execute($stmt_proc);
                    mysqli_stmt_close($stmt_proc);

                    $stmt_price = mysqli_prepare($con, "UPDATE procedure_price_tbl SET procedure_price = ? WHERE procedure_id = ?");
                    mysqli_stmt_bind_param($stmt_price, "di", $procPrice, $procId);
                    mysqli_stmt_execute($stmt_price);
                    mysqli_stmt_close($stmt_price);
                } else {
                    // Handle as NEW -- existing code
                    $stmt_proc = mysqli_prepare($con, "INSERT INTO procedure_tbl (procedure_name, service_id, procedure_added) VALUES (?, ?, NOW())");
                    mysqli_stmt_bind_param($stmt_proc, "si", $procName, $serviceId);
                    mysqli_stmt_execute($stmt_proc);
                    $procId = mysqli_insert_id($con);
                    mysqli_stmt_close($stmt_proc);

                    $stmt_price = mysqli_prepare($con, "INSERT INTO procedure_price_tbl (procedure_price, procedure_id) VALUES (?, ?)");
                    mysqli_stmt_bind_param($stmt_price, "di", $procPrice, $procId);
                    mysqli_stmt_execute($stmt_price);
                    mysqli_stmt_close($stmt_price);
                }


                // // Insert into procedure_tbl
                // $stmt_proc = mysqli_prepare($con, "INSERT INTO procedure_tbl (procedure_name, service_id, procedure_added) VALUES (?, ?, NOW())");
                // mysqli_stmt_bind_param($stmt_proc, "si", $procName, $serviceId);
                // mysqli_stmt_execute($stmt_proc);
                // $procedureId = mysqli_insert_id($con);
                // mysqli_stmt_close($stmt_proc);

                // // Insert into procedure_price_tbl
                // $stmt_price = mysqli_prepare($con, "INSERT INTO procedure_price_tbl (procedure_price, procedure_id) VALUES (?, ?)");
                // mysqli_stmt_bind_param($stmt_price, "di", $procPrice, $procedureId);
                // mysqli_stmt_execute($stmt_price);
                // mysqli_stmt_close($stmt_price);
            }
        }

       // ================== GROUPED PROCEDURES ================== //
// if (!empty($_POST['group_name']) && is_array($_POST['group_name'])) {
//     foreach ($_POST['group_name'] as $gIndex => $groupName) {
//         $groupName = trim($groupName);
//         if ($groupName === '') continue;

//         // Check if this is an existing group (has group_id)
//         $groupId = isset($_POST['group_id'][$gIndex]) ? intval($_POST['group_id'][$gIndex]) : 0;
        
//         if (!$groupId) {
//             // Insert NEW group
//             $stmt_group = mysqli_prepare($con, "INSERT INTO procedure_group_tbl (service_id, group_name) VALUES (?, ?)");
//             mysqli_stmt_bind_param($stmt_group, "is", $serviceId, $groupName);
//             mysqli_stmt_execute($stmt_group);
//             $groupId = mysqli_insert_id($con);
//             mysqli_stmt_close($stmt_group);
//         }

//         // Process each sub-procedure inside this group
//         if (!empty($_POST['sub_procedure_name'][$gIndex]) && is_array($_POST['sub_procedure_name'][$gIndex])) {
//             foreach ($_POST['sub_procedure_name'][$gIndex] as $pIndex => $subProcName) {
//                 $subProcName = trim($subProcName);
//                 $subProcPrice = floatval($_POST['sub_procedure_price'][$gIndex][$pIndex] ?? 0);

//                 if ($subProcName === '') continue;

//                 // Check if this sub-procedure has an ID (existing) - NOTE: sub_procedure_id is a flat array
//                 // $subProcId = isset($_POST['sub_procedure_id'][$pIndex]) ? intval($_POST['sub_procedure_id'][$pIndex]) : 0;
//                 $subProcId = 0;
//                 if (isset($_POST['sub_procedure_id'][$gIndex])) {
//                     if (is_array($_POST['sub_procedure_id'][$gIndex])) {
//                         $subProcId = intval($_POST['sub_procedure_id'][$gIndex][$pIndex] ?? 0);
//                     } else {
//                         // Fallback for flat array (shouldn't happen but just in case)
//                         $subProcId = intval($_POST['sub_procedure_id'][$pIndex] ?? 0);
//                     }
//                 }


//                 if ($subProcId > 0) {
//                     // UPDATE existing sub-procedure
//                     $stmt_proc = mysqli_prepare($con, "UPDATE procedure_tbl SET procedure_name = ? WHERE procedure_id = ?");
//                     mysqli_stmt_bind_param($stmt_proc, "si", $subProcName, $subProcId);
//                     mysqli_stmt_execute($stmt_proc);
//                     mysqli_stmt_close($stmt_proc);

//                     $stmt_price = mysqli_prepare($con, "UPDATE procedure_price_tbl SET procedure_price = ? WHERE procedure_id = ?");
//                     mysqli_stmt_bind_param($stmt_price, "di", $subProcPrice, $subProcId);
//                     mysqli_stmt_execute($stmt_price);
//                     mysqli_stmt_close($stmt_price);
//                 } else {
//                     // INSERT new sub-procedure
//                     $stmt_proc = mysqli_prepare($con, "INSERT INTO procedure_tbl (procedure_name, service_id, group_id, procedure_added) VALUES (?, ?, ?, NOW())");
//                     mysqli_stmt_bind_param($stmt_proc, "sii", $subProcName, $serviceId, $groupId);
//                     mysqli_stmt_execute($stmt_proc);
//                     $subProcedureId = mysqli_insert_id($con);
//                     mysqli_stmt_close($stmt_proc);

//                     $stmt_price = mysqli_prepare($con, "INSERT INTO procedure_price_tbl (procedure_price, procedure_id) VALUES (?, ?)");
//                     mysqli_stmt_bind_param($stmt_price, "di", $subProcPrice, $subProcedureId);
//                     mysqli_stmt_execute($stmt_price);
//                     mysqli_stmt_close($stmt_price);
//                 }
//             }
//         }
//     }
// }

// ================== GROUPED PROCEDURES ================== //
if (!empty($_POST['group_name']) && is_array($_POST['group_name'])) {
    foreach ($_POST['group_name'] as $gIndex => $groupName) {
        $groupName = trim($groupName);
        if ($groupName === '') continue;

        // ✅ Get the ACTUAL group_id for this group
        $groupId = 0;
        if (isset($_POST['group_id'][$gIndex])) {
            $groupId = intval($_POST['group_id'][$gIndex]);
        }
        
        if ($groupId > 0) {
            // ✅ VERIFY this group actually exists and belongs to this service
            $verify = mysqli_query($con, "SELECT group_id FROM procedure_group_tbl WHERE group_id = $groupId AND service_id = $serviceId");
            if (mysqli_num_rows($verify) > 0) {
                // UPDATE existing group name
                $stmt_group = mysqli_prepare($con, "UPDATE procedure_group_tbl SET group_name = ? WHERE group_id = ? AND service_id = ?");
                mysqli_stmt_bind_param($stmt_group, "sii", $groupName, $groupId, $serviceId);
                mysqli_stmt_execute($stmt_group);
                mysqli_stmt_close($stmt_group);
            } else {
                // Group doesn't exist, create new one
                $groupId = 0;
            }
        }
        
        if ($groupId === 0) {
            // ✅ INSERT new group
            $stmt_group = mysqli_prepare($con, "INSERT INTO procedure_group_tbl (service_id, group_name) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt_group, "is", $serviceId, $groupName);
            mysqli_stmt_execute($stmt_group);
            $groupId = mysqli_insert_id($con);
            mysqli_stmt_close($stmt_group);
        }

        // ✅ NOW process sub-procedures for THIS SPECIFIC group
        if (isset($_POST['sub_procedure_name'][$gIndex]) && is_array($_POST['sub_procedure_name'][$gIndex])) {
            foreach ($_POST['sub_procedure_name'][$gIndex] as $pIndex => $subProcName) {
                $subProcName = trim($subProcName);
                if ($subProcName === '') continue;
                
                $subProcPrice = floatval($_POST['sub_procedure_price'][$gIndex][$pIndex] ?? 0);
                
                // ✅ Get the ACTUAL sub-procedure ID
                $subProcId = 0;
                if (isset($_POST['sub_procedure_id'][$gIndex]) && is_array($_POST['sub_procedure_id'][$gIndex])) {
                    $subProcId = intval($_POST['sub_procedure_id'][$gIndex][$pIndex] ?? 0);
                }

                if ($subProcId > 0) {
                    // ✅ VERIFY this procedure exists and belongs to this group
                    $verify = mysqli_query($con, "SELECT procedure_id FROM procedure_tbl WHERE procedure_id = $subProcId AND service_id = $serviceId");
                    if (mysqli_num_rows($verify) > 0) {
                        // UPDATE existing sub-procedure WITH CORRECT GROUP_ID
                        $stmt_proc = mysqli_prepare($con, "UPDATE procedure_tbl SET procedure_name = ?, group_id = ? WHERE procedure_id = ? AND service_id = ?");
                        mysqli_stmt_bind_param($stmt_proc, "siii", $subProcName, $groupId, $subProcId, $serviceId);
                        mysqli_stmt_execute($stmt_proc);
                        mysqli_stmt_close($stmt_proc);

                        // Update price
                        $check_price = mysqli_query($con, "SELECT procedure_price_id FROM procedure_price_tbl WHERE procedure_id = $subProcId");
                        if (mysqli_num_rows($check_price) > 0) {
                            $stmt_price = mysqli_prepare($con, "UPDATE procedure_price_tbl SET procedure_price = ? WHERE procedure_id = ?");
                            mysqli_stmt_bind_param($stmt_price, "di", $subProcPrice, $subProcId);
                            mysqli_stmt_execute($stmt_price);
                            mysqli_stmt_close($stmt_price);
                        } else {
                            $stmt_price = mysqli_prepare($con, "INSERT INTO procedure_price_tbl (procedure_price, procedure_id) VALUES (?, ?)");
                            mysqli_stmt_bind_param($stmt_price, "di", $subProcPrice, $subProcId);
                            mysqli_stmt_execute($stmt_price);
                            mysqli_stmt_close($stmt_price);
                        }
                    } else {
                        // Procedure doesn't exist, treat as new
                        $subProcId = 0;
                    }
                }
                
                if ($subProcId === 0) {
                    // ✅ INSERT new sub-procedure WITH CORRECT GROUP_ID
                    $stmt_proc = mysqli_prepare($con, "INSERT INTO procedure_tbl (procedure_name, service_id, group_id, procedure_added) VALUES (?, ?, ?, NOW())");
                    mysqli_stmt_bind_param($stmt_proc, "sii", $subProcName, $serviceId, $groupId);
                    mysqli_stmt_execute($stmt_proc);
                    $newSubProcId = mysqli_insert_id($con);
                    mysqli_stmt_close($stmt_proc);

                    // Insert price
                    $stmt_price = mysqli_prepare($con, "INSERT INTO procedure_price_tbl (procedure_price, procedure_id) VALUES (?, ?)");
                    mysqli_stmt_bind_param($stmt_price, "di", $subProcPrice, $newSubProcId);
                    mysqli_stmt_execute($stmt_price);
                    mysqli_stmt_close($stmt_price);
                }
            }
        }
    }
}


        mysqli_commit($con);

        // ✅ Now fetch updated procedures and return only the container
        $query = "
            SELECT pg.group_name, p.procedure_name, pp.procedure_price
            FROM procedure_tbl AS p
            LEFT JOIN procedure_group_tbl AS pg ON p.group_id = pg.group_id
            LEFT JOIN procedure_price_tbl AS pp ON p.procedure_id = pp.procedure_id
            WHERE p.service_id = ? AND (p.is_archived=0 OR p.is_archived IS NULL)
            ORDER BY pg.group_name, p.procedure_name
        ";
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, 'i', $serviceId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $procedures = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $groupName = $row['group_name'] ?: 'Ungrouped';
            $procedures[$groupName][] = $row;
        }
        mysqli_stmt_close($stmt);

        // ✅ Return only the refreshed HTML
        ob_start();
        ?>
        <div id="procedureListContainer">
            <?php if (!empty($procedures)): ?>
                <?php foreach ($procedures as $groupName => $procs): ?>
                    <h4 style="margin-top:15px;"><?= htmlspecialchars($groupName) ?></h4>
                    <div class="info-grid">
                        <?php foreach ($procs as $proc): ?>
                            <div class="info-item">
                                <label><?= htmlspecialchars($proc['procedure_name']) ?></label>
                                <span>₱<?= number_format($proc['procedure_price'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="padding:15px;">No procedures found for this service.</p>
            <?php endif; ?>
        </div>
        <?php
        $updatedHTML = ob_get_clean();
        echo $updatedHTML;
        exit;

    } catch (Exception $e) {
        mysqli_rollback($con);
        http_response_code(500);
        exit("Error inserting procedures");
    }
}

        // view details of service for admin
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['service_id'])) {

            $serviceId = intval($_POST['service_id']);

            $query = "SELECT * FROM clinic_service_tbl WHERE service_id = ?";
            $stmt = mysqli_prepare($con, $query);
            mysqli_stmt_bind_param($stmt, 'i', $serviceId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($row = mysqli_fetch_assoc($result)) {
                // render (I'll render the info in the html below)
            } else {
                echo "<h1>Service not found.</h1>";
                exit;
            }

            mysqli_stmt_close($stmt);

             // Fetch related procedures, groups, and prices
        $query = "
                SELECT pg.group_name, p.procedure_name, pp.procedure_price
                FROM procedure_tbl AS p
                LEFT JOIN procedure_group_tbl AS pg ON p.group_id = pg.group_id
                LEFT JOIN procedure_price_tbl AS pp ON p.procedure_id = pp.procedure_id
                WHERE p.service_id = ? AND (p.is_archived=0 OR p.is_archived IS NULL)
                ORDER BY pg.group_name, p.procedure_name
            ";
            $stmt = mysqli_prepare($con, $query);
            mysqli_stmt_bind_param($stmt, 'i', $serviceId);
            mysqli_stmt_execute($stmt);
            $proceduresResult = mysqli_stmt_get_result($stmt);

            $procedures = [];
            while ($proc = mysqli_fetch_assoc($proceduresResult)) {
                $groupName = $proc['group_name'] ?: 'Ungrouped';
                $procedures[$groupName][] = $proc;
            }
            mysqli_stmt_close($stmt);

            // ✅ Return only the refreshed HTML
            ob_start();

        } else {
            echo "<h2 style='text-align:center; margin-top: 100px;'>No service selected.</h2>";
            exit;
        }

        // Fetch patients who availed this service
    $query = "
        SELECT psa.avail_id, psa.case_no, psa.date_availed, psa.status,
               pi.patient_id, pi.patient_fname, pi.patient_lname, pi.patient_mname
        FROM patient_service_avail psa
        JOIN patient_info_tbl pi ON psa.patient_id = pi.patient_id
        WHERE psa.service_id = ?
        ORDER BY psa.date_availed DESC
    ";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, 'i', $serviceId);
    mysqli_stmt_execute($stmt);
    $patientsResult = mysqli_stmt_get_result($stmt);

    $patients = [];
    while ($pat = mysqli_fetch_assoc($patientsResult)) {
        $patients[] = $pat;
    }
    mysqli_stmt_close($stmt);

    // EDIT SERVICE INFORMATION BACKCEND
    // === EDIT SERVICE INFO ===
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_service') {
            $serviceId = intval($_POST['service_id']);
            $serviceCode = trim($_POST['service_code']);
            $serviceName = trim($_POST['service_name']);
            $roleId = intval($_POST['role_id']);

            if (!$serviceCode || !$serviceName || !$roleId) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
                exit;
            }

            // Check for duplicate service code (excluding current service)
            $checkQuery = "SELECT service_id FROM clinic_service_tbl WHERE service_code = ? AND service_id != ?";
            $checkStmt = mysqli_prepare($con, $checkQuery);
            mysqli_stmt_bind_param($checkStmt, 'si', $serviceCode, $serviceId);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);
            
            if (mysqli_stmt_num_rows($checkStmt) > 0) {
                mysqli_stmt_close($checkStmt);
                echo json_encode(['status' => 'error', 'message' => 'Service code already exists.']);
                exit;
            }
            mysqli_stmt_close($checkStmt);

            // Update service
            $updateQuery = "UPDATE clinic_service_tbl SET service_code = ?, service_name = ?, role_id = ? WHERE service_id = ?";
            $updateStmt = mysqli_prepare($con, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, 'ssii', $serviceCode, $serviceName, $roleId, $serviceId);
            
            if (mysqli_stmt_execute($updateStmt)) {
                mysqli_stmt_close($updateStmt);
                
                // Fetch updated role name
                $roleQuery = "SELECT role_name FROM roles WHERE role_id = ?";
                $roleStmt = mysqli_prepare($con, $roleQuery);
                mysqli_stmt_bind_param($roleStmt, 'i', $roleId);
                mysqli_stmt_execute($roleStmt);
                $roleResult = mysqli_stmt_get_result($roleStmt);
                $roleRow = mysqli_fetch_assoc($roleResult);
                $roleName = $roleRow['role_name'] ?? 'N/A';
                mysqli_stmt_close($roleStmt);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Service updated successfully.',
                    'service_code' => $serviceCode,
                    'service_name' => $serviceName,
                    'role_name' => $roleName
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Failed to update service.']);
            }
            exit;
        }

        // ARCHIVE SERVICE
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'archive_service' && isset($_POST['service_id'])) {
            $idToArchive = intval($_POST['service_id']);
            $stmt = mysqli_prepare($con, "UPDATE clinic_service_tbl SET is_archived = 1 WHERE service_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $idToArchive);
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['status' => 'success', 'message' => 'Service removed successfully.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error archiving service: ' . mysqli_error($con)]);
            }
            exit;
        }

        // RESTORE SERVICE
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore_service' && isset($_POST['service_id'])) {
            $idToRestore = intval($_POST['service_id']);
            $stmt = mysqli_prepare($con, "UPDATE clinic_service_tbl SET is_archived = 0 WHERE service_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $idToRestore);
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['status' => 'success', 'message' => 'Service restored successfully.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error restoring service: ' . mysqli_error($con)]);
            }
            exit;
        }
       
    ?>

    <!-- <link rel="stylesheet" href="../css/view_account.css"> -->

    <div class = "page-title d-flex justify-content-between align-items-center">
        <div class = "title">View Service</div>

        <!-- space for the buttons -->
    </div>

    <!-- Service info box not table -->
    <div class = "info-card">
        <div class = "card-header">
            <h3><i class="fa-solid fa-bell-concierge"></i> Service Information </h3>
            <button class="btn btn-outline btn-sm" id="editServiceBtn"><i class="fas fa-pen"></i> Edit</button>
        </div>
        <div class = "info-grid">
            <div class = "info-item">
                <label>Service Code</label>
                <span><?= htmlspecialchars($row['service_code']) ?></span>
            </div>
            <div class = "info-item">
                <label>Service Name</label>
                <span><?= htmlspecialchars($row['service_name']) ?></span>
            </div>
            <div class = "info-item">
                <label>Personnel In Charge</label>
                <span id="displayPersonnel">
                    <?php
                    // Fetch role name
                    $roleQuery = "SELECT role_name FROM roles WHERE role_id = ?";
                    $roleStmt = mysqli_prepare($con, $roleQuery);
                    mysqli_stmt_bind_param($roleStmt, 'i', $row['role_id']);
                    mysqli_stmt_execute($roleStmt);
                    $roleResult = mysqli_stmt_get_result($roleStmt);
                    $roleRow = mysqli_fetch_assoc($roleResult);
                    echo htmlspecialchars($roleRow['role_name'] ?? 'N/A');
                    mysqli_stmt_close($roleStmt);
                    ?>
                </span>
            </div>
           
            <div class ="info-item">
                <?php if ($row['is_archived'] == 1): ?>
                <button class="btn btn-success btn-sm" id="restoreServiceBtn">Restore</button>
                <?php else: ?>
                <button class="btn btn-danger btn-sm" id="archiveServiceBtn">Delete</button>
                <?php endif; ?>
            </div>


        </div>
    </div>

    <!-- EDIT SERVICE INFO MODAL, DITO KO NA NILAGAY PARA CONVENIENT -->
    <!-- Edit Service Modal -->
    <div id="editServiceModal" class="modal">
        <div class="modal-content">
            <h3>Edit Service Information</h3>
            <form id="editServiceForm" method="POST">
                <input type="hidden" id="edit_service_id" name="service_id" value="<?= intval($serviceId) ?>">
                <input type="hidden" name="action" value="edit_service">
                
                <div class="form-group">
                    <label>Service Code</label>
                    <input type="text" id="edit_service_code" name="service_code" 
                        value="<?= htmlspecialchars($row['service_code']) ?>" 
                        required maxlength="20">
                </div>
                
                <div class="form-group">
                    <label>Service Name</label>
                    <input type="text" id="edit_service_name" name="service_name" 
                        value="<?= htmlspecialchars($row['service_name']) ?>" 
                        required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label>Personnel In Charge</label>
                    <select name="role_id" id="edit_role_id" required>
                        <?php
                        $roleQuery = mysqli_query($con, "SELECT role_id, role_name FROM roles WHERE role_id IN (2,3,5,6,7)");
                        while ($role = mysqli_fetch_assoc($roleQuery)) {
                            $selected = ($role['role_id'] == $row['role_id']) ? 'selected' : '';
                            echo "<option value=\"{$role['role_id']}\" {$selected}>{$role['role_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn btn-outline" onclick="closeEditServiceModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>



    <!-- Procedures -->

    <div class="info-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3><i class="fas fa-calendar-plus"></i> Procedures</h3>
            <button id="addProcedureBtn" class="btn btn-outline btn-sm">
                <i class="fas fa-plus"></i> Add Procedure
            </button>
        </div>

        <!-- ADD PROCEDURE MODAL -->
        <div id="addProcedureModal" class="modal">
        <div class = "modal-content">
            <h3>Add Procedure</h3>
            <form id = "addProcedureForm" method= "POST">
                <input type="hidden" name = "service_id" value="<?= intval($serviceId) ?>">
                <!-- new added code below this -->
                <input type="hidden" name="action" value="add_procedure">

                <!-- <input type="hidden" name="service_id" value="<?php 
                // echo $service_id; ?>"> -->


                <!-- DYNAMIC CONTAINER -->
                 <div id ="procedureContainer"></div>

                 <!-- single procedure template -->
                  <template id ="singleProcedureTemplate">
                    <div class = "procedure-box">
                        <div class = "procedure-content">
                        <span class = "close" style = "cursor:pointer">&times;</span>
                        <label>Procedure</label>
                        <input type="text" name = "procedure_name[]" required>
                        <label>Price</label>
                        <input type="number" step="0.01" name = "procedure_price[]" required>
                        </div>
                    </div>
                  </template>

                  <!-- grouped proc template -->
                   <template id = "groupTemplate">
                     <div class = "group-box">
                        <div class = "group-content">
                        <span class = "close-group" style = "cursor:pointer">&times;</span>
                        <label>Group Name</label>
                        <input type="text" name = "group_name[]" placeholder="e.g. Blood Chemistry" required>
                        <div class = "procedure-list"></div>
                        <button type = "button" class = "btn btn-outline btm-sm addProcedureBtnInside">+ Add Procedure</button>
                        </div>
                     </div>
                   </template>

                   <!-- single procedure inside group -->
                    <!-- disabled to test something new -->
                    <template id = "groupProcedureTemplate">
                        <div class = "procedure-box">
                            <div class = "procedure-content">
                            <span class = "close" style = "cursor:pointer;">&times;</span>
                            <label>Procedure Name</label>
                            <input type="text" name = "sub_procedure_name[0][]" required>
                            <label>Price</label>
                            <input type="number" step="0.01" name = "sub_procedure_price[0][]" required>
                            </div>
                        </div>
                    </template>

                    <!-- buttons to add either single or grouped -->
                     <div class = "button-row">
                        <button type = "button" class = "btn btn-outline btn-sm" onclick = "addSingleProcedure()">+ Add Single Procedure</button>
                        <button type = "button" class = "btn btn-outline btn-sm" onclick = "addGroupBlock()">+ Add Grouped Procedure</button>
                     </div>

                     <div class = "modal-buttons">
                        <button type="button" class="btn btn-outline" onclick="closeProcedureModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Procedures</button>
                     </div>
            </form>
        </div>
        </div>


        <!-- Dynamic procedure form container -->
        <div id="procedureFormContainer"></div>
            <div id = 'procedureListContainer'>
                <?php if (!empty($procedures)): ?>
                <?php foreach ($procedures as $groupName => $procs): ?>
                    <h4 style="margin-top:15px;"><?= htmlspecialchars($groupName) ?></h4>
                    <div class="info-grid">
                        <?php foreach ($procs as $proc): ?>
                            <div class="info-item">
                                <label><?= htmlspecialchars($proc['procedure_name']) ?></label>
                                <span>₱<?= number_format($proc['procedure_price'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="padding:15px;">No procedures found for this service.</p>
            <?php endif; ?>
        </div>
        <?php 
        // exit;
        ?>
    </div>

    <!-- Patients that availed Labo -->

    <!-- Patients table -->
    <div class="info-card">
        <div class="card-header">
            <h3><i class="fas fa-user-injured"></i> Patients</h3>
        </div>
        <?php if (!empty($patients)): ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Case No.</th>
                            <th>Patient Name</th>
                            <th>Date Availed</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $pat): ?>
                            <tr>
                                <td><?= htmlspecialchars($pat['case_no'] ?? '--') ?></td>
                                <td><?= htmlspecialchars($pat['patient_fname'] . ' ' . $pat['patient_mname'] . ' ' . $pat['patient_lname']) ?></td>
                                <td><?= date("M d, Y H:i", strtotime($pat['date_availed'])) ?></td>
                                <td><?= htmlspecialchars($pat['status']) ?></td>
                                <td>
                                    <button type="button" class="btn btn-outline btn-sm view-patient-btn"
                                        data-avail_id="<?= $pat['avail_id'] ?>"
                                        data-patient_id="<?= $pat['patient_id'] ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="padding:15px;">No patients found for this service.</p>
        <?php endif; ?>
    </div>

    <?php
    }
    // VIEW SERVICES NOT VIEW SERVICES WITH PATIENTS
    ?>
    <?php

    // ===================RECEPTIONIST VIEW SERVICE DETAILS ONLY =================== //
    
if ($_SESSION['role'] == 'Receptionist') {
    // Only execute if service is selected by POST or GET
    $serviceId = intval($_POST['service_id'] ?? $_GET['service_id'] ?? 0);
    if (!$serviceId) {
        echo "<h2 style='text-align:center;margin-top:100px;'>No service selected.</h2>";
        exit;
    }

    // Fetch service info
    $query = "SELECT * FROM clinic_service_tbl WHERE service_id = ?";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, 'i', $serviceId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        // Service found, continue
    } else {
        echo "<h1>Service not found.</h1>";
        exit;
    }
    mysqli_stmt_close($stmt);

    // Fetch procedures and groups
    $query = "
        SELECT pg.group_name, p.procedure_name, pp.procedure_price
        FROM procedure_tbl AS p
        LEFT JOIN procedure_group_tbl AS pg ON p.group_id = pg.group_id
        LEFT JOIN procedure_price_tbl AS pp ON p.procedure_id = pp.procedure_id
        WHERE p.service_id = ? AND (p.is_archived=0 OR p.is_archived IS NULL)
        ORDER BY pg.group_name, p.procedure_name
    ";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, 'i', $serviceId);
    mysqli_stmt_execute($stmt);
    $proceduresResult = mysqli_stmt_get_result($stmt);

    $procedures = [];
    while ($proc = mysqli_fetch_assoc($proceduresResult)) {
        $groupName = $proc['group_name'] ?: 'Ungrouped';
        $procedures[$groupName][] = $proc;
    }
    mysqli_stmt_close($stmt);

    // Fetch patients who availed this service
    $query = "
        SELECT psa.avail_id, psa.case_no, psa.date_availed, psa.status,
               pi.patient_id, pi.patient_fname, pi.patient_lname, pi.patient_mname
        FROM patient_service_avail psa
        JOIN patient_info_tbl pi ON psa.patient_id = pi.patient_id
        WHERE psa.service_id = ?
        ORDER BY psa.date_availed DESC
    ";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, 'i', $serviceId);
    mysqli_stmt_execute($stmt);
    $patientsResult = mysqli_stmt_get_result($stmt);

    $patients = [];
    while ($pat = mysqli_fetch_assoc($patientsResult)) {
        $patients[] = $pat;
    }
    mysqli_stmt_close($stmt);
    ?>

    <div class="page-title d-flex justify-content-between align-items-center">
        <div class="title">View Service</div>
    </div>

    <!-- Service info box -->
    <div class="info-card">
        <div class="card-header">
            <h3><i class="fa-solid fa-bell-concierge"></i> Service Information </h3>
        </div>
        <div class="info-grid">
            <div class="info-item">
                <label>Service Code</label>
                <span><?= htmlspecialchars($row['service_code']) ?></span>
            </div>
            <div class="info-item">
                <label>Service Name</label>
                <span><?= htmlspecialchars($row['service_name']) ?></span>
            </div>
            <div class = "info-item">
                <label>Personnel In Charge</label>
                <span id="displayPersonnel">
                    <?php
                    // Fetch role name
                    $roleQuery = "SELECT role_name FROM roles WHERE role_id = ?";
                    $roleStmt = mysqli_prepare($con, $roleQuery);
                    mysqli_stmt_bind_param($roleStmt, 'i', $row['role_id']);
                    mysqli_stmt_execute($roleStmt);
                    $roleResult = mysqli_stmt_get_result($roleStmt);
                    $roleRow = mysqli_fetch_assoc($roleResult);
                    echo htmlspecialchars($roleRow['role_name'] ?? 'N/A');
                    mysqli_stmt_close($roleStmt);
                    ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Procedures (READ-ONLY) -->
    <div class="info-card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-plus"></i> Procedures</h3>
        </div>
        <div id='procedureListContainer'>
            <?php if (!empty($procedures)): ?>
                <?php foreach ($procedures as $groupName => $procs): ?>
                    <h4 style="margin-top:15px;"><?= htmlspecialchars($groupName) ?></h4>
                    <div class="info-grid">
                        <?php foreach ($procs as $proc): ?>
                            <div class="info-item">
                                <label><?= htmlspecialchars($proc['procedure_name']) ?></label>
                                <span>₱<?= number_format($proc['procedure_price'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="padding:15px;">No procedures found for this service.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Patients table -->
    <div class="info-card">
        <div class="card-header">
            <h3><i class="fas fa-user-injured"></i> Patients</h3>
        </div>
        <?php if (!empty($patients)): ?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Case No.</th>
                            <th>Patient Name</th>
                            <th>Date Availed</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $pat): ?>
                            <tr>
                                <td><?= htmlspecialchars($pat['case_no'] ?? '--') ?></td>
                                <td><?= htmlspecialchars($pat['patient_fname'] . ' ' . $pat['patient_mname'] . ' ' . $pat['patient_lname']) ?></td>
                                <td><?= date("M d, Y H:i", strtotime($pat['date_availed'])) ?></td>
                                <td><?= htmlspecialchars($pat['status']) ?></td>
                                <td>
                                    <button type="button" class="btn btn-outline btn-sm view-patient-btn"
                                        data-avail_id="<?= $pat['avail_id'] ?>"
                                        data-patient_id="<?= $pat['patient_id'] ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="padding:15px;">No patients found for this service.</p>
        <?php endif; ?>
    </div>
    <!-- Make sure your JS handles .view-patient-btn (for modal/details) -->
    <script src="js/main.js"></script>
<?php
}
?>