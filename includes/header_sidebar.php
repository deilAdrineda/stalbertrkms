<?php
    include 'connection.php';
    include 'session_check.php'; //reusable_function is declared here already
    
    $roleMenus = getRoleMenus();
    $role = $_SESSION['role'] ?? '';
    // default partial used by JS in stalbertphp
    $currentPage = '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>St. Albert Medical & Diagnostic Clinic</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
    <link rel="stylesheet" href="css/view_account.css">
    <!-- <link rel="stylesheet" href="css/main.css"> -->
    <link rel="stylesheet" href="css/add_account.css">
    <link rel="stylesheet" href="css/dashboard.css" />
    <link rel="stylesheet" href="css/add_procedure.css">
    <?php
        // the following css link is for every pages that has its own external css style like add_account css
        // do take note that it needs to be fixed for every dynamic pages and not conflict with other css styles
        // find a way to fix it
        // dashboard css has the style for the accounts and the dashboard/template itself
    ?>
</head>
    <body>
        <div class="container">
                <!-- Sidebar -->
                <div class="sidebar">
                    <div class="logo">
                        <img src="logo/clinic_logo.png" alt="St. Albert Logo" />
                        <h1>St. Albert Medical and Diagnostic Clinic</h1>
                    </div>

                    <div class="nav-menu">
                        <?php 
                            // Render the nav as <a class="nav-item" data-page="partials/..."> so JS can load it
                            $menus = $roleMenus[$role] ?? [];
                            foreach ($menus as $menuItem) {
                                // build partial path: partials/<same-url-as-before>
                                $partialPath = 'partials/' . $menuItem['url'];
                                echo '<a href="#" class="nav-item" data-page="' . htmlspecialchars($partialPath) . '">';
                                echo '<i class="' . htmlspecialchars($menuItem['icon']) . '"></i><span>' . htmlspecialchars($menuItem['label']) . '</span>';
                                echo '</a>';
                            }
                        ?>
                    </div>
                    
                    <div class="logout-container">
                        <form action="logout.php" method="POST">
                            <button type="submit" class="logout-btn">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Header -->
                <div class="header">
                    <div class="user-profile">
                        <div class="profile-img"><?= htmlspecialchars($initials) ?></div>
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars($fullname) ?></div>
                            <div class="user-role"><?= htmlspecialchars($role) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="main-content">
                    <div id="main-content"><!-- content injected here --></div>
                            <!-- div is for loading icon -->
                            <div id="loader" style="text-align:center; display:none;">
                                <i class="fas fa-spinner fa-spin" style="font-size:40px;"></i>
                            </div>

                        <!-- continue to footer -->