<?php
include '../../includes/session_check.php';

// ✅ Get current user's role
$userRole = $_SESSION['role_name'] ?? '';
$canExport = ($userRole === 'Administrator' || $userRole === 'Receptionist');

// Get filter selections
$type = $_GET['report_type'] ?? '';
$year = $_GET['report_year'] ?? '';
$month = $_GET['report_month'] ?? '';

$salesData = [];
$dailyData = [];
$total = 0;
$reportGenerated = isset($_GET['report_type']); // Flag for submitted filter

if ($reportGenerated) {
    if ($type === 'Monthly & Annual Sale') {
        $query = "
            SELECT MONTH(billing_date) AS month_num, MONTHNAME(billing_date) AS month, SUM(amount_total) AS total_sales
            FROM billing_tbl b
            JOIN patient_service_avail a ON a.avail_id = b.avail_id
            WHERE YEAR(b.billing_date) = ? AND a.status = 'Completed'
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

        // Ensure all 12 months are represented
        $allMonths = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];
        $salesDataFilled = [];
        foreach ($allMonths as $m) {
            $salesDataFilled[$m] = $salesData[$m] ?? 0;
        }
    }
    
    if ($type === 'Daily Sale') {
        $year = $_POST['report_year_daily'] ?? date('Y');
        $month = $_POST['report_month'] ?? '';
        
        $query = "
            SELECT DAY(billing_date) AS day, SUM(amount_total) AS total_sales
            FROM billing_tbl b
            JOIN patient_service_avail a ON a.avail_id = b.avail_id
            WHERE MONTHNAME(b.billing_date) = ? AND YEAR(b.billing_date) = ? AND a.status = 'Completed'
            GROUP BY DAY(billing_date)
            ORDER BY DAY(billing_date)
        ";
        
        $stmt = $con->prepare($query);
        $stmt->bind_param('si', $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();

        $numDays = date('t', strtotime("$month 1 $year"));
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
    }
}
?>

<div class="page-title">
    <div class="title">Reports</div>
    <div class="action-buttons"></div>
</div>

<!-- Filter Controls -->
<div class="filter-bar" style="margin-bottom: 20px; padding: 15px; background: var(--color-surface); border-radius: var(--radius-lg); display: flex; gap: 15px; align-items: center; border: 1px solid var(--color-border);">
    <form method="post" id="reportFilterForm" autocomplete="off">
        <label for="reportType">Report Type:</label>
        <select name="report_type" id="reportType">
            <option value="">Select Report Type</option>
            <option value="Detailed Daily">Daily Transactions</option>
            <option value="Daily Sale">Daily Sale</option>
            <option value="Monthly & Annual Sale">Monthly & Annual Sale</option>
        </select>

        <!-- Shows ONLY when Report Type is Detailed Daily -->
        <span id="dayFilter" style="display:none;">
            <label for="report_date">Date:</label>
            <input type="date" name="report_date" id="report_date" max="<?= date('Y-m-d') ?>" />
        </span>

        <!-- Shows ONLY when Report Type is Monthly & Annual Sale -->
        <span id="yearFilter" style="display:none;">
            <label for="report_year">Year:</label>
            <select name="report_year" id="report_year">
                <?php for ($yr = date('Y'); $yr >= 2020; $yr--): ?>
                    <option value="<?= $yr ?>"><?= $yr ?></option>
                <?php endfor; ?>
            </select>
        </span>
        
        <!-- Shows ONLY when Report Type is Daily Sale -->
        <span id="monthFilter" style="display:none;">
            <label for="report_month">Month:</label>
            <select name="report_month" id="report_month">
                <?php
                foreach (range(1,12) as $m) {
                    $monthName = date('F', mktime(0,0,0,$m,10));
                    echo "<option value='$monthName'>$monthName</option>";
                }
                ?>
            </select>
            <label for="report_year_daily">Year:</label>
            <select name="report_year_daily" id="report_year_daily">
                <?php for ($yr = date('Y'); $yr >= 2020; $yr--): ?>
                    <option value="<?= $yr ?>"><?= $yr ?></option>
                <?php endfor; ?>
            </select>
        </span>
        
        <button type="submit" class="btn btn-primary">Generate</button>
    </form>

    <?php if ($canExport): ?>
        <!-- ✅ ONLY show Export button for Administrator and Receptionist -->
        <button type="button" class="btn btn-success" id="exportExcelBtn">Export to Excel</button>
    <?php endif; ?>
</div>

<div id="reportTableContainer">
    <!-- <div class="report-empty-message">Generate a Report</div> -->
</div>

