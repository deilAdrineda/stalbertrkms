<?php
    include 'includes/connection.php';
    include 'includes/reusable_functions.php';

    // this code is to prevent going back to this login page
    session_start();
    if (isset($_SESSION['username'], $_SESSION['role'])) {
        header('Location: st_albert.php');
        exit;
    }
    // Prevent browser from caching the login page
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class = "login-container">
        <div class = "left-section">
            <img src="logo/clinic_logo.png" alt="St. Albert Logo" class = "logo">
            <h1>St. Albert Medical and Diagnostic Clinic</h1>
        </div>

        <div class = "right-section">
            <form class = "login-form" method="POST" >
                <h2>Sign In</h2>

                <div class = "form-floating mb-3">
                    <input type="username" class="form-control" id="floatingInput" name = "username" placeholder = "Username" required>
                    <label for="floatingInput">Username</label>
                </div>

                <div class = "form-floating">
                    <input type="password" autocomplete = "new-password" class = "form-control" id="floatingPassword" name = "password" placeholder = "Password" >
                    <label for="floatingPassword">Password</label>
                </div>

                <button type = "submit" class = "login-button" name = "login">Login</button>
            </form>

            <footer>
                <p>© 2025 St. Albert Medical Clinic – All Rights Reserved</p>
            </footer>
        </div>
    </div>
    <script src = "https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>

<?php
    if($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["login"])) {
        $username = trim(mysqli_real_escape_string($con, $_POST['username']));
        $password = trim(mysqli_real_escape_string($con, $_POST['password']));

        if($username === '') {
            alert('Username is required!');
            return;
        }

        if($password === '') {
            alert('Password is required!');
            return;
        }

        //login attempts
        $attempt_check_stmt = mysqli_prepare($con, "SELECT COUNT(*) FROM user_attempts
        WHERE username = ? AND attempt_date_time > (NOW() - INTERVAL 30 SECOND)");
        mysqli_stmt_bind_param($attempt_check_stmt, 's', $username);
        mysqli_stmt_execute($attempt_check_stmt);
        mysqli_stmt_bind_result($attempt_check_stmt, $attempts);
        mysqli_stmt_fetch($attempt_check_stmt);
        mysqli_stmt_close($attempt_check_stmt);

        //cooldown when too many attemtmps
        if($attempts > 3) {
            alert('Too many attempts. Try again in 30 Seconds');
            return;
            //CHANGE THIS IF RECOMMENDED
        }

        $stmt = mysqli_prepare($con, "SELECT ua.user_password_id
        FROM user_account ua
        JOIN user_info ui ON ua.user_info_id = ui.user_info_id
        WHERE ua.username = ? AND ui.is_archived = 0");

        mysqli_stmt_bind_param($stmt, "s", $username);

        if(mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);

            //check if username exist
            if(mysqli_stmt_num_rows($stmt) >0 ) {
                mysqli_stmt_bind_result($stmt, $user_password_id);
                mysqli_stmt_fetch($stmt);
                $stmt_select_password = mysqli_prepare($con, "SELECT user_password FROM user_passwords WHERE user_password_id = ?");
                mysqli_stmt_bind_param($stmt_select_password, 'i', $user_password_id);

                //fetching password
                if(mysqli_stmt_execute($stmt_select_password)) {
                    mysqli_stmt_bind_result($stmt_select_password, $user_password_from_db);
                    mysqli_stmt_fetch($stmt_select_password);
                    mysqli_stmt_close($stmt_select_password);

                    //verify password
                    if(password_verify($password, $user_password_from_db)) {
                        $user_token = bin2hex(random_bytes(32));

                        //expiration of token
                        $expiration = date('Y-m-d H:i:s', strtotime('+30 minutes'));

                        $stmt_token = mysqli_prepare($con, 'INSERT INTO user_tokens (user_token, expiration) VALUES (?, ?)');
                        mysqli_stmt_bind_param($stmt_token, "ss", $user_token, $expiration);
                        if(mysqli_stmt_execute($stmt_token)){
                        $user_token_id = mysqli_insert_id($con);
                        $stmt_set_token = mysqli_prepare($con, 'UPDATE user_passwords SET user_token_id = ? WHERE user_password_id = ?');
                        mysqli_stmt_bind_param($stmt_set_token, 'ii', $user_token_id, $user_password_id);

                        if(mysqli_stmt_execute($stmt_set_token)) {
                            session_start();
                            $_SESSION['username'] = $username;
                            $_SESSION['user_token'] = $user_token;

                            // fetch the users role
                            $stmt_get_role = mysqli_prepare($con, "
                                SELECT r.role_name 
                                FROM user_account ua
                                JOIN roles r ON ua.role_id = r.role_id
                                WHERE ua.username = ?
                                LIMIT 1
                            ");
                            mysqli_stmt_bind_param($stmt_get_role, 's', $username);
                            mysqli_stmt_execute($stmt_get_role);
                            mysqli_stmt_bind_result($stmt_get_role, $role);
                            mysqli_stmt_fetch($stmt_get_role);
                            mysqli_stmt_close($stmt_get_role);

                            $_SESSION['role'] = $role;
                            
                                        //note on activity on user login, change the 'user' according to-
                                        //the role kahit sinabi na username even possible.
                            logMe($username, "User Login", date('Y-m-d H:i:s'));

                            // Immediately after a successful password check (e.g. just before header('Location: st_albert.php');)
                            $stmt_get_info_id = mysqli_prepare($con,
                            "SELECT user_info_id FROM user_account WHERE username = ? LIMIT 1"
                            );
                            mysqli_stmt_bind_param($stmt_get_info_id, 's', $username);
                            mysqli_stmt_execute($stmt_get_info_id);
                            mysqli_stmt_bind_result($stmt_get_info_id, $user_info_id);
                            mysqli_stmt_fetch($stmt_get_info_id);
                            mysqli_stmt_close($stmt_get_info_id);

                            $_SESSION['user_info_id'] = $user_info_id;


                            //redirect to landing page regardless of role
                            header('Location: st_albert.php');
                            exit;
                            mysqli_stmt_close($stmt_set_token);
                        }
                        mysqli_stmt_close($stmt_set_token);
                    }
                    mysqli_stmt_close($stmt_set_token);
                } else {
                    //failed password attempt
                    $insert_attempt_stmt = mysqli_prepare($con, "INSERT INTO user_attempts (username, attempt_date_time) VALUES (?, NOW())");
                    mysqli_stmt_bind_param($insert_attempt_stmt, 's', $username);
                    mysqli_stmt_execute($insert_attempt_stmt);
                    mysqli_stmt_close($insert_attempt_stmt);

                    alert('Incorrect Username or Password');
                }
            } else {
                mysqli_stmt_close($stmt_select_password);
            }
        } else {
            //failed username attempt
            $insert_attempt_stmt = mysqli_prepare($con, "INSERT INTO user_attempts(username, attempt_date_time) VALUES (?, NOW())");
            mysqli_stmt_bind_param($insert_attempt_stmt, 's', $username);
            mysqli_stmt_execute($insert_attempt_stmt);
            mysqli_stmt_close($insert_attempt_stmt);

            alert('Incorrect Username or Password');
        }
        mysqli_stmt_close($stmt);
    } else {
        mysqli_stmt_close($stmt);
    }
                    
    }

?>