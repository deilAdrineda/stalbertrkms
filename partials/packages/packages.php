<?php
// include '../../includes/connection.php';
include '../../includes/session_check.php';
requireRole(['Administrator']);

function json_resp($status, $message, $extra = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

// ========== AJAX ENDPOINTS ==========

// 1. Get procedures for modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_get_procedures'])) {
    header('Content-Type: application/json');
    $result = mysqli_query($con, "
    SELECT p.procedure_id, p.procedure_name, s.service_name, pp.procedure_price
    FROM procedure_tbl p
    JOIN clinic_service_tbl s ON p.service_id = s.service_id
    LEFT JOIN procedure_price_tbl pp ON p.procedure_id = pp.procedure_id
    WHERE p.is_archived = 0
    ORDER BY s.service_name, p.procedure_name ASC
    ");

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $cat = $row['service_name'] ?: 'Other';
        if (!isset($data[$cat])) $data[$cat] = [];
        $data[$cat][] = [
            'procedure_id' => $row['procedure_id'],
            'procedure_name' => $row['procedure_name'],
            'procedure_price' => $row['procedure_price'] // may be null if missing, default to 0 in JS
        ];
    }
    echo json_encode($data);
    exit;
}

// 2. Add package
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_package'])) {
    header('Content-Type: application/json');
    $name = trim($_POST['package_name'] ?? '');
    $discount = floatval($_POST['discount_value'] ?? 0); // percent
    $reg_price = floatval($_POST['reg_price'] ?? 0);
    $discount_price = floatval($_POST['discount_price'] ?? 0);
    $procedures = $_POST['procedures'] ?? [];
    if ($name === '' || empty($procedures) || $reg_price <= 0) {
        json_resp('error', 'All fields and at least one procedure are required.');
    }
    mysqli_begin_transaction($con);
    try {
        $stmt = mysqli_prepare($con, "INSERT INTO clinic_packages (package_name, discount_value, reg_price, discount_price, date_created) VALUES (?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, 'sddd', $name, $discount, $reg_price, $discount_price);

        mysqli_stmt_execute($stmt);
        $pkgId = mysqli_insert_id($con);
        mysqli_stmt_close($stmt);

        foreach ($procedures as $procId) {
            $stmt2 = mysqli_prepare($con, "INSERT INTO clinic_packages_procedures (package_id, procedure_id) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt2, 'ii', $pkgId, $procId);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);
        }
        mysqli_commit($con);
        json_resp('success', 'Package created!', ['package_id' => $pkgId]);
    } catch (Exception $e) {
        mysqli_rollback($con);
        json_resp('error', 'DB error: ' . $e->getMessage());
    }
    exit;
}

// 3. Table loader (simple, non-AJAX for now)
$query = "SELECT * FROM clinic_packages WHERE is_archived = 0 ORDER BY date_created DESC";
$res = mysqli_query($con, $query);

// 4. edit

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_get_package'])) {
    header('Content-Type: application/json');
    $pkgId = intval($_POST['package_id'] ?? 0);
    if ($pkgId <= 0) json_resp('error', 'Invalid package id');

    // Get package details
    $q = mysqli_query($con, "SELECT * FROM clinic_packages WHERE package_id=$pkgId");
    $pkg = mysqli_fetch_assoc($q);

    // Get the package's procedures (ids)
    $procs = [];
    $q2 = mysqli_query($con, "SELECT procedure_id FROM clinic_packages_procedures WHERE package_id=$pkgId");
    while ($r = mysqli_fetch_assoc($q2)) $procs[] = $r['procedure_id'];
    $pkg['procedures'] = $procs;

    // Get all available procedures grouped by service, with price
    $result = mysqli_query($con, "
        SELECT p.procedure_id, p.procedure_name, s.service_name, pp.procedure_price
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
            'procedure_price' => $row['procedure_price']
        ];
    }
    $pkg['all_procedures'] = $available;

    echo json_encode($pkg); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_edit_package'])) {
    header('Content-Type: application/json');
    $pkgId = intval($_POST['package_id'] ?? 0);
    $name = trim($_POST['package_name'] ?? '');
    $discount = floatval($_POST['discount_value'] ?? 0); // percent
    $reg_price = floatval($_POST['reg_price'] ?? 0);
    $discount_price = floatval($_POST['discount_price'] ?? 0);
    $procedures = $_POST['procedures'] ?? [];
    if ($pkgId <= 0 || $name === '' || empty($procedures) || $reg_price <= 0) {
        json_resp('error', 'All fields and at least one procedure are required.');
    }

    mysqli_begin_transaction($con);
    try {
        // Update main package info
        $stmt = mysqli_prepare($con, "UPDATE clinic_packages SET package_name=?, discount_value=?, reg_price=?, discount_price=? WHERE package_id=?");
        mysqli_stmt_bind_param($stmt, 'sdddi', $name, $discount, $reg_price, $discount_price, $pkgId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Remove old procedures
        mysqli_query($con, "DELETE FROM clinic_packages_procedures WHERE package_id=$pkgId");

        // Re-insert updated procedures
        foreach ($procedures as $procId) {
            $stmt2 = mysqli_prepare($con, "INSERT INTO clinic_packages_procedures (package_id, procedure_id) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt2, 'ii', $pkgId, $procId);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);
        }
        mysqli_commit($con);
        json_resp('success', 'Package updated!', ['package_id' => $pkgId]);
    } catch (Exception $e) {
        mysqli_rollback($con);
        json_resp('error', 'DB error: ' . $e->getMessage());
    }
    exit;
}

// 5. Delete (archive) package

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_package'])) {
    header('Content-Type: application/json');
    $pkgId = intval($_POST['package_id'] ?? 0);
    if ($pkgId <= 0) json_resp('error', 'Invalid package id.');

    mysqli_begin_transaction($con);
    try {
        // Soft delete ("archive") the package (don't actually remove from DB)
        $stmt = mysqli_prepare($con, "UPDATE clinic_packages SET is_archived = 1, date_archived = NOW() WHERE package_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $pkgId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($con);
        json_resp('success', 'Package Removed.');
    } catch (Exception $e) {
        mysqli_rollback($con);
        json_resp('error', 'Database error while archiving package.');
    }
    exit;
}



?>

<!-- PAGE TITLE -->
<div class="page-title">
    <div class="title">Packages</div>
    <div class="action-buttons">
        <button id="addPackageBtn" class="btn btn-primary"><i class="fas fa-plus"></i> Add Package</button>
    </div>
</div>

<!-- PACKAGES TABLE -->
<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Package Name</th>
                <th>Procedures</th>
                <th>Reg Price</th>
                <th>Discount (%)</th>
                <th>Discounted Price</th>
                <th>Date Created</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="packagesTable">
            <?php
            if (mysqli_num_rows($res) === 0) {
                echo "<tr><td colspan='6' style='text-align:center; color:#888;'>No packages found.</td></tr>";
            } else {
                // For each package, count the procedures
                while ($row = mysqli_fetch_assoc($res)) {
                    $pid = (int)$row['package_id'];
                    $procCount = 0;
                    $sub = mysqli_query($con, "SELECT COUNT(*) AS n FROM clinic_packages_procedures WHERE package_id=$pid");
                    if ($r = mysqli_fetch_assoc($sub)) $procCount = (int)$r['n'];

                    // Prepare button HTML (no echo inside echo, just concat!)
                    $package_id        = htmlspecialchars($row['package_id']);
                    $package_name      = htmlspecialchars($row['package_name'], ENT_QUOTES);
                    $reg_price         = htmlspecialchars($row['reg_price']);
                    $discount_value    = htmlspecialchars($row['discount_value']);
                    $discount_price    = htmlspecialchars($row['discount_price']);
                    
                    echo "<tr>
                        <td>".htmlspecialchars($row['package_name'])."</td>
                        <td>$procCount</td>
                        <td>".number_format($row['reg_price'],2)."</td>
                        <td>".number_format($row['discount_value'],2)."</td>
                        <td>".number_format($row['discount_price'],2)."</td>
                        <td>".date("M d, Y", strtotime($row['date_created']))."</td>
                       <td>
                            <button
                                type=\"button\"
                                class=\"btn btn-outline btn-sm view-package-btn\"
                                data-id=\"{$package_id}\"
                                data-name=\"{$package_name}\"
                                data-regprice=\"{$reg_price}\"
                                data-discountvalue=\"{$discount_value}\"
                                data-discountprice=\"{$discount_price}\"
                            >
                                <i class=\"fas fa-eye\"></i> Edit
                            </button>
                             <button
                                type=\"button\"
                                class=\"btn btn-outline btn-sm delete-package-btn\"
                                data-id=\"{$package_id}\"
                            >
                                <i class=\"fas fa-trash\"></i> Delete
                            </button>
                        </td>
                    </tr>";
                }
            }
            ?>
        </tbody>
    </table>
</div>

<!-- ADD PACKAGE MODAL -->
<div id="addPackageModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3>Add Package</h3>
        <form id="addPackageForm">
            <div class="form-group">
                <label>Package Name</label>
                <input type="text" id="add_package_name" name="package_name" required>
            </div>
            <div class="form-group">
                <label>Reg Price (auto-calc)</label>
                <input type="text" id="add_reg_price" name="reg_price" readonly style="background:#eee;">
            </div>
            <div class="form-group">
                <label>Discount (%)</label>
                <input type="number" step="0.01" id="add_discount_value" name="discount_value" min="0" max="100" value="0">
            </div>
            <div class="form-group">
                <label>Discounted Price (auto-calc)</label>
                <input type="text" id="add_discount_price" name="discount_price" readonly style="background:#eee;">
            </div>
            <!-- <div class="form-group">
                <label>Procedures</label>
                <div id="add_proc_group" class="procedure-group"></div>
            </div> -->
            <div class="form-group">
                <label>Procedures</label>
                <div id="add_proc_group" class="procedure-group">
                    <?php foreach ($procedures_by_service as $cat => $procs): ?>
                        <div class="procedure-category">
                            <strong><?= htmlspecialchars($cat) ?></strong>
                            <div>
                                <?php foreach ($procs as $proc): ?>
                                    <label>
                                        <input type="checkbox" name="procedures[]" value="<?= $proc['procedure_id'] ?>" data-price="<?= $proc['procedure_price'] ?: 0 ?>">
                                        <?= htmlspecialchars($proc['procedure_name']) ?>
                                        <span class="price-badge">₱<?= number_format($proc['procedure_price'] ?: 0, 2) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('addPackageModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Package</button>
            </div>
        </form>
    </div>
</div>

<!-- edit package modal -->

<div id="editPackageModal" class="modal" style="display:none;">
  <div class="modal-content">
    <h3>Edit Package</h3>
    <form id="editPackageForm">
      <input type="hidden" name="package_id" id="edit_package_id">
      <div class="form-group">
        <label>Package Name</label>
        <input type="text" id="edit_package_name" name="package_name" required>
      </div>
      <div class="form-group">
        <label>Reg Price</label>
        <input type="text" id="edit_reg_price" name="reg_price" readonly style="background:#eee;">
      </div>
      <div class="form-group">
        <label>Discount (%)</label>
        <input type="number" step="0.01" id="edit_discount_value" name="discount_value" min="0" max="100" value="0">
      </div>
      <div class="form-group">
        <label>Discounted Price</label>
        <input type="text" id="edit_discount_price" name="discount_price" readonly style="background:#eee;">
      </div>
      <!-- <div class="form-group">
        <label>Procedures</label>
        <div id="edit_proc_group" class="procedure-group"></div>
      </div> -->
      <div class="form-group">
        <label>Procedures</label>
        <div id="edit_proc_group" class="procedure-group">
            <?php foreach ($procedures_by_service as $cat => $procs): ?>
                <div class="procedure-category">
                    <strong><?= htmlspecialchars($cat) ?></strong>
                    <div>
                        <?php foreach ($procs as $proc): ?>
                            <label>
                                <input type="checkbox" name="procedures[]" value="<?= $proc['procedure_id'] ?>" data-price="<?= $proc['procedure_price'] ?: 0 ?>">
                                <?= htmlspecialchars($proc['procedure_name']) ?>
                                <span class="price-badge">₱<?= number_format($proc['procedure_price'] ?: 0, 2) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('editPackageModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
      </div>
    </form>
  </div>
</div>
