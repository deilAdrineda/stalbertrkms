<?php
    session_start();
    include 'includes/connection.php';
    include 'includes/reusable_functions.php';

    if(isset($_SESSION['user_password_id']) && isset($_SESSION['user_token'])){
        $stmt_update = mysqli_prepare($con, "UPDATE user_passwords SET user_token_id = NULL WHERE user_password_id =?");
        mysqli_stmt_bind_param($stmt_update, 'i', $_SESSION['user_password_id']);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        $stmt_delete = mysqli_prepare($con, "DELETE FROM user_tokens WHERE user_token = ?");
        mysqli_stmt_bind_param($stmt_delete, 's', $_SESSION['user_token']);
        mysqli_stmt_execute($stmt_delete);
        mysqli_stmt_close($stmt_delete);

        logMe($_SESSION['username'], "User logout", date('Y-m-d H:i:s'));
    }
    session_unset();
    session_destroy();
    header('location:index.php');
    exit;
?>