<!-- Table Render -->
<?php
if ($type === 'Monthly & Annual Sale') {
    echo '<table class="data-table"><thead><tr><th>MONTH</th><th>TOTAL SALES ' . htmlspecialchars($year) . '</th></tr></thead><tbody>';
    foreach ($salesDataFilled as $m => $val) {
        echo '<tr><td>' . $m . '</td><td>' . number_format($val, 2) . '</td></tr>';
    }
    echo '<tr><td><b>TOTAL</b></td><td><b>' . number_format($total, 2) . '</b></td></tr></tbody></table>';
    $periodLabel = "Reporting Period: $year";
} elseif ($type === 'Daily Sale') {
    echo '<table class="data-table"><thead><tr><th>' . htmlspecialchars($month) . ' ' . htmlspecialchars($year) . '</th><th>Total</th></tr></thead><tbody>';
    foreach ($dailyData as $day => $val) {
        echo '<tr><td>' . $day . '</td><td>' . number_format($val, 2) . '</td></tr>';
    }
    echo '<tr><td><b>TOTAL</b></td><td><b>' . number_format($total, 2) . '</b></td></tr></tbody></table>';
    $periodLabel = "Reporting Period: $month $year";
} elseif ($type === 'Detailed Daily') {
    echo '<table class="data-table"><thead>
        <tr>
            <th>SERIAL #</th>
            <th>OR #</th>
            <th>NAME</th>
            <th>PROCEDURES</th>
            <th>DISCOUNT</th>
            <th>PRICE</th>
        </tr></thead><tbody>';
    $total = 0;
    foreach ($dailyDetailsData as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['serial_no']) . '</td>';
        echo '<td>' . htmlspecialchars($row['or_number']) . '</td>';
        echo '<td>' . htmlspecialchars($row['patient_lname'] . ' ' . $row['patient_fname']) . '</td>';
        
        $proceduresText = '';
        if (!empty($row['package_name'])) {
            $addonList = [];
            $pkgProcIds = [];
            $pkgId = $row['selected_package'] ?? null;
            if ($pkgId) {
                $pkgQ = mysqli_query($con, "SELECT procedure_id FROM clinic_packages_procedures WHERE package_id = $pkgId");
                while ($pkgRow = mysqli_fetch_assoc($pkgQ)) $pkgProcIds[] = $pkgRow['procedure_id'];
            }
            $sql2 = "SELECT sp.*, pt.procedure_name FROM patient_service_proc sp
                     LEFT JOIN procedure_tbl pt ON sp.procedure_id = pt.procedure_id
                     WHERE sp.avail_id = ?";
            $stmt2 = $con->prepare($sql2);
            $stmt2->bind_param('i', $row['avail_id']);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            while ($procRow = $res2->fetch_assoc()) {
                $inPackage = $procRow['procedure_id'] && in_array($procRow['procedure_id'], $pkgProcIds);
                if (!$inPackage || !empty($procRow['custom_proc'])) {
                    $procName = !empty($procRow['custom_proc']) ? $procRow['custom_proc'] : $procRow['procedure_name'];
                    $addonList[] = $procName;
                }
            }
            $stmt2->close();
            $proceduresText = $row['package_name'];
            if (count($addonList)) {
                $proceduresText .= ', ' . implode(', ', $addonList);
            }
        } else {
            $procList = [];
            $sql2 = "SELECT sp.*, pt.procedure_name FROM patient_service_proc sp
                     LEFT JOIN procedure_tbl pt ON sp.procedure_id = pt.procedure_id
                     WHERE sp.avail_id = ?";
            $stmt2 = $con->prepare($sql2);
            $stmt2->bind_param('i', $row['avail_id']);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            while ($procRow = $res2->fetch_assoc()) {
                $procName = !empty($procRow['custom_proc']) ? $procRow['custom_proc'] : $procRow['procedure_name'];
                $procList[] = $procName;
            }
            $stmt2->close();
            $proceduresText = implode(", ", $procList);
        }
        echo '<td>' . htmlspecialchars($proceduresText) . '</td>';
        echo '<td>' . htmlspecialchars($row['discount']) . '</td>';
        echo '<td>' . number_format($row['price'], 2) . '</td>';
        echo '</tr>';
        $total += $row['price'];
    }
    echo '<tr style="font-weight:bold;background: #ffe066;"><td colspan="5" style="text-align:right;">TOTAL</td><td>' . number_format($total, 2) . '</td></tr>';
    echo '</tbody></table>';
    $periodLabel = "Reporting Date: " . htmlspecialchars($date);
}
?>

<?php if ($canExport): ?>
    <!-- ✅ ONLY show Export Modal for Administrator and Receptionist -->
    <div class="modal" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post" action="export_report.php">
                <div class="modal-content">
                    <div class="modal-header"><h5>Report Export Details</h5></div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <label>Report Name</label>
                            <input class="form-control" name="export_name" required />
                        </div>
                        <div class="mb-2">
                            <label>Report Type</label>
                            <input class="form-control" name="report_type" value="<?= htmlspecialchars($type) ?>" readonly />
                        </div>
                        <div class="mb-2">
                            <label><?= $periodLabel ?></label>
                            <input class="form-control" name="period_label" value="<?= $periodLabel ?>" readonly />
                            <input type="hidden" name="report_year" value="<?= htmlspecialchars($year) ?>" />
                            <?php if ($type === 'Daily Sale'): ?>
                                <input type="hidden" name="report_month" value="<?= htmlspecialchars($month) ?>" />
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Export & Download</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>