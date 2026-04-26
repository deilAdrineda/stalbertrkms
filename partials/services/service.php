<?php 
    include '../../includes/connection.php';
   include '../../includes/session_check.php';
    requireRole(['Administrator','Receptionist','Laboratory Personnel', 'Ultrasound Personnel', '2D Echo Personnel', 'ECG Personnel', 'X-RAY Personnel']);
?>
<?php
// this is part is handmade, with gpt of course. Bit by bit

// this condition is for the admin to have this kind of access

// ALSO DO TAKE NOTE THAT THIS IS FOR VIEWING THE SERVICES, NOT WHICH PATIENTS HAS 

if ($_SESSION['role'] == 'Administrator') { 

    // ---------- AJAX: Add service ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['ajax_add_service']) || isset($_POST['add_service']))) {
        header('Content-Type: application/json; charset=utf-8');

        // // require admin role
        // if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
        //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        //     exit;
        // }

        $service_code = trim($_POST['service_code'] ?? '');
        $service_name = trim($_POST['service_name'] ?? '');
        $role_id      = intval($_POST['role_id'] ?? 0); // Use 'role_id' (matches your table field)

        if ($service_code === '' || $service_name === '' || !$role_id) {
            echo json_encode(['status' => 'error', 'message' => 'Please provide code, name, and role.']);
            exit;
        }

        // duplicate check
        $stmt_check = mysqli_prepare($con, "SELECT service_id FROM clinic_service_tbl WHERE service_code = ?");
        mysqli_stmt_bind_param($stmt_check, 's', $service_code);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            mysqli_stmt_close($stmt_check);
            echo json_encode(['status' => 'error', 'message' => 'Service code already exists.']);
            exit;
        }
        mysqli_stmt_close($stmt_check);

        mysqli_begin_transaction($con);

        try {
            // Modified to also insert role_id!
            $stmt = mysqli_prepare($con, "INSERT INTO clinic_service_tbl (service_code, service_name, role_id, service_added) VALUES (?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt, 'ssi', $service_code, $service_name, $role_id);
            mysqli_stmt_execute($stmt);
            $newId = mysqli_insert_id($con);
            mysqli_stmt_close($stmt);

            mysqli_commit($con);

            echo json_encode([
                'status' => 'success',
                'message' => 'Service added successfully',
                'service_id' => $newId,
                'service_code' => $service_code,
                'service_name' => $service_name,
                'role_id' => $role_id
            ]);
            exit;
        } catch (Exception $e) {
            mysqli_rollback($con);
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
            exit;
        }
    }
?>

    <div class = "page-title">
        <div class = "title">
            Services
        </div>

        <!-- Action Button -->
         <!-- Action Button -->
        <div class="action-buttons">
            <button id="addServiceBtn" class="btn btn-primary" type="button">
                <i class="fas fa-plus"></i> Add Service
            </button>
        </div>

    </div>

    <!-- Add Service Modal -->
    <div id="addServiceModal" class="modal">
        <div class="modal-content">
            <h3>Add Service</h3>

            <form id="addServiceForm" method="POST">
                <div class="form-group">
                    <label>Service Code</label>
                    <input type="text" id = "service_code" name="service_code" required maxlength="20">
                </div>
                <div class="form-group">
                    <label>Service Name</label>
                    <input type="text" id = "service_name" name="service_name" required maxlength="100">
                </div>
                <div class="form-group">
                    <label>Personnel In Charge</label>
                    <select name="role_id" id="role_id" required>
                        <option value="" disabled selected>Select Role</option>
                        <?php
                         $roleIds = [2, 3, 5, 6, 7];
                         $roleQuery = mysqli_query($con, "SELECT role_id, role_name FROM roles WHERE role_id IN (2, 3, 5, 6, 7)");
                           $roleQuery = mysqli_query($con, "SELECT role_id, role_name FROM roles WHERE role_id IN (2,3,5,6,7)");
                            while ($role = mysqli_fetch_assoc($roleQuery)) {
                                echo "<option value=\"{$role['role_id']}\">{$role['role_name']}</option>";
                            }
                        ?>
                    </select>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn btn-outline" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Service</button>
                </div>
            </form>

        </div>
    </div>

    <!-- Table -->
     <div class = 'table-card'>
        <table class = 'data-table'>
            <thead>
                <tr>
                    <th>Service Code</th>
                    <!-- Create a way to show the Year, e.g L25, X25, etc -->
                    <th>Service Name</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody id = 'serviceTable'>
                <?php
                    $query = "SELECT * FROM clinic_service_tbl WHERE is_archived =0";
                    $result = mysqli_query($con, $query);

                    while ($srow = mysqli_fetch_assoc($result)) {
                        // format
                        $serviceName = htmlspecialchars($srow['service_name']);
                        $serviceCode = htmlspecialchars($srow['service_code']);

                        echo "<tr>";
                        echo "<td>$serviceCode</td>";
                        echo "<td>$serviceName</td>";
                        echo '
                        <td>
                             <button type="button" 
                                    class="btn btn-outline btn-sm view-service-btn" 
                                    data-id="' . htmlspecialchars($srow['service_id']) . '">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </td>';
                        echo "</tr>";
                    }
                ?>

            </tbody>

        </table>
     </div>

     <script src="js/main.js"></script>
<?php
} // MANAGING SERVICE ONLY FOR ADMINISTRATOR
?>

<?php
    if($_SESSION['role'] == 'Receptionist') {
?>

    <div class="page-title">
        <div class="title">
            Services
        </div>
    </div>

    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Service Code</th>
                    <th>Service Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="serviceTable">
                <?php
                    $query = "SELECT * FROM clinic_service_tbl";
                    $result = mysqli_query($con, $query);

                    while ($srow = mysqli_fetch_assoc($result)) {
                        $serviceName = htmlspecialchars($srow['service_name']);
                        $serviceCode = htmlspecialchars($srow['service_code']);

                        echo "<tr>";
                        echo "<td>$serviceCode</td>";
                        echo "<td>$serviceName</td>";
                        echo '
                        <td>
                            <button type="button"
                                    class="btn btn-outline btn-sm view-service-btn"
                                    data-id="' . htmlspecialchars($srow['service_id']) . '">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </td>';
                        echo "</tr>";
                    }
                ?>
            </tbody>
        </table>
    </div>
    <script src="js/main.js"></script>
<?php
}
?>
