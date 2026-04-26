<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="SAMDC.csv"');

include '../../includes/session_check.php';

// ✅ Only allow Administrator and Receptionist to export
$userRole = $_SESSION['role_name'] ?? '';
if ($userRole !== 'Administrator' && $userRole !== 'Receptionist') {
    die('ERROR! CANNOT EXPORT!');
}

// Get filters
$type = $_GET['report_type'] ?? '';
$year = ($type === 'Monthly & Annual Sale')
    ? ($_GET['report_year'] ?? date('Y'))
    : ($_GET['report_year_daily'] ?? date('Y'));
$month = $_GET['report_month'] ?? '';
$date = $_GET['report_date'] ?? '';

$output = fopen('php://output', 'w'); // Write direct to output

if ($type === 'Monthly & Annual Sale') {
    fputcsv($output, ["MONTH", "TOTAL SALES $year"]);
    $query = "
        SELECT MONTHNAME(billing_date) AS month, SUM(discount_amount) AS total_sales
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
    $salesData = [];
    $total = 0;
    while ($row = $result->fetch_assoc()) {
        $salesData[$row['month']] = $row['total_sales'];
        $total += $row['total_sales'];
    }
    $allMonths = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];
    foreach ($allMonths as $m) {
        $val = $salesData[$m] ?? 0;
        fputcsv($output, [$m, $val]);
    }
    fputcsv($output, ["TOTAL", $total]);
}
elseif ($type === 'Daily Sale') {
    fputcsv($output, ["DAY", "TOTAL $month $year"]);
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
    $numDays = date('t', strtotime("$month 1 $year"));
    $dailyData = [];
    $total = 0;
    for ($d = 1; $d <= $numDays; $d++) {
        $dayStr = sprintf('%s %02d', $month, $d);
        $dailyData[$dayStr] = 0;
    }
    while ($row = $result->fetch_assoc()) {
        $dayStr = sprintf('%s %02d', $month, $row['day']);
        $dailyData[$dayStr] = $row['total_sales'];
        $total += $row['total_sales'];
    }
    foreach ($dailyData as $day => $val) {
        fputcsv($output, [$day, $val]);
    }
    fputcsv($output, ["TOTAL", $total]);
}
elseif ($type === 'Detailed Daily') {
    fputcsv($output, ["SERIAL #", "OR #", "NAME", "PROCEDURES", "DISCOUNT", "PRICE"]);
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
    $total = 0;

    // while ($row = $result->fetch_assoc()) {
    //     // Get procedures for this avail_id as a comma-separated string
    //     $procList = [];
    //     $procQ = $con->prepare(
    //         "SELECT sp.*, pt.procedure_name FROM patient_service_proc sp
    //          LEFT JOIN procedure_tbl pt ON sp.procedure_id = pt.procedure_id
    //          WHERE sp.avail_id = ?"
    //     );
    //     $procQ->bind_param('i', $row['avail_id']);
    //     $procQ->execute();
    //     $procRes = $procQ->get_result();
    //     while ($procRow = $procRes->fetch_assoc()) {
    //         $procName = !empty($procRow['custom_proc']) ? $procRow['custom_proc'] : $procRow['procedure_name'];
    //         $procList[] = $procName;
    //     }
    //     $procQ->close();
    //     $proceduresText = implode(", ", $procList);

           while ($row = $result->fetch_assoc()) {
                // Build PROCEDURES cell: package name (if any) + add-ons only
                $proceduresText = '';
                $addonList = [];
                $packageName = !empty($row['package_name']) ? $row['package_name'] : '';
                $pkgProcIds = [];
                if ($packageName) {
                    // Find official package id by name
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
                    // No package: show all procedures
                    $procList = [];
                    while ($procRow = $res2->fetch_assoc()) {
                        $procName = !empty($procRow['custom_proc']) ? $procRow['custom_proc'] : $procRow['procedure_name'];
                        $procList[] = $procName;
                    }
                    $proceduresText = implode(", ", $procList);
                }
                $stmt2->close();

                fputcsv($output, [
                    $row['case_no'],
                    $row['or_number'],
                    $row['patient_lname'] . ' ' . $row['patient_fname'],
                    $proceduresText,
                    $row['discount'],
                    $row['price']
                ]);
                $total += $row['price'];
            }

    fputcsv($output, ["TOTAL", "", "", "", "", $total]);
}


fclose($output);
?>
