<?php
include '../../includes/connection.php';
include '../../includes/session_check.php';
requireRole(['Administrator', 'Receptionist', 'Laboratory Personnel', 'Ultrasound Personnel', '2D Echo Personnel', 'ECG Personnel', 'X-RAY Personnel']);

$userRole = $_SESSION['role_name'] ?? '';
$sessionUserId = $_SESSION['user_info_id'] ?? null; // Must always be set at login!

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use POST user_info_id, otherwise fallback to logged-in user's own profile (sidebar "My Profile")
    $userInfoId = isset($_POST['user_info_id']) && $_POST['user_info_id'] !== ''
        ? intval($_POST['user_info_id'])
        : $sessionUserId;
    if (!$userInfoId) {
        echo "<h2>No user selected and no user in session. Please log in again.</h2>";
        exit;
    }
    $isSelf  = ($sessionUserId == $userInfoId);
    $isAdmin = ($userRole === 'Administrator');
    if (!$isAdmin && !$isSelf) {
        echo "<h2>You are not authorized to view or edit this user.</h2>";
        exit;
    }
} else {
    // Only POST is allowed! Otherwise, show error
    echo "<h2 style='text-align:center; margin-top: 100px;'>No user selected. Please use the application menu to navigate.</h2>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_account'])) {
    $userInfoId = intval($_POST['user_info_id']);
    $user_fname = trim(mysqli_real_escape_string($con, $_POST['user_fname']));
    $user_mname = trim(mysqli_real_escape_string($con, $_POST['user_mname']));
    $user_lname = trim(mysqli_real_escape_string($con, $_POST['user_lname']));
    $user_sex = trim(mysqli_real_escape_string($con, $_POST['user_sex']));
    $user_dob = trim(mysqli_real_escape_string($con, $_POST['user_dob']));
    $user_phone = trim(mysqli_real_escape_string($con, $_POST['user_phone']));
    $user_home_add = trim(mysqli_real_escape_string($con, $_POST['user_home_add']));

    // validate names server side
    $namePattern = "/^[a-zA-Z\s\.]+$/";
    if (!preg_match($namePattern, $user_fname)) {
        echo 'Invalid First Name'; exit;
    }
    if (!preg_match($namePattern, $user_mname)) {
        echo 'Invalid Middle Name'; exit;
    }
    if (!preg_match($namePattern, $user_lname)) {
        echo 'Invalid Last Name'; exit;
    }

    // Validate sex
    if ($user_sex !== 'Male' && $user_sex !== 'Female') {
        echo 'Invalid Sex value'; exit;
    }

    // Validate phone number (must be 11 digits)
    if (!preg_match('/^(?:\d\s*){11}$/', $user_phone)) {
        echo 'Phone number must be 11 digits only.'; exit;
    }

    // Validate date of birth format
    function isAtLeast18($dob) {
        $dobObj = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$dobObj) return false;
        $todayObj = new DateTime();
        $age = $todayObj->diff($dobObj)->y;
        return $age >= 18;
    }

    // Usage in validation:
    if (!DateTime::createFromFormat('Y-m-d', $user_dob)) {
        echo 'Invalid Date of Birth format'; exit;
    }
    if (!isAtLeast18($user_dob)) {
        echo 'User must be at least 18 years old.'; exit;
    }

    $query = "UPDATE user_info SET
        user_fname = ?,
        user_mname = ?,
        user_lname = ?,
        user_sex = ?,
        user_dob = ?,
        user_phone = ?,
        user_home_add = ?
        WHERE user_info_id = ?";

    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "sssssssi",
        $user_fname, $user_mname, $user_lname, $user_sex,
        $user_dob, $user_phone, $user_home_add, $userInfoId);

    if (mysqli_stmt_execute($stmt)) {
        echo "success"; exit;
    } else {
        echo "Error updating record: " . mysqli_error($con); exit;
    }
}

    // if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_info_id'])){
    //     $userInfoId = intval($_POST['user_info_id']);

    //     $query = "SELECT ui.user_fname, ui.user_mname, ui.user_lname , 
    //         ui.user_created, ui.user_phone, r.role_name, ui.user_sex, ui.user_dob, ui.user_home_add
    //         FROM user_info ui
    //         JOIN user_account ua ON ui.user_info_id = ua.user_info_id
    //         JOIN roles r ON ua.role_id = r.role_id
    //         WHERE ui.user_info_id = ?";

    //         $stmt = mysqli_prepare($con, $query);
    //         mysqli_stmt_bind_param($stmt, 'i', $userInfoId);
    //         mysqli_stmt_execute($stmt);
    //         $result = mysqli_stmt_get_result($stmt);

    //         if($row = mysqli_fetch_assoc($result)) {
    //             //later
    //         } else {
    //             echo "<h1>Account not found.</h1>";
    //             exit;
    //         }
    //         mysqli_stmt_close($stmt);
    // } else {
    //     echo "<h2 style='text-align:center; margin-top: 100px;'>No user selected. Please go back to the <a href='../st_albert.php'>dashboard</a>.</h2>";
    //     exit;
    // }

    // At this point, $userInfoId is ALWAYS SET from the top logic (POST user_info_id or fallback)
    $query = "SELECT ui.user_fname, ui.user_mname, ui.user_lname , 
        ui.user_created, ui.user_phone, r.role_name, ui.user_sex, ui.user_dob, ui.user_home_add
        FROM user_info ui
        JOIN user_account ua ON ui.user_info_id = ua.user_info_id
        JOIN roles r ON ua.role_id = r.role_id
        WHERE ui.user_info_id = ?";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, 'i', $userInfoId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        // continue with rendering
    } else {
        echo "<h1>Account not found.</h1>";
        exit;
    }
    mysqli_stmt_close($stmt);


    // Restore Account Handler
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_account'])) {
        $userInfoIdToRestore = intval($_POST['user_info_id']);

        $query = "UPDATE user_info SET is_archived = 0 WHERE user_info_id = ?";
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, "i", $userInfoIdToRestore);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success', 'message' => 'Account restored successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error restoring account: ' . mysqli_error($con)]);
        }
        exit;
    }

    //Delete/ARCHIVE/Soft delete
    //user is hidden based on the user info id on the user info table with changing the is_archived
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_account'])) {
    $userInfoIdToArchive = intval($_POST['user_info_id']);

    $query = "UPDATE user_info SET is_archived = 1 WHERE user_info_id = ?";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "i", $userInfoIdToArchive);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['status' => 'success', 'message' => 'Account archived successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error archiving account: ' . mysqli_error($con)]);
    }
    exit; // important to stop further output
}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_account_credentials']) && $isSelf) {
    $currentPassword = trim($_POST['currentPassword'] ?? '');
    $newUsername = trim($_POST['newUsername'] ?? '');
    $newPassword = trim($_POST['newPassword'] ?? '');
    $confirmNewPassword = trim($_POST['confirmNewPassword'] ?? '');

    // 1. Check current password (required)
    if (empty($currentPassword)) {
        echo json_encode(['status'=>'error','message'=>'Current password is required.']); exit;
    }
    $stmt = mysqli_prepare($con, "SELECT ua.user_password_id, up.user_password, ua.username FROM user_account ua JOIN user_passwords up ON ua.user_password_id = up.user_password_id WHERE ua.user_info_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $userInfoId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $passwordId, $currentPasswordHash, $currentUsername);
    if (!mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        echo json_encode(['status'=>'error','message'=>'Incorrect Password (not found).']); exit;
    }
    mysqli_stmt_close($stmt);
    if (!password_verify($currentPassword, $currentPasswordHash)) {
        echo json_encode(['status'=>'error','message'=>'Incorrect Password.']); exit;
    }

    // === Begin validation-only section ===
    $errors = [];

    // Username: If changing, check validity and uniqueness
    // $updateUsername = false;
    // if ($newUsername && $newUsername !== $currentUsername) {
    //     if (strlen($newUsername) < 8 || strlen($newUsername) > 20) {
    //         $errors[] = 'New username must be between 8 and 20 characters.';
    //     } else {
    //         $stmt = mysqli_prepare($con, "SELECT COUNT(*) FROM user_account WHERE username = ? AND user_info_id != ?");
    //         mysqli_stmt_bind_param($stmt, 'si', $newUsername, $userInfoId);
    //         mysqli_stmt_execute($stmt);
    //         mysqli_stmt_bind_result($stmt, $exists);
    //         mysqli_stmt_fetch($stmt);
    //         mysqli_stmt_close($stmt);
    //         if ($exists) {
    //             $errors[] = 'Username already exists.';
    //         } else {
    //             $updateUsername = true;
    //         }
    //     }
    // }

    // Password: If either newPassword or confirmNewPassword given, validate both
    $updatePassword = false;
    if ($newPassword || $confirmNewPassword) {
        if (empty($newPassword) || empty($confirmNewPassword)) {
            $errors[] = 'Both new password and confirmation are required.';
        } elseif ($newPassword !== $confirmNewPassword) {
            $errors[] = 'New password and confirmation do not match.';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } else {
            $updatePassword = true;
        }
    }

    if (!empty($errors)) {
        echo json_encode(['status'=>'error','message'=>implode(" ", $errors)]); exit;
    }

    // === Perform updates only after validation passes ===
    mysqli_begin_transaction($con); // optional, but safer

    try {
        // if ($updateUsername) {
        //     $stmt = mysqli_prepare($con, "UPDATE user_account SET username=? WHERE user_info_id=?");
        //     mysqli_stmt_bind_param($stmt, 'si', $newUsername, $userInfoId);
        //     mysqli_stmt_execute($stmt);
        //     mysqli_stmt_close($stmt);
        // }

        if ($updatePassword) {
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($con, "UPDATE user_passwords SET user_password=? WHERE user_password_id=?");
            mysqli_stmt_bind_param($stmt, 'si', $newPasswordHash, $passwordId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        mysqli_commit($con);
        echo json_encode(['status'=>'success','message'=>'Account Updated!']); exit;
    } catch (Exception $e) {
        mysqli_rollback($con);
        echo json_encode(['status'=>'error','message'=>'Transaction failed: '.$e->getMessage()]);
        exit;
    }
}

// ---- DISPLAY ACCOUNT INFO ----
$query = "SELECT ui.user_fname, ui.user_mname, ui.user_lname,
    ui.user_created, ui.user_phone, r.role_name, ui.user_sex, ui.user_dob, ui.user_home_add
    FROM user_info ui
    JOIN user_account ua ON ui.user_info_id = ua.user_info_id
    JOIN roles r ON ua.role_id = r.role_id
    WHERE ui.user_info_id = ?";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, 'i', $userInfoId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    // ---- SHOW THE ACCOUNT INFO HTML (rest of your view_account view here) ----
    // (Omitted for brevity; your normal HTML with PHP echo/embedding)
} else {
    echo "<h1>Account not found.</h1>";
    exit;
}
mysqli_stmt_close($stmt);

?>
        <div class="page-title d-flex justify-content-between align-items-center">
            <div class="title">View Account</div>
        </div>

        <!-- Personal Info Box -->
        <div class="info-card">
        <div class="card-header">
            <h3><i class="fas fa-user"></i> Personal Information</h3>
            <?php if ($isSelf): ?>
                <button class="btn btn-outline btn-sm" id="editBtn">
                    <i class="fas fa-pen"></i> Edit
                </button>
            <?php endif; ?>
        </div>
        <div class="info-grid">
            <div class="info-item"><label>First Name</label><span> <?= htmlspecialchars($row['user_fname']) ?> </span></div>
            <div class="info-item"><label>Middle Name</label><span><?= htmlspecialchars($row['user_mname']) ?></span></div>
            <div class="info-item"><label>Last Name</label><span><?= htmlspecialchars($row['user_lname']) ?></span></div>
            <div class="info-item"><label>Sex</label><span><?= htmlspecialchars($row['user_sex']) ?></span></div>
            <div class="info-item"><label>Date of Birth</label><span><?= date("F d, Y", strtotime($row['user_dob'])) ?></span></div>
            <div class="info-item"><label>Phone Number</label><span><?= htmlspecialchars($row['user_phone']) ?></span></div>
            <div class="info-item"><label>Home Address</label><span><?= htmlspecialchars($row['user_home_add']) ?></span></div>
        </div>
        </div>

        <div class="info-card">
        <div class="card-header">
            <h3><i class="fas fa-user-shield"></i> Account Information</h3>
            <?php if ($isSelf): ?>
  <!-- Only display if viewing own profile -->
  <button id="editAccountBtn" class="btn btn-outline btn-sm" style="margin-top:10px;">
    <i class="fas fa-user-gear"></i> Edit Account
  </button>

  <div id="editAccountModal" class="modal">
    <div class="modal-content">
      <h3>Change Password</h3>
      <form id="editAccountForm" autocomplete="off">
        <div class="form-group">
          <label for="currentPassword">Current Password <span style="color:red">*</span></label>
          <input type="password" name="currentPassword" id="currentPassword" required minlength="8" autocomplete="off">
        </div>
        <!-- <div class="form-group">
          <label for="newUsername">New Username</label>
          <input type="text" name="newUsername" id="newUsername" minlength="8" required maxlength="20" autocomplete="off">
        </div> -->
        <div class="form-group">
          <label for="newPassword">New Password</label>
          <input type="password" name="newPassword" id="newPassword" minlength="8" required autocomplete="off">
        </div>
        <div class="form-group">
          <label for="confirmNewPassword">Confirm New Password</label>
          <input type="password" name="confirmNewPassword" id="confirmNewPassword" required minlength="8" autocomplete="off">
        </div>
        <div class="modal-buttons">
          <button type="button" class="btn btn-outline" id="closeEditAccountModalBtn">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

        </div>
        <div class="info-grid">
            <div class="info-item"><label>Role</label><span><?= htmlspecialchars($row['role_name']) ?></span></div>
            <div class="info-item">
                <label>Status</label>
                <span>
                    <?php
                    // Fetch is_archived status
                    $statusQuery = "SELECT is_archived FROM user_info WHERE user_info_id = ?";
                    $stmtStatus = mysqli_prepare($con, $statusQuery);
                    mysqli_stmt_bind_param($stmtStatus, 'i', $userInfoId);
                    mysqli_stmt_execute($stmtStatus);
                    $statusResult = mysqli_stmt_get_result($stmtStatus);
                    $statusRow = mysqli_fetch_assoc($statusResult);
                    $isArchived = $statusRow['is_archived'] == 1;
                    mysqli_stmt_close($stmtStatus);
                    
                    if ($isArchived) {
                        echo '<span class="status status--error">Inactive</span>';
                    } else {
                        echo '<span class="status status--success">Active</span>';
                    }
                    ?>
                </span>
            </div>


            <div class="info-item"><label>Date Created</label><span><?= date("M d, Y H:i:s a", strtotime($row['user_created'])) ?></span></div>

            <?php if (!$isSelf):?>
                 <!-- for delete/archive purposes -->
                <form class = "archiveAccountForm">
                    <input type="hidden" name="user_info_id" value="<?php echo htmlspecialchars($userInfoId); ?>">
                    <?php if ($isArchived): ?>
                        <button type="submit" name="restore_account" class="btn btn-primary">
                            <i class="fas fa-undo"></i> Restore Account
                        </button>
                    <?php else: ?>
                        <button type="submit" name="archive_account" class="btn btn-outline">
                            <i class="fas fa-archive"></i> Archive Account
                        </button>
                    <?php endif; ?>
                </form>
            <?php endif;?>
            

        </div>
        
        <!-- Edit Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <h3>Edit Personal Information</h3>
                <form id="editForm" method="POST" action="view_account.php">
                <input type="hidden" id = "userId" name="user_info_id" value="<?= htmlspecialchars($userInfoId) ?>">
                <!-- added -->
                <input type="hidden" name="edit_account" value="1">

                <div class="form-group">
                    <label for="firstName">First Name</label>
                    <input type="text"
                            id="firstName"
                            name="user_fname"
                            value="<?= htmlspecialchars($row['user_fname']) ?>"
                            required
                            pattern="[A-Za-z\s\-\.]+"
                            maxlength="50"
                            title="First name can only contain letters, spaces, hyphens, and periods">
                </div>
                
                <div class="form-group">
                    <label for="middleName">Middle Name</label>
                    <input type="text"
                            id="middleName"
                            name="user_mname"
                            value="<?= htmlspecialchars($row['user_mname']) ?>"
                            pattern="[A-Za-z\s\-\.]*"
                            maxlength="50"
                            title="Middle name can only contain letters, spaces, hyphens, and periods">
                </div>
                <div class="form-group">
                    <label for="lastName">Last Name</label>
                    <input type="text"
                            id="lastName"
                            name="user_lname"
                            value="<?= htmlspecialchars($row['user_lname']) ?>"
                            required
                            pattern="[A-Za-z\s\-\.]+"
                            maxlength="50"
                            title="Last name can only contain letters, spaces, hyphens, and periods">
                </div>
                <div class="form-group">
                    <label for="sex">Sex</label>
                    <select id="sex" name="user_sex" required>
                    <option value="Male" <?= $row['user_sex'] === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $row['user_sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
                <?php
                    // Calculate the latest allowed birthdate for 18 years old
                    $minAgeDate = date('Y-m-d', strtotime('-18 years'));
                ?>
                <div class="form-group">
                    <label for="dob">Date of Birth</label>
                    <input type="date"
                        id="dob"
                        name="user_dob"
                        value="<?= htmlspecialchars($row['user_dob']) ?>"
                        required
                        max="<?= $minAgeDate ?>"
                        title="User must be at least 18 years old">
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" 
                            id="phone" 
                            name="user_phone" 
                            value="<?= htmlspecialchars($row['user_phone']) ?>" 
                            pattern="\d{11}" 
                            maxlength="11"
                            title="Phone number must be exactly 11 digits">
                </div>
                <div class="form-group">
                    <label for="address">Home Address</label>
                    <input type="text"
                            id="address"
                            name="user_home_add"
                            value="<?= htmlspecialchars($row['user_home_add']) ?>"
                            maxlength="255"
                            title="Address must not exceed 255 characters">
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn btn-outline" id="closeBtnEdit">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
                </form>
            </div>
            </div>

        </div>

        
<!-- <script src="js/view_account.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> -->