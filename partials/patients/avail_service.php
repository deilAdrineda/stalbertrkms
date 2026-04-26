<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include '../../includes/session_check.php';
requireRole('Receptionist');

$con = $con ?? null;

// ========== AJAX: Get Package Details for Avail ==========

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_get_package'], $_POST['package_id'])) {
    header('Content-Type: application/json');
    $pkgId = intval($_POST['package_id']);
    if ($pkgId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid package id']);
        exit;
    }

    // Main package head details
    $pkgQ = mysqli_query($con, "SELECT * FROM clinic_packages WHERE package_id = $pkgId AND is_archived = 0");
    $pkg = mysqli_fetch_assoc($pkgQ);

    if (!$pkg) {
        echo json_encode(['status' => 'error', 'message' => 'Package not found']);
        exit;
    }

    // Procedures included in this package (list of ids)
    $procedure_ids = [];
    $pQ = mysqli_query($con, "SELECT procedure_id FROM clinic_packages_procedures WHERE package_id = $pkgId");
    while ($row = mysqli_fetch_assoc($pQ)) $procedure_ids[] = $row['procedure_id'];
    $pkg['procedures'] = $procedure_ids;

    // Get all available procedures grouped by service, with price (to match JS logic)
    $result = mysqli_query($con, "
        SELECT p.procedure_id, p.procedure_name, s.service_name, pp.procedure_price, p.service_id
        FROM procedure_tbl p
        JOIN clinic_service_tbl s ON p.service_id = s.service_id
        LEFT JOIN procedure_price_tbl pp ON p.procedure_id = pp.procedure_id
        WHERE p.is_archived = 0
        ORDER BY s.service_name, p.procedure_name ASC
    ");

    $available = [];
    while ($row = mysqli_fetch_assoc($result)) {
        
        $cat = $row['service_name'] ?: 'Other';
        if (!isset($available[$cat])) $available[$cat] = [];
        $available[$cat][] = [
            'procedure_id' => $row['procedure_id'],
            'procedure_name' => $row['procedure_name'],
            'procedure_price' => $row['procedure_price'],
            'service_id' => $row['service_id']
        ];
    }
    $pkg['all_procedures'] = $available;

    echo json_encode($pkg);
    exit;
}


