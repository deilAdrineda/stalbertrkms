<?php

include '../../includes/session_check.php';

$type = $_POST['report_type'] ?? '';
$year = $_POST['report_type'] === 'Monthly & Annual Sale'
    ? $_POST['report_year'] ?? date('Y')
    : $_POST['report_year_daily'] ?? date('Y');
$month = $_POST['report_month'] ?? '';

$salesData = [];
$dailyData = [];
$total = 0;

if ($type === 'Monthly & Annual Sale') {
        $query = "
        SELECT MONTH(billing_date) AS month_num, MONTHNAME(billing_date) AS month, SUM(discount_amount) AS total_sales
        FROM billing_tbl b
        JOIN patient_service_avail a ON a.avail_id = b.avail_id
        WHERE YEAR(billing_date) = ? AND a.status = 'Completed'
        GROUP BY MONTH(billing_date)
        ORDER BY MONTH(billing_date)
    ";

    $stmt = $con->prepare($query);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $salesData[$row['month']] = $row['total_sales'];
        $total += $row['total_sales'];
    }
    $allMonths = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];
    $salesDataFilled = [];
    foreach ($allMonths as $m) {
        $salesDataFilled[$m] = $salesData[$m] ?? 0;
    }

    echo '<table class="data-table"><thead><tr><th>MONTH</th><th>TOTAL SALES ' . htmlspecialchars($year) . '</th></tr></thead><tbody>';
    foreach ($salesDataFilled as $m => $val) {
        echo '<tr><td>' . $m . '</td><td>' . number_format($val, 2) . '</td></tr>';
    }
    echo '<tr><td><b>TOTAL</b></td><td><b>' . number_format($total, 2) . '</b></td></tr></tbody></table>';
}
if ($type === 'Daily Sale') {
   $query = "
    SELECT DAY(billing_date) AS day, SUM(discount_amount) AS total_sales
    FROM billing_tbl b
    JOIN patient_service_avail a ON a.avail_id = b.avail_id
    WHERE MONTHNAME(billing_date) = ? AND YEAR(billing_date) = ? AND a.status = 'Completed'
    GROUP BY DAY(billing_date)
    ORDER BY DAY(billing_date)
";
    $stmt = $con->prepare($query);
    $stmt->bind_param('si', $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $numDays = date('t', strtotime("$month 1 $year")); // Number of days in selected month
    for ($d = 1; $d <= $numDays; $d++) {
        $dayStr = sprintf('%s %02d', $month, $d);
        $dailyData[$dayStr] = 0;
    }
    $dailyTotal = 0;
    while ($row = $result->fetch_assoc()) {
        $dayStr = sprintf('%s %02d', $month, $row['day']);
        $dailyData[$dayStr] = $row['total_sales'];
        $dailyTotal += $row['total_sales'];
    }
    $total = $dailyTotal;
    echo '<table class="data-table"><thead><tr><th>' . htmlspecialchars($month) . ' ' . htmlspecialchars($year) . '</th><th>Total</th></tr></thead><tbody>';
    foreach ($dailyData as $day => $val) {
        echo '<tr><td>' . $day . '</td><td>' . number_format($val, 2) . '</td></tr>';
    }
    echo '<tr><td><b>TOTAL</b></td><td><b>' . number_format($total, 2) . '</b></td></tr></tbody></table>';
}

if ($type === 'Detailed Daily') {
    // Assume date in YYYY-MM-DD
    $date = $_POST['report_date'] ?? date('Y-m-d');
    $query = "
        SELECT 
            a.case_no,
            b.or_number,
            p.patient_fname,
            p.patient_lname,
            b.discount_value AS discount,
            b.discount_amount AS price,
            a.avail_id,
            a.package_name 
        FROM billing_tbl b
        JOIN patient_service_avail a ON b.avail_id = a.avail_id
        JOIN patient_info_tbl p ON a.patient_id = p.patient_id
        WHERE DATE(b.billing_date) = ? AND a.status = 'Completed'
        ORDER BY b.billing_id ASC
    ";

    $stmt = $con->prepare($query);
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $result = $stmt->get_result();

    // Display table similar to Excel
    $total = 0;
    echo '<table class="data-table"><thead>
      <tr>
        <th>SERIAL #</th>
        <th>OR #</th>
        <th>NAME</th>
        <th>PROCEDURES</th>
        <th>DISCOUNT</th>
        <th>PRICE</th>
      </tr></thead><tbody>';

    // while ($row = $result->fetch_assoc()) {
    //     // Fetch procedures for this avail_id:
    //     $procList = [];
    //     $sql2 = "SELECT sp.*, pt.procedure_name FROM patient_service_proc sp
    //              LEFT JOIN procedure_tbl pt ON sp.procedure_id = pt.procedure_id
    //              WHERE sp.avail_id = ?";
    //     $stmt2 = $con->prepare($sql2);
    //     $stmt2->bind_param('i', $row['avail_id']);
    //     $stmt2->execute();
    //     $res2 = $stmt2->get_result();
    //     while ($procRow = $res2->fetch_assoc()) {
    //         // Use either custom_proc or default procedure_name
    //         $procName = !empty($procRow['custom_proc']) ? $procRow['custom_proc'] : $procRow['procedure_name'];
    //         $procList[] = $procName;
    //     }
    //     $proceduresText = implode(", ", $procList);

        while ($row = $result->fetch_assoc()) {
            // Build PROCEDURES cell: package name (if any) + add-ons only
            $proceduresText = '';
            $addonList = [];
            $packageName = !empty($row['package_name']) ? $row['package_name'] : '';
            $pkgProcIds = [];
            if ($packageName) {
                // Find the corresponding package_id by package_name (if you don't have selected_package)
                $pkgQ = mysqli_query($con, "SELECT package_id FROM clinic_packages WHERE package_name = '" . mysqli_real_escape_string($con, $packageName) . "' LIMIT 1");
                $pkgRow = mysqli_fetch_assoc($pkgQ);
                $pkgIdFound = $pkgRow['package_id'] ?? 0;
                if ($pkgIdFound) {
                    $pidQ = mysqli_query($con, "SELECT procedure_id FROM clinic_packages_procedures WHERE package_id = $pkgIdFound");
                    while ($pidRow = mysqli_fetch_assoc($pidQ)) $pkgProcIds[] = $pidRow['procedure_id'];
                }
            }
            $sql2 = "SELECT sp.*, pt.procedure_name FROM patient_service_proc sp
                    LEFT JOIN procedure_tbl pt ON sp.procedure_id = pt.procedure_id
                    WHERE sp.avail_id = ?";
            $stmt2 = $con->prepare($sql2);
            $stmt2->bind_param('i', $row['avail_id']);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            if ($packageName) {
                while ($procRow = $res2->fetch_assoc()) {
                    $inPackage = $procRow['procedure_id'] && in_array($procRow['procedure_id'], $pkgProcIds);
                    if (!$inPackage || !empty($procRow['custom_proc'])) {
                        $procName = !empty($procRow['custom_proc']) ? $procRow['custom_proc'] : $procRow['procedure_name'];
                        $addonList[] = $procName;
                    }
                }
                $proceduresText = $packageName;
                if (count($addonList)) {
                    $proceduresText .= ', ' . implode(', ', $addonList);
                }
            } else {
                // No package; just show all procs
                $procList = [];
                while ($procRow = $res2->fetch_assoc()) {
                    $procName = !empty($procRow['custom_proc']) ? $procRow['custom_proc'] : $procRow['procedure_name'];
                    $procList[] = $procName;
                }
                $proceduresText = implode(", ", $procList);
            }
            $stmt2->close();

            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['case_no']) . '</td>'; // Serial # (case_no!)
            echo '<td>' . htmlspecialchars($row['or_number']) . '</td>';
            echo '<td>' . htmlspecialchars($row['patient_lname'] . ' ' . $row['patient_fname']) . '</td>';
            echo '<td>' . htmlspecialchars($proceduresText) . '</td>'; // Procedures
            echo '<td>' . htmlspecialchars($row['discount']) . '</td>';
            echo '<td>' . number_format($row['price'], 2) . '</td>';
            echo '</tr>';
            $total += $row['price'];
        }

    echo '<tr style="font-weight:bold;background: #ffe066;"><td colspan="5" style="text-align:right;">TOTAL</td><td>' . number_format($total, 2) . '</td></tr>';
    echo '</tbody></table>';
}

?>