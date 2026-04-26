<?php
  header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
  header("Cache-Control: post-check=0, pre-check=0", false);
  header("Pragma: no-cache");
  include '../../includes/session_check.php';
  requireRole('Administrator');

  // fetch the roles from the database for role options
  $role_query = mysqli_query($con, "SELECT role_id, role_name FROM roles WHERE role_name != 'Administrator' ORDER BY role_name ASC");
  $roles = [];
  while ($row = mysqli_fetch_assoc($role_query)) {
      $roles[] = $row;
  }
    function sendResponse($status, $message) {
      header('Content-Type: application/json');
      echo json_encode(['status' => $status, 'message' => $message]);
      exit;
  }

if($_SERVER['REQUEST_METHOD']==='POST'){
    // if(isset($_POST['register'])) {
        // $firstname = $_POST['firstname'];
        // $middlename = $_POST['middlename'];
        // $lastname = $_POST['lastname'];
        // $sex = $_POST['sex'];
        // $address = $_POST['address'];
        // $dob = $_POST['dob'];
        // $role = $_POST['role_name'];
        // $username = $_POST['username'];
        // $password = $_POST['password'];

        $firstname = trim(mysqli_real_escape_string($con, $_POST['firstname']));
        $middlename = trim(mysqli_real_escape_string($con, $_POST['middlename']));
        $lastname = trim(mysqli_real_escape_string($con, $_POST['lastname']));
        $sex = trim(mysqli_real_escape_string($con, $_POST['sex']));
        $address = trim(mysqli_real_escape_string($con, $_POST['address']));
        $dob = trim(mysqli_real_escape_string($con, $_POST['dob']));
        $phone = trim(mysqli_real_escape_string($con, $_POST['phone']));
        $role = trim(mysqli_real_escape_string($con, $_POST['role']));
        $username = trim(mysqli_real_escape_string($con, $_POST['username']));
        $password = trim(mysqli_real_escape_string($con, $_POST['password']));

        //validate names
        $namePattern = "/^[a-zA-Z\s\.]+$/";
        //validate phone number
        //$phoneNum = preg_replace('/\D/', '', $phone);

        if (empty($firstname) || !preg_match($namePattern, $firstname)) {
            sendResponse('warning', 'Invalid First Name');
            return;
        }
        if ($middlename !== '' && !preg_match($namePattern, $middlename)) {
            sendResponse('warning', 'Invalid Middle Name');
            return;
        }
        if (empty($lastname) || !preg_match($namePattern, $lastname)) {
            sendResponse('warning', 'Invalid Last Name');
            return;
        }

        
        if ($sex !== 'Male' && $sex !== 'Female') {
            sendResponse('warning', 'Invalid Value');
            return;
        }

        // if(empty($phone) || !preg_match('/^\d{11}$/', $phone)) {
        //     alert('Phone number must be 11 digits only.');
        //     return;
        // }

        if (empty($phone) || !preg_match('/^(?:\d\s*){11}$/', $phone)) {
            sendResponse('warning', 'Phone number must be 11 digits only');
            return;
        }        

        function isAtLeast18($dob) {
            $dobObj = DateTime::createFromFormat('Y-m-d', $dob);
            if (!$dobObj) return false;
            $todayObj = new DateTime();
            $age = $todayObj->diff($dobObj)->y;
            return $age >= 18;
        }

       // 1. Check format first
        if (!DateTime::createFromFormat('Y-m-d', $dob)) {
            sendResponse('warning', 'Invalid Date Format'); 
            exit;
        }

        $dobObj = DateTime::createFromFormat('Y-m-d', $dob);
        $minDate = new DateTime('1900-01-01');
        $maxDate = new DateTime(); // today

        // 2. Check if future date
        if ($dobObj > $maxDate) {
            sendResponse('warning', 'Birth Date cannot be in the future.');
            exit;
        }

        // 3. Check if too old
        if ($dobObj < $minDate) {
            sendResponse('warning', 'Birth Date must not be that old');
            exit;
        }

        // 4. Finally check age (18+)
        if (!isAtLeast18($dob)) {
            sendResponse('warning', 'User must be at least 18 years old!'); 
            exit;
        }

 
        //username
        if (strlen($username) < 8) {
            sendResponse('warning', 'Username is too short');
            return;
        } elseif (strlen($username) > 20) {
            sendResponse('warning', 'Username is too long');
            return;
        } else {
            $stmt_check_user = mysqli_prepare($con, "SELECT username FROM user_account WHERE username = ?");
            mysqli_stmt_bind_param($stmt_check_user, 's', $username);
            mysqli_stmt_execute($stmt_check_user);
            mysqli_stmt_store_result($stmt_check_user);

            if (mysqli_stmt_num_rows($stmt_check_user) > 0) {
                sendResponse('warning', 'Username already exists');
                mysqli_stmt_close($stmt_check_user);
                return;
            }
            mysqli_stmt_close($stmt_check_user);
        }

        //password
        if (strlen($password) < 8) {
            sendResponse('warning', 'Password is too short');
            return;
        }
        if ($password !== $_POST['confirm_password']) { sendResponse('warning', 'Passwords do not match'); return; }
        
    
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        mysqli_begin_transaction($con);

        try {
            //prepare, bind ,execute, close
            //user created date
            $user_created = date('Y-m-d H:i:s');
            //user
            $stmt_insert_user_info = mysqli_prepare($con, "INSERT INTO user_info (user_fname, user_mname, user_lname, user_sex, user_home_add, user_dob, user_phone, user_created) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_insert_user_info, "ssssssss", $firstname, $middlename, $lastname, $sex, $address, $dob, $phone, $user_created);
            mysqli_stmt_execute($stmt_insert_user_info);
            $user_id = mysqli_insert_id($con);
            mysqli_stmt_close($stmt_insert_user_info);

            //password
            $stmt_insert_user_password = mysqli_prepare($con, "INSERT INTO user_passwords (user_password) VALUES (?)");
            mysqli_stmt_bind_param($stmt_insert_user_password, "s", $hashed);
            mysqli_stmt_execute($stmt_insert_user_password);
            $user_password_id = mysqli_insert_id($con);
            mysqli_stmt_close($stmt_insert_user_password);

            //account
            $stmt_insert_user_account = mysqli_prepare($con, "INSERT INTO user_account (username, role_id, user_password_id, user_info_id) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_insert_user_account, "siii", $username, $role, $user_password_id, $user_id);
            mysqli_stmt_execute($stmt_insert_user_account);
            mysqli_stmt_close($stmt_insert_user_account);

            mysqli_commit($con);
            sendResponse('success', 'Successfully Registered');
            // alert('Successfully Registered', 'accounts.php');
            // from gpt
            // $_SESSION['account_added'] = true;
            // header("Location: add_account.php");
            // exit;
        } catch(Exception $e) {
            mysqli_rollback($con);
            sendResponse('error', 'FAILED SAVING THE ACCOUNT');
        }
    }


?>


    <link rel="stylesheet" href="../css/add_account.css">

   <div class="page-title">
                  <div class="title">Add Account</div>
                </div>
                <div class="add-account-card">
                <form class="add-account-form" method="POST" id="addAccountForm">
                    <!-- Personal Info -->
                <div class="form-section">
                  <div class="form-section-title">
                    <i class="fa-solid fa-id-card"></i>Personal Information
                  </div>

                  <div class="form-row">
                    <div class="form-group">
                      <label>First Name</label>
                      <input type="text" name="firstname" placeholder="e.g. John" required>
                    </div>
                    <div class="form-group">
                      <label>Middle Name</label>
                      <input type="text" name="middlename" placeholder="e.g. Matthew" required>
                    </div>
                    <div class="form-group">
                      <label>Last Name</label>
                      <input type="text" name="lastname" placeholder="e.g. Reyes" required>
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label>Sex</label>
                      <select name="sex" required>
                        <option value="" disabled selected>Sex</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label>Birthday</label>
                      <input type="date" name="dob" required>
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group" style="flex:2;">
                      <label>Address</label>
                      <input type="text" name="address" placeholder="789 Rizal Avenue, Talavera, Nueva Ecija" required>
                    </div>
                    <div class="form-group">
                      <label>Contact Number</label>
                      <input type="text" name="phone" placeholder="09123456789" required>
                    </div>
                  </div>
                </div>

                <!-- Account Info -->
                <div class="form-section">
                  <div class="form-section-title"><i class="fa-solid fa-user-gear"></i> Account Information</div>

                  <div class="form-row">
                    <div class="form-group">
                      <label for="username">Username</label>
                      <input type="text" name="username" id="username" placeholder="Username" required minlength="8" maxlength="20">
                    </div>

                    <div class="form-group password-wrapper">
                        <label for="password">Password</label>
                        <div class="password-field">
                          <input type="password" name="password" id="password" placeholder="Password" required minlength="8">
                          <i class="fa-solid fa-lock toggle-password" onclick="togglePassword('password', this)"></i>
                        </div>
                        </div>

                    <div class="form-group password-wrapper">
                      <label for="confirm_password">Confirm Password</label>
                      <div class="password-field">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required minlength="8">
                      </div>
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="form-group">
                      <label>role</label>
                      <select name="role" required>
                        <option value="" disabled selected>Select role</option>
                        <?php foreach ($roles as $role_option): ?>
                          <option value="<?= htmlspecialchars($role_option['role_id']) ?>">
                            <?= htmlspecialchars($role_option['role_name']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                </div>

                  <!-- Form Buttons -->
                    <div class="form-actions">
                      <a href="#" id="cancelAddAccount" class="btn btn btn-outline btn-sm">
                        <i class="fa-solid fa-circle-xmark"></i> Cancel
                      </a>

                      <button type="submit" class="btn btn-primary" name="register">
                        <i class="fa-solid fa-floppy-disk"></i> Save
                      </button>
                    </div>
                </form>
              </div>

              <script>
              // Prevent browser from caching the form
              if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
              }
            </script>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

