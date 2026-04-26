<?php
include '../../includes/connection.php';
include '../../includes/session_check.php';
requireRole(['Administrator']);

if ($_SESSION['role'] === 'Administrator') {

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['ajax_add_discount']) || isset($_POST['ajax_edit_discount']) || isset($_POST['ajax_delete_discount']) || isset($_POST['add_discount']) || isset($_POST['edit_discount']) || isset($_POST['delete_discount']))) {
        header('Content-Type: application/json; charset=utf-8');

        function json_resp($status, $message, $extra = []) {
            echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
            exit;
        }

        // Protect: ensure admin
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
            json_resp('error', 'Unauthorized');
        }

        // ---------- Add Discount ----------
if (isset($_POST['ajax_add_discount']) || isset($_POST['add_discount'])) {
    $name = trim($_POST['discount_name'] ?? '');
    $value = trim($_POST['discount_value'] ?? '');

    if ($name === '' || $value === '') json_resp('error', 'Please fill in all fields.');

    if (!is_numeric($value) || $value < 0) json_resp('error', 'Discount value must be a non-negative number.');

    // duplicate check (by name)
    $stmt_check = mysqli_prepare($con, "SELECT discount_id FROM discount_tbl WHERE discount_name = ? AND is_archived = 0");
    mysqli_stmt_bind_param($stmt_check, 's', $name);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    if (mysqli_stmt_num_rows($stmt_check) > 0) {
        mysqli_stmt_close($stmt_check);
        json_resp('error', 'A discount with this name already exists.');
    }
    mysqli_stmt_close($stmt_check);

    mysqli_begin_transaction($con);
    try {
        $v = (float)$value; // ✅ YOU FORGOT THIS

        $stmt = mysqli_prepare($con, "INSERT INTO discount_tbl (discount_name, discount_value) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, 'sd', $name, $v);
        mysqli_stmt_execute($stmt);
        $newId = mysqli_insert_id($con);
        mysqli_stmt_close($stmt);

        // ✅ Fetch the date_created
        $stmt_date = mysqli_prepare($con, "SELECT date_created FROM discount_tbl WHERE discount_id = ?");
        mysqli_stmt_bind_param($stmt_date, 'i', $newId);
        mysqli_stmt_execute($stmt_date);
        mysqli_stmt_bind_result($stmt_date, $dateCreated);
        mysqli_stmt_fetch($stmt_date);
        mysqli_stmt_close($stmt_date);

        mysqli_commit($con); // ✅ Don't forget to commit before response

        json_resp('success', 'Discount added successfully.', [
            'discount_id' => $newId,
            'discount_name' => $name,
            'discount_value' => $v,
            'date_created' => date("F d, Y", strtotime($dateCreated))
        ]);
        exit;
    } catch (Exception $e) {
        mysqli_rollback($con);
        json_resp('error', 'Database error while adding discount.');
    }
}


        // ---------- Edit Discount ----------
        if (isset($_POST['ajax_edit_discount']) || isset($_POST['edit_discount'])) {
            $id = intval($_POST['discount_id'] ?? 0);
            $name = trim($_POST['discount_name'] ?? '');
            $value = trim($_POST['discount_value'] ?? '');

            if ($id <= 0 || $name === '' || $value === '') json_resp('error', 'Invalid input.');

            if (!is_numeric($value) || $value < 0) json_resp('error', 'Discount value must be a non-negative number.');

            // optional duplicate-name check for other rows
            $stmt_check = mysqli_prepare($con, "SELECT discount_id FROM discount_tbl WHERE discount_name = ? AND discount_id != ? AND is_archived = 0");
            mysqli_stmt_bind_param($stmt_check, 'si', $name, $id);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                mysqli_stmt_close($stmt_check);
                json_resp('error', 'Another discount with this name already exists.');
            }
            mysqli_stmt_close($stmt_check);

            mysqli_begin_transaction($con);
            try {
                $stmt = mysqli_prepare($con, "UPDATE discount_tbl SET discount_name = ?, discount_value = ? WHERE discount_id = ? AND is_archived = 0");
                $v = (float)$value;
                mysqli_stmt_bind_param($stmt, 'sdi', $name, $v, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                mysqli_commit($con);

                json_resp('success', 'Discount updated successfully.', [
                    'discount_id' => $id,
                    'discount_name' => $name,
                    'discount_value' => $v
                ]);
            } catch (Exception $e) {
                mysqli_rollback($con);
                json_resp('error', 'Database error while updating discount.');
            }
        }


        // ---------- Soft Delete Discount (Archive) ----------
        if (isset($_POST['ajax_delete_discount']) || isset($_POST['delete_discount'])) {
            $id = intval($_POST['discount_id'] ?? 0);
            if ($id <= 0) json_resp('error', 'Invalid discount id.');

            mysqli_begin_transaction($con);
            try {
                // Soft delete (archive)
                $stmt = mysqli_prepare($con, "UPDATE discount_tbl SET is_archived = 1 WHERE discount_id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                mysqli_commit($con);

                json_resp('success', 'Discount archived.');
            } catch (Exception $e) {
                mysqli_rollback($con);
                json_resp('error', 'Database error while archiving discount.');
            }
        }


        // Unknown POST action
        json_resp('error', 'Unknown action.');
    } // end AJAX handlers

    // ---------- Page Markup ----------
    ?>

    <div class="page-title">
        <div class="title">Discounts</div>
        <div class="action-buttons">
            <button id="addDiscountBtn" class="btn btn-primary" type="button">
                <i class="fas fa-plus"></i> Add Discount
            </button>
        </div>
    </div>

    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Discount Value (%)</th>
                    <th>Date Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="discountsTable">
                <?php
                // $query = "SELECT discount_id, discount_name, discount_value FROM discount_tbl ORDER BY discount_name ASC";
                $query = "SELECT * FROM discount_tbl WHERE is_archived = 0 ORDER BY discount_id DESC";
                $result = mysqli_query($con, $query);

                if(mysqli_num_rows($result) === 0) {
                    echo "<tr><td colspan='3' style='text-align:center; padding: 12px; color:#888;'>No discounts found.</td></tr>";
                }

                while ($row = mysqli_fetch_assoc($result)) {
                    $id = (int)$row['discount_id'];
                    $name = htmlspecialchars($row['discount_name']);
                    $dateCreated = date("F d, Y", strtotime($row['date_created']));

                    // format value to remove trailing zeros if whole
                    $value = rtrim(rtrim(number_format((float)$row['discount_value'], 2, '.', ''), '0'), '.');
                    echo "<tr data-id=\"{$id}\">";
                    echo "<td>{$name}</td>";
                    echo "<td>{$value}%</td>";
                    echo "<td>{$dateCreated}</td>";
                    echo '<td>
                            <button type="button" class="btn btn-outline btn-sm view-discount-btn" 
                                data-id="' . htmlspecialchars($id) . '" 
                                data-name="' . htmlspecialchars($row['discount_name'], ENT_QUOTES) . '" 
                                data-value="' . htmlspecialchars($row['discount_value'], ENT_QUOTES) . '">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button type="button" class="btn btn-outline btn-sm delete-discount-btn" data-id="' . htmlspecialchars($id) . '">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                          </td>';
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Add Discount Modal -->
    <div id="addDiscountModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3>Add Discount</h3>
            <form id="addDiscountForm" method="POST">
                <div class="form-group">
                    <label>Discount Name</label>
                    <input type="text" id="add_discount_name" name="discount_name" required maxlength="100">
                </div>
                <div class="form-group">
                    <label>Discount Value (%)</label>
                    <input type="number" step="0.01" id="add_discount_value" name="discount_value" required min="1" max="100" step="0.01">
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-outline" onclick="closeAddDiscountModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Discount</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit/View Discount Modal -->
    <div id="editDiscountModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3>Edit Discount</h3>
            <form id="editDiscountForm" method="POST">
                <input type="hidden" id="edit_discount_id" name="discount_id">
                <div class="form-group">
                    <label>Discount Name</label>
                    <input type="text" id="edit_discount_name" name="discount_name" required maxlength="100">
                </div>
                <div class="form-group">
                    <label>Discount Value (%)</label>
                    <input type="number" step="0.01" id="edit_discount_value" name="discount_value" required min="1" max="100" step="0.01">
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-outline" onclick="closeEditDiscountModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- <script src="js/main.js"></script> -->

    <?php
} // end role check
?>