// ======================== BACKEND: Handle Avail POST =========================
if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['patient_id'], $_POST['selected_services'])) {
    // Use patient_id from form, everything else same as "add patient"
    $patient_id = intval($_POST['patient_id']);

     // ---- Insert this block here ----
    if (empty($_POST['selected_services']) || !is_array($_POST['selected_services'])) {
        echo json_encode(['status' => 'error', 'message' => 'Please check at least 1 service.']);
        exit;
    }

    // Check each service for at least one procedure (predefined or custom)
    $procedures_by_svc = $_POST['procedures'] ?? [];
    $have_procedure = false;
    foreach ($_POST['selected_services'] as $svc_id_raw) {
        $svc_id = (int)$svc_id_raw;
        $procs = $procedures_by_svc[$svc_id] ?? [];
        $hasPredefined = is_array($procs) && count($procs) > 0;
        $hasCustom = isset($_POST['manual_procedure'][$svc_id]) && trim($_POST['manual_procedure'][$svc_id]) !== '';
        if ($hasPredefined || $hasCustom) {
            $have_procedure = true;
            break;
        }
    }
    if (!$have_procedure) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a procedure for every selected service.']);
        exit;
    }

    $requested_by_arr = $_POST['requested_by'] ?? [];
    $history_arr = $_POST['history'] ?? [];
    $manual_single = $_POST['manual_procedure'] ?? [];
    $procedures_by_svc = $_POST['procedures'] ?? [];
    $other_proc_group = $_POST['other_proc_group'] ?? [];
    $manual_custom_proc_price = $_POST['manual_custom_proc_price'] ?? [];
    $other_proc_group_price = $_POST['other_proc_group_price'] ?? [];

    // Defaults
    $billing_status_default = 'Paid';
    $status_default = 'Pending';
    $now = date('Y-m-d H:i:s');

    $selected_package_id = $_POST['selected_package'] ?? '';
    $package_name_base = '';
    $package_service_ids = []; // ✅ Track which services are in the package

    if ($selected_package_id) {
        $pkgQ = mysqli_query($con, "SELECT package_name FROM clinic_packages WHERE package_id = $selected_package_id");
        $pkg = mysqli_fetch_assoc($pkgQ);
        $package_name_base = $pkg['package_name'] ?? '';
        
        // ✅ Get all service IDs that are part of this package
        $pkg_svc_query = mysqli_query($con, "
            SELECT DISTINCT s.service_id 
            FROM clinic_packages_procedures cpp
            JOIN procedure_tbl p ON cpp.procedure_id = p.procedure_id
            JOIN clinic_service_tbl s ON p.service_id = s.service_id
            WHERE cpp.package_id = $selected_package_id
        ");
        while ($row = mysqli_fetch_assoc($pkg_svc_query)) {
            $package_service_ids[] = (int)$row['service_id'];
        }
    }

    $official_package_procs = [];
    if ($selected_package_id) {
        $pkg_proc_rs = mysqli_query($con, "SELECT procedure_id FROM clinic_packages_procedures WHERE package_id = $selected_package_id");
        while ($row = mysqli_fetch_assoc($pkg_proc_rs)) $official_package_procs[] = (int)$row['procedure_id'];
    }



    mysqli_begin_transaction($con);

    try {
        $avail_sql = "INSERT INTO patient_service_avail (case_no, patient_id, service_id, requested_by, brief_history, package_name, date_availed, billing_status, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $avail_stmt = mysqli_prepare($con, $avail_sql);

        $proc_sql = "INSERT INTO patient_service_proc (avail_id, procedure_id, custom_proc, group_id, custom_group_proc) VALUES (?, ?, ?, ?, ?)";
        $proc_stmt = mysqli_prepare($con, $proc_sql);

        $group_service_stmt = mysqli_prepare($con, "SELECT service_id FROM procedure_group_tbl WHERE group_id = ?");

        $inserted_avail_ids = []; 

        foreach ($_POST['selected_services'] as $svc_id_raw) {
            $service_id = (int)$svc_id_raw;

            // Clean up all possible empty fields before DB bind/insert:
            $requested_by = (isset($requested_by_arr[$service_id]) && trim($requested_by_arr[$service_id]) !== '')
                ? trim($requested_by_arr[$service_id])
                : null;

            $brief_hist = (isset($history_arr[$service_id]) && trim($history_arr[$service_id]) !== '')
                ? trim($history_arr[$service_id])
                : null;

            $package_name = (in_array($service_id, $package_service_ids)) ? $package_name_base : null;

            // case_no logic
            $serviceRes = mysqli_query($con, "SELECT service_code FROM clinic_service_tbl WHERE service_id = $service_id LIMIT 1");
            $serviceRow = mysqli_fetch_assoc($serviceRes);
            $service_code = $serviceRow['service_code'] ?? '';

            $year = date('y');

            // today's count of avails for this service
            $countQuery = mysqli_query(
            $con,
            "SELECT COUNT(*) as count_today
            FROM patient_service_avail
            WHERE service_id = $service_id
                AND DATE(date_availed) = CURDATE()"
            );
            $countRow = mysqli_fetch_assoc($countQuery);
            $todayCount = (int)$countRow['count_today'];
            $nextCount = $todayCount + 1;

            // the actual case_no
            $case_no = $service_code . $year . '-' . $nextCount;

            mysqli_stmt_bind_param($avail_stmt, 'siissssss', $case_no,$patient_id, $service_id, $requested_by, $brief_hist, $package_name, $now, $billing_status_default, $status_default);
            if (!mysqli_stmt_execute($avail_stmt)) throw new Exception('Failed to insert patient_service_avail');

            $avail_id = mysqli_insert_id($con);

             $inserted_avail_ids[] = $avail_id;

            // 1. Get role_id for the service
            $role_res = mysqli_query($con, "SELECT role_id FROM clinic_service_tbl WHERE service_id = $service_id");
            $role_row = mysqli_fetch_assoc($role_res);
            $role_id = $role_row['role_id'] ?? null;

            // SECURITY: If service has no role_id assigned
            if (!$role_id) {
                mysqli_rollback($con);
                echo json_encode(['status' => 'error', 'message' => 'No assigned personnel for this service!']);
                exit;
            }

            // 2. Get all active personnel (user_account_id) for that role
            $pers_query = mysqli_query($con, "SELECT user_account_id FROM user_account WHERE role_id = $role_id");

            // SECURITY: If no personnel tied to role_id
            if (mysqli_num_rows($pers_query) === 0) {
                mysqli_rollback($con);
                echo json_encode(['status' => 'error', 'message' => 'No assigned personnel for this service!']);
                exit;
            }

            // 3. For each personnel, create a service_task entry
            while ($pers = mysqli_fetch_assoc($pers_query)) {
                $user_account_id = $pers['user_account_id'];
                // Insert service_task record
                $task_sql = "INSERT INTO service_task (avail_id, service_id, user_account_id, status, assigned_on)
                            VALUES (?, ?, ?, 'Pending', ?)";
                $task_stmt = mysqli_prepare($con, $task_sql);
                mysqli_stmt_bind_param($task_stmt, 'iiis',
                    $avail_id,
                    $service_id,
                    $user_account_id,
                    $now
                );
                mysqli_stmt_execute($task_stmt);
                mysqli_stmt_close($task_stmt);
            }


            // -- Secure package and add-on procedure insert --
            $proc_ids = $procedures_by_svc[$service_id] ?? [];

            if ($selected_package_id && !empty($official_package_procs)) {
                foreach ($official_package_procs as $pkg_proc_id) {
                    // Check correct service assignment for safety:
                    $check_service = mysqli_query($con, "SELECT service_id, group_id FROM procedure_tbl WHERE procedure_id = $pkg_proc_id");
                    $svc_row = mysqli_fetch_assoc($check_service);
                    if ($svc_row && (int)$svc_row['service_id'] === $service_id) {
                        $group_id = $svc_row['group_id'] ?? null;
                        $null = null;
                        // Get price
                        $priceQ = mysqli_query($con, "SELECT procedure_price FROM procedure_price_tbl WHERE procedure_id = $pkg_proc_id ORDER BY procedure_price_id DESC LIMIT 1");
                        $priceRow = mysqli_fetch_assoc($priceQ);
                        $price_at_avail = isset($priceRow['procedure_price']) ? floatval($priceRow['procedure_price']) : 0;
                        // Insert with price
                        $proc_sql = "INSERT INTO patient_service_proc (avail_id, procedure_id, custom_proc, group_id, custom_group_proc, price_at_avail)
                                    VALUES (?, ?, ?, ?, ?, ?)";
                        $proc_stmt_new = mysqli_prepare($con, $proc_sql);
                        mysqli_stmt_bind_param($proc_stmt_new, 'iisssd', $avail_id, $pkg_proc_id, $null, $group_id, $null, $price_at_avail);
                        if (!mysqli_stmt_execute($proc_stmt_new)) throw new Exception('Failed to insert package procedure with price');
                        mysqli_stmt_close($proc_stmt_new);
                    }
                }
            }

            // -- 2. Insert non-package "add-on" procedures (predefined) --
            if (is_array($proc_ids)) {
                foreach ($proc_ids as $value) {
                    $parts = explode(':', $value);
                    $proc_id = isset($parts[0]) && is_numeric($parts[0]) ? (int)$parts[0] : null;
                    if (!$selected_package_id || !in_array($proc_id, $official_package_procs)) {
                        $group_id = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : null;
                        if ($group_id !== null) {
                            $checkGroup = mysqli_query($con, "SELECT group_id FROM procedure_group_tbl WHERE group_id = $group_id");
                            if (mysqli_num_rows($checkGroup) === 0) $group_id = null;
                        }
                        $null = null;
                        $priceQ = mysqli_query($con, "SELECT procedure_price FROM procedure_price_tbl WHERE procedure_id = $proc_id ORDER BY procedure_price_id DESC LIMIT 1");
                        $priceRow = mysqli_fetch_assoc($priceQ);
                        $price_at_avail = isset($priceRow['procedure_price']) ? floatval($priceRow['procedure_price']) : 0;
                        $proc_sql = "INSERT INTO patient_service_proc (avail_id, procedure_id, custom_proc, group_id, custom_group_proc, price_at_avail)
                                    VALUES (?, ?, ?, ?, ?, ?)";
                        $proc_stmt_add = mysqli_prepare($con, $proc_sql);
                        mysqli_stmt_bind_param($proc_stmt_add, 'iisssd', $avail_id, $proc_id, $null, $group_id, $null, $price_at_avail);
                        if (!mysqli_stmt_execute($proc_stmt_add)) throw new Exception('Failed to insert add-on procedure with price');
                        mysqli_stmt_close($proc_stmt_add);
                    }
                }
            }

            // -- 3. Custom single procedure --
            if (!empty($manual_single[$service_id])) {
                $custom_text = trim($manual_single[$service_id]);
                $custom_price = isset($manual_custom_proc_price[$service_id]) && is_numeric($manual_custom_proc_price[$service_id])
                    ? floatval($manual_custom_proc_price[$service_id])
                    : null;
                if ($custom_text !== '') {
                    $null = null;
                    $custom_proc_sql = "INSERT INTO patient_service_proc (avail_id, procedure_id, custom_proc, custom_proc_price, group_id, custom_group_proc, custom_group_price)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $custom_proc_stmt = mysqli_prepare($con, $custom_proc_sql);
                    mysqli_stmt_bind_param($custom_proc_stmt, 'iisdids', $avail_id, $null, $custom_text, $custom_price, $null, $null, $null);
                    if (!mysqli_stmt_execute($custom_proc_stmt)) throw new Exception('Failed to insert custom single procedure');
                    mysqli_stmt_close($custom_proc_stmt);
                }
            }

            // -- 4. Custom group procedure --
            if (!empty($other_proc_group) && is_array($other_proc_group)) {
                foreach ($other_proc_group as $group_id_raw => $custom_group_text) {
                    $group_id = (int)$group_id_raw;
                    $custom_group_text = trim($custom_group_text);
                    $custom_group_price = isset($other_proc_group_price[$group_id]) && is_numeric($other_proc_group_price[$group_id])
                        ? floatval($other_proc_group_price[$group_id])
                        : null;
                    if ($custom_group_text === '') continue;
                    mysqli_stmt_bind_param($group_service_stmt, 'i', $group_id);
                    mysqli_stmt_execute($group_service_stmt);
                    $result = mysqli_stmt_get_result($group_service_stmt);
                    $row = $result ? mysqli_fetch_assoc($result) : null;
                    if ($row && (int)$row['service_id'] === $service_id) {
                        $null = null;
                        $custom_group_proc_sql = "INSERT INTO patient_service_proc (avail_id, procedure_id, custom_proc, custom_proc_price, group_id, custom_group_proc, custom_group_price)
                                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $custom_group_proc_stmt = mysqli_prepare($con, $custom_group_proc_sql);
                        mysqli_stmt_bind_param($custom_group_proc_stmt, 'iisdisd', $avail_id, $null, $null, $null, $group_id, $custom_group_text, $custom_group_price);
                        if (!mysqli_stmt_execute($custom_group_proc_stmt)) throw new Exception('Failed to insert custom group procedure');
                        mysqli_stmt_close($custom_group_proc_stmt);
                    }
                }
            }

        } // end foreach selected_services

        mysqli_stmt_close($avail_stmt);
        mysqli_stmt_close($proc_stmt);
        if (isset($group_service_stmt)) mysqli_stmt_close($group_service_stmt);

    // // --- Compute per-avail subtotals ---
    // $avail_subtotals = [];
    // foreach ($inserted_avail_ids as $avail_id) {
    //     $sum = 0;
    //     $procs = mysqli_query($con, "SELECT COALESCE(custom_proc_price, custom_group_price, price_at_avail, 0) AS proc_price FROM patient_service_proc WHERE avail_id = $avail_id");
    //     while ($row = mysqli_fetch_assoc($procs)) {
    //         $sum += floatval($row['proc_price']);
    //     }
    //     $avail_subtotals[$avail_id] = $sum;
    // }
    // $total_subtotal = array_sum($avail_subtotals);

    // // --- BILLING FIELDS ---
    // $or_number = isset($_POST['or_number']) && trim($_POST['or_number']) !== '' ? trim($_POST['or_number']) : '--';
    // $discount_id = isset($_POST['discount_id']) && $_POST['discount_id'] !== '' ? intval($_POST['discount_id']) : null;
    // $custom_discount = isset($_POST['custom_discount_value']) ? floatval($_POST['custom_discount_value']) : 0.00;
    // $curr_time = date('Y-m-d H:i:s');

    // $discount_name = '';
    // $discount_value = 0.00;
    // if ($discount_id) {
    //     $d_res = mysqli_query($con, "SELECT discount_name, discount_value FROM discount_tbl WHERE discount_id = $discount_id LIMIT 1");
    //     $d_row = mysqli_fetch_assoc($d_res);
    //     $discount_name = $d_row['discount_name'] ?? '';
    //     $discount_value = (float)($d_row['discount_value'] ?? 0);
    // } elseif ($custom_discount > 0) {
    //     $discount_name = 'Custom';
    //     $discount_value = $custom_discount;
    // }
    // $discount_amount = 0;
    // if ($discount_value > 0) {
    //     $discount_amount = $total_subtotal * ($discount_value / 100);
    // }

    // // --- Insert billing for each avail ---
    // foreach ($inserted_avail_ids as $avail_id) {
    //     // Get service_id for this avail (you can use your own mapping if you track it earlier)
    // $svcRes = mysqli_query($con, "SELECT service_id FROM patient_service_avail WHERE avail_id = $avail_id LIMIT 1");
    // $svcRow = mysqli_fetch_assoc($svcRes);
    // $service_id = $svcRow ? (int)$svcRow['service_id'] : 0;

    // // Is this avail part of a package?
    // $is_package = $selected_package_id && in_array($service_id, $package_service_ids);

    // if ($is_package) {
    //     // Use package price only!
    //     $pkgQ = mysqli_query($con, "SELECT discount_price FROM clinic_packages WHERE package_id = $selected_package_id");
    //     $pkg = mysqli_fetch_assoc($pkgQ);
    //     $package_price = $pkg['discount_price'] ?? 0;

    //     $avail_sub = $package_price;
    //     $this_discount_name = 'Package';
    //     $this_discount_value = 0;
    //     $avail_discount = 0;
    //     $final_total = $package_price;
    // } else {
    //     $avail_sub = $avail_subtotals[$avail_id];
    //     $proportion = $total_subtotal > 0 ? ($avail_sub / $total_subtotal) : 0;
    //     $avail_discount = $discount_amount * $proportion;
    //     $final_total = $avail_sub - $avail_discount;
    //     $this_discount_name = $discount_name;
    //     $this_discount_value = $discount_value;
    // }

    //     $pay_stmt = mysqli_prepare($con, "
    //         INSERT INTO billing_tbl
    //         (avail_id, or_number, amount_total, discount_name, discount_value, discount_amount, custom_discount_value, billing_date)
    //         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    //     ");
    //     mysqli_stmt_bind_param(
    //         $pay_stmt,
    //         'isdsddds',
    //         $avail_id,         // i
    //         $or_number,        // s
    //         $avail_sub,        // d - subtotal per avail
    //         $this_discount_name,    // s
    //         $this_discount_value,   // d
    //         $final_total,   // d - total
    //         $custom_discount,  // d
    //         $curr_time         // s
    //     );
    //     if (!mysqli_stmt_execute($pay_stmt)) {
    //         $error = mysqli_stmt_error($pay_stmt);
    //         throw new Exception("billing insert failed: $error");
    //     }
    //     mysqli_stmt_close($pay_stmt);
    // }

    // --- Compute per-avail subtotals (package and/or add-on procs) ---
$avail_subtotals = [];
$addon_subtotals = []; // For discount split

foreach ($inserted_avail_ids as $avail_id) {
    $svcRes = mysqli_query($con, "SELECT service_id FROM patient_service_avail WHERE avail_id = $avail_id LIMIT 1");
    $svcRow = mysqli_fetch_assoc($svcRes);
    $service_id = $svcRow ? (int)$svcRow['service_id'] : 0;
    $is_package = $selected_package_id && in_array($service_id, $package_service_ids);

    $package_price = 0;
    if ($is_package) {
        $pkgQ = mysqli_query($con, "SELECT discount_price FROM clinic_packages WHERE package_id = $selected_package_id");
        $pkg = mysqli_fetch_assoc($pkgQ);
        $package_price = isset($pkg['discount_price']) ? floatval($pkg['discount_price']) : 0;
    }

    $addon_total = 0;
    $procs = mysqli_query(
        $con, 
        "SELECT COALESCE(custom_proc_price, custom_group_price, price_at_avail, 0) AS proc_price, procedure_id 
         FROM patient_service_proc WHERE avail_id = $avail_id"
    );
    while ($row = mysqli_fetch_assoc($procs)) {
        $proc_id = (int)$row['procedure_id'];
        if ($is_package) {
            if (!in_array($proc_id, $official_package_procs)) {
                $addon_total += floatval($row['proc_price']);
            }
        } else {
            $addon_total += floatval($row['proc_price']);
        }
    }
    $avail_subtotals[$avail_id] = $package_price + $addon_total;
    $addon_subtotals[$avail_id] = $addon_total;
}
$total_subtotal = array_sum($avail_subtotals);
$total_addonsub = array_sum($addon_subtotals);

// --- BILLING FIELDS ---
$or_number = isset($_POST['or_number']) && trim($_POST['or_number']) !== '' ? trim($_POST['or_number']) : '--';
$discount_id = isset($_POST['discount_id']) && $_POST['discount_id'] !== '' ? intval($_POST['discount_id']) : null;
$custom_discount = isset($_POST['custom_discount_value']) ? floatval($_POST['custom_discount_value']) : 0.00;
$curr_time = date('Y-m-d H:i:s');

$discount_name = '';
$discount_value = 0.00;
if ($discount_id) {
    $d_res = mysqli_query($con, "SELECT discount_name, discount_value FROM discount_tbl WHERE discount_id = $discount_id LIMIT 1");
    $d_row = mysqli_fetch_assoc($d_res);
    $discount_name = $d_row['discount_name'] ?? '';
    $discount_value = (float)($d_row['discount_value'] ?? 0);
} elseif ($custom_discount > 0) {
    $discount_name = 'Custom';
    $discount_value = $custom_discount;
}
$discount_amount = 0;
if ($discount_value > 0) {
    $discount_amount = $total_addonsub * ($discount_value / 100); // Only add-on proc subtotal gets discount
}

// --- Insert billing for each avail ---
foreach ($inserted_avail_ids as $avail_id) {
    $svcRes = mysqli_query($con, "SELECT service_id FROM patient_service_avail WHERE avail_id = $avail_id LIMIT 1");
    $svcRow = mysqli_fetch_assoc($svcRes);
    $service_id = $svcRow ? (int)$svcRow['service_id'] : 0;
    $is_package = $selected_package_id && in_array($service_id, $package_service_ids);

    $avail_sub = $avail_subtotals[$avail_id];
    $addon_sub = $addon_subtotals[$avail_id];

     $this_discount_name = $discount_name;
    $this_discount_value = $discount_value;

    if ($is_package) {
    // discount only on the add-on portion of this avail
    $avail_discount = $addon_sub * ($discount_value / 100);
    $final_total = $avail_sub - $avail_discount;
} else {
    // normal non-package avail: discount applies to entire avail
    $avail_discount = $avail_sub * ($discount_value / 100);
    $final_total = $avail_sub - $avail_discount;
}

    $pay_stmt = mysqli_prepare($con, "
        INSERT INTO billing_tbl
        (avail_id, or_number, amount_total, discount_name, discount_value, discount_amount, custom_discount_value, billing_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param(
        $pay_stmt,
        'isdsddds',
        $avail_id,
        $or_number,
        $avail_sub,
        $this_discount_name,
        $this_discount_value,
        $final_total,
        $custom_discount,
        $curr_time
    );
    if (!mysqli_stmt_execute($pay_stmt)) {
        $error = mysqli_stmt_error($pay_stmt);
        throw new Exception("billing insert failed: $error");
    }
    mysqli_stmt_close($pay_stmt);
}
// --- End billing ---


    // --- Rest of your commit/response block ---
    mysqli_commit($con);
    echo json_encode(['status' => 'success', 'message' => 'Service, Procedures, and billing saved!']);
    exit;
} catch (Exception $e) {
    mysqli_rollback($con);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
}


// Get patient ID
$patient_id = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : 0;

if ($patient_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid patient selected.']);
    exit;
}

// Fetch patient info for header
$patientRes = mysqli_query($con, "SELECT * FROM patient_info_tbl WHERE patient_id = $patient_id AND is_archived = 0");
$pat = mysqli_fetch_assoc($patientRes);

if (!$pat) {
    echo json_encode(['status' => 'error', 'message' => 'Patient not found.']);
    exit;
}

// Fetch services for selection (same as before)
$services = mysqli_query($con, "SELECT * FROM clinic_service_tbl WHERE is_archived = 0 ORDER BY service_name ASC");

?>

<div class="page-title"><div class="title">
    Avail New Service for: <strong><?= htmlspecialchars($pat['patient_fname'] . ' ' . $pat['patient_lname']); ?></strong>
</div></div>

<div class ="add-account-card">

<form class = "add-account-form" method="POST" id="availServiceForm">
    <input type="hidden" name="patient_id" value="<?= $patient_id ?>">

     <!-- ===== REQUEST FORM SECTION ===== -->
        <div class="form-section">
            <div class="form-section-title">
                <i class="fa-solid fa-clipboard-list"></i> Request Form
            </div>

            <!-- Service Selection Header -->
            <div class="laboratory-header">
                <h2>Select Service(s)</h2>
                <p>Please select which service(s) this patient needs:</p>
            </div>

            <!-- Dynamic Service Checkboxes -->
            <div id="serviceSelectionForm" class="service-options">
                <?php 
                mysqli_data_seek($services, 0);
                while ($service = mysqli_fetch_assoc($services)) : 
                    // Map service names to icons (you can add more)
                    $icons = [
                        'LABORATORY' => 'fa-vial',
                        'X-RAY' => 'fa-x-ray',
                        'ULTRASOUND' => 'fa-wave-square',
                        '2D ECHO' => 'fa-heart',
                        'ECG' => 'fa-heartbeat',
                        'DRUG TESTING' => 'fa-syringe'
                    ];
                    $icon = $icons[$service['service_name']] ?? 'fa-stethoscope';
                ?>
                <label class="service-option">
                    <input type="checkbox" 
                           id="service-<?= $service['service_id'] ?>" 
                           name="selected_services[]" 
                           value="<?= $service['service_id'] ?>"
                           data-service-id="<?= $service['service_id'] ?>"
                           data-service-name="<?= htmlspecialchars($service['service_name']) ?>">
                    <i class="fas <?= $icon ?>"></i> <?= htmlspecialchars($service['service_name']) ?>
                </label>
                <?php endwhile; ?>
            </div>

            <!-- Dynamic Service Fields (Hidden by default) -->
            <?php 
            mysqli_data_seek($services, 0);
            while ($service = mysqli_fetch_assoc($services)) : 
                $serviceId = $service['service_id'];
                $serviceName = $service['service_name'];
            ?>
            <div class="service-group" data-service-id="<?= $serviceId ?>">
                <label>
                    <i class="fas <?= $icons[$serviceName] ?? 'fa-stethoscope' ?>"></i>
                    <strong><?= htmlspecialchars($serviceName) ?></strong>
                </label>
                <div class="service-fields" id="fields-<?= $serviceId ?>">
                    <label>Requested By</label>
                    <input type="text" name="requested_by[<?= $serviceId ?>]" disabled>

                    <label>Procedure</label>
                    <input type="text" name="manual_procedure[<?= $serviceId ?>]" disabled>

                    <label>Custom Price (₱)</label>
                    <input type="number" name="manual_custom_proc_price[<?= $serviceId ?>]" step="0.01" min="0" disabled>

                    <label>Brief History</label>
                    <input type="text" name="history[<?= $serviceId ?>]" disabled>
                </div>
            </div>
            <?php endwhile; ?>

            <!-- Dynamic Procedure Sections -->
            <?php
            mysqli_data_seek($services, 0);
            while ($service = mysqli_fetch_assoc($services)) :
                $serviceId = $service['service_id'];
                $serviceName = $service['service_name'];
                
                // Fetch groups for this service
                $groups = mysqli_query($con, "
                    SELECT * FROM procedure_group_tbl 
                    WHERE service_id = $serviceId 
                    ORDER BY group_name ASC
                ");
                
                // Fetch single procedures (ungrouped)
                $single_procs = mysqli_query($con, "
                    SELECT p.*, pp.procedure_price 
                    FROM procedure_tbl p
                    LEFT JOIN procedure_price_tbl pp ON p.procedure_id = pp.procedure_id
                    WHERE p.service_id = $serviceId 
                    AND (p.group_id IS NULL OR p.group_id = 0)
                    AND (p.is_archived = 0 OR p.is_archived IS NULL)
                    ORDER BY p.procedure_name ASC
                ");
            ?>
            <div class="form-section lab-section" id="section-<?= $serviceId ?>" data-service-id="<?= $serviceId ?>">
                <div class="form-section-title">
                    <i class="fa-solid fa-microscope"></i> <?= htmlspecialchars($serviceName) ?> Procedures
                </div>
                <div class="lab-categories">
                    
                    <!-- Single Procedures -->
                    <?php if (mysqli_num_rows($single_procs) > 0): ?>
                    <div class="lab-category">
                        <strong>Single Procedures</strong>
                        <div class="lab-checkboxes">
                            <?php while ($proc = mysqli_fetch_assoc($single_procs)) : ?>
                            <label>
                                <input type="checkbox" 
                                       name="procedures[<?= $serviceId ?>][]" 
                                       value="<?= $proc['procedure_id'] ?>:0"
                                       data-price="<?= $proc['procedure_price'] ?? 0 ?>"
                                       disabled>
                                <?= htmlspecialchars($proc['procedure_name']) ?>
                                <?php $procedure_price = isset($row['procedure_price']) ? $row['procedure_price'] : 0;?>
                                 <span class="text-muted" style="font-weight:bold;">(₱<?= number_format($proc['procedure_price'] ?? 0,2) ?>)</span>
                            </label>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Grouped Procedures -->
                    <?php while ($group = mysqli_fetch_assoc($groups)) : 
                        $groupId = $group['group_id'];
                        $procedures = mysqli_query($con, "
                            SELECT p.*, pp.procedure_price 
                            FROM procedure_tbl p
                            LEFT JOIN procedure_price_tbl pp ON p.procedure_id = pp.procedure_id
                            WHERE p.group_id = $groupId
                            AND (p.is_archived = 0 OR p.is_archived IS NULL)
                            ORDER BY p.procedure_name ASC
                        ");
                    ?>
                    <div class="lab-category">
                        <strong><?= htmlspecialchars($group['group_name']) ?></strong>
                        <div class="lab-checkboxes">
                            <?php while ($proc = mysqli_fetch_assoc($procedures)) : ?>
                            <label>
                                <input type="checkbox"
                                       name="procedures[<?= $serviceId ?>][]"
                                       value="<?= $proc['procedure_id'] ?>:<?= $groupId ?>"
                                       data-price="<?= $proc['procedure_price'] ?? 0 ?>"
                                       disabled>
                                     
                                <?= htmlspecialchars($proc['procedure_name']) ?>
                                 <span class="text-muted" style="font-weight:bold;"> (₱<?= number_format($proc['procedure_price'] ?? 0,2)?>)</span>
                            </label>
                            <?php endwhile; ?>
                            
                            <label>Others:
                                <input type="text" 
                                       name="other_proc_group[<?= $groupId ?>]" 
                                       placeholder="Specify..." 
                                       disabled />
                            </label>
                            <label>Custom Group Price (₱)
                                <input type="number" 
                                       name="other_proc_group_price[<?= $groupId ?>]" 
                                       step="0.01" min="0" 
                                       disabled />
                            </label>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endwhile; ?>

        </div>

        <div class="form-section">
            <div class="form-group">
                <label><i class="fas fa-box"></i> Select Package</label>
                <select id="packageSelect" name="selected_package">
                <option value="">-- None --</option>
                <?php
                $pkgRes = mysqli_query($con, "SELECT * FROM clinic_packages WHERE is_archived = 0 ORDER BY package_name ASC");
                while ($pkg = mysqli_fetch_assoc($pkgRes)) {
                    echo "<option value='{$pkg['package_id']}' data-service='" . htmlspecialchars(json_encode($pkg['services'] ?? []), ENT_QUOTES) . "'>{$pkg['package_name']} (₱" . number_format($pkg['discount_price'], 2) . ")</option>";
                }
                ?>
                </select>
            </div>
        </div>


        <!-- ===== BILLING SECTION ===== -->
        <div class="form-section billing-section" id="billingSection" style="display:none;">
            <div class="form-section-title">
                <i class="fa-solid fa-receipt"></i> Billing Details
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>OR Number</label>
                    <input type="text" id="orNumber" name="or_number" placeholder="Enter OR Number" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Total Amount (₱)</label>
                    <input type="text" id="totalAmount" name="total_amount" value="0.00" readonly>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Choose Discount</label>
                    <select id="discountSelect" name="discount_id">
                        <option value="" selected>No Discount</option>
                        <?php
                        $discounts = mysqli_query($con, "SELECT * FROM discount_tbl WHERE is_archived = 0 ORDER BY discount_name ASC");
                        while ($d = mysqli_fetch_assoc($discounts)) {
                            echo "<option value='{$d['discount_id']}' data-value='{$d['discount_value']}'>{$d['discount_name']} ({$d['discount_value']}%)</option>";
                        }
                        ?>
                    </select>
                    <input type="hidden" name="discount_value" id="discount_value_input" value="0">
                </div>
                <div class="form-group">
                    <label>Custom Discount (%)</label>
                    <input type="number" id="customDiscount" name="custom_discount_value" placeholder="e.g. 12" min="1" max="100">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Final Amount After Discount (₱)</label>
                    <input type="text" id="finalAmount" name="discount_amount" value="0.00" readonly>
                </div>
            </div>
        </div>

        <!-- ===== FORM ACTIONS ===== -->
        <div class="form-actions">
            <a href="#" id="cancelAvailService" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-circle-xmark"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary" name="add_patient">
                <i class="fa-solid fa-floppy-disk"></i> Save
            </button>
        </div>
    </form>
</div>