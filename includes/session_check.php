<?php
include 'connection.php';
include 'reusable_functions.php';
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['username'])) {
    header('location:logout.php');
    exit;
} else {
    $current_time = date('Y-m-d H:i:s');

    // Step 1: Validate session
    $stmt = mysqli_prepare($con, "SELECT ua.username, ut.user_token, ut.expiration, up.user_password_id
        FROM user_account ua
        JOIN user_passwords up ON ua.user_password_id = up.user_password_id
        JOIN user_tokens ut ON up.user_token_id = ut.user_token_id
        WHERE ua.username = ?");
    mysqli_stmt_bind_param($stmt, 's', $_SESSION['username']);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_bind_result($stmt, $username_db, $user_token_db, $expiration_db, $user_password_id);
        mysqli_stmt_fetch($stmt);
        $_SESSION['user_password_id'] = $user_password_id;

        if ($username_db !== $_SESSION['username'] ||
            $user_token_db !== $_SESSION['user_token'] ||
            $current_time > $expiration_db) {
            header('location: logout.php');
            exit;
        }
        mysqli_stmt_close($stmt);
    }

    // Fetching fullname and role_name from joined roles table
    $stmt_user_info = mysqli_prepare($con, "SELECT ui.user_fname, ui.user_lname, r.role_name
        FROM user_account ua
        JOIN user_info ui ON ua.user_info_id = ui.user_info_id
        JOIN roles r ON ua.role_id = r.role_id
        WHERE ua.username = ?");
    mysqli_stmt_bind_param($stmt_user_info, 's', $_SESSION['username']);

    if (mysqli_stmt_execute($stmt_user_info)) {
        mysqli_stmt_bind_result($stmt_user_info, $user_fname, $user_lname, $role_name);
        mysqli_stmt_fetch($stmt_user_info);
        mysqli_stmt_close($stmt_user_info);

        $_SESSION['full_name'] = trim("$user_fname $user_lname");
        $_SESSION['role_name'] = $role_name; // -- Use this everywhere!
    }
}

// Permission checking function
function requireRole($requiredRoles) {
    if (!isset($_SESSION['role_name'])) {
        header('Location: index.php');
        exit;
    }
    $userRole = $_SESSION['role_name'];
    if (!is_array($requiredRoles)) {
        $requiredRoles = [$requiredRoles];
    }
    if (!in_array($userRole, $requiredRoles)) {
        redirectToRoleHome($userRole);
        exit;
    }
}

// Sidebar/header code (if you use it)
$username = $_SESSION['username'] ?? null;
$fullname = 'Unknown User';
$role = 'Unknown Role';

if ($username) {
    $stmt = mysqli_prepare($con, "
        SELECT CONCAT(ui.user_fname, ' ', ui.user_lname) AS full_name, r.role_name
        FROM user_account ua
        JOIN user_info ui ON ua.user_info_id = ui.user_info_id
        JOIN roles r ON ua.role_id = r.role_id
        WHERE ua.username = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $fullname, $role);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}

// For initials
$initials = '';
foreach (explode(' ', $fullname) as $word) {
    $initials .= strtoupper($word[0]);
}
?>