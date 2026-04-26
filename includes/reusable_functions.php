<?php
date_default_timezone_set('Asia/Manila');

function alert($message, $redirectUrl = null) {
    if ($redirectUrl) {
        echo "<script>
            Swal.fire('$message').then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '$redirectUrl';
                }
            });
        </script>";
    } else {
        echo "<script>Swal.fire('$message');</script>";
    }
}

function logMe($username, $activity, $dateTime){
    include 'connection.php';
    $stmt = mysqli_prepare($con, "INSERT INTO user_logs (username, user_activity, user_log_date) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'sss', $username, $activity, $dateTime);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function calculateAge($dob) {
    $birthDate = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    return $age;
}

// redirects user to their home page based on role
function redirectToRoleHome($role) {
    switch ($role) {
        case 'Administrator':
            header('Location: st_albert.php?page=partials/home/home_ad.php');
            break;
        case 'Receptionist':
            header('Location: st_albert.php?page=partials/home/home_recep.php');
            break;
        case 'Ultrasound Personnel':
        case 'Laboratory Personnel':
        case 'X-RAY Personnel':
        case 'ECG Personnel':
        case '2D Echo Personnel':
            header('Location: st_albert.php?page=partials/home/home_personnel.php');
            break;
        default:
            header('Location: logout.php');
    }
    exit;
}

// // redirects after archiving account/patient based on role
// // function redirectToTables($role) {
// //     switch ($role) {
// //         case 'Administrator':
// //             header('Location: st_albert.php?page=partials/accounts/accounts.php');
// //             break;
// //         case 'Receptionist':
// //             header('Location: st_albert.php?page=partials/patients/patient.php');
// //             break;
// //         case 'Ultrasound Personnel':
// //         case 'Laboratory Personnel':
// //         case 'X-RAY Personnel':
// //         case 'ECG Personnel':
// //         case '2D Echo Personnel':
// //             header('Location: st_albert.php?page=partials/home/home_personnel.php');
// //             break;
// //         default:
// //             header('Location: logout.php');
// //     }
// //     exit;
// // }

// // returns allowed pages for a given role
// function getAllowedPages($role) {
//     $roleMenus = getRoleMenus();
//     $allowed = array_column($roleMenus[$role] ?? [], 'url');

//     $extraPages = [
//         'Administrator' => [
//             'partials/accounts/add_account.php',
//             'partials/accounts/view_account.php',
//             'partials/patients/view_patient.php',
//             'partials/accounts/edit_account.php',
//             'partials/accounts/search_acc.php',
//             'partials/services/add_service.php',
//             'partials/services/view_service.php',
//             'partials/patients/search_patients.php'
//         ],
//         // 'Receptionist' => [
//         //     'partials/patients/add_patient.php',
//         //     'partials/patients/view_patient.php',
//         //     'partials/patients/edit_patient.php',
//         //     'partials/home/home_recep.php',
//         //     'partials/patients/search_patients.php',
//         //     'partials/patients/avail_service.php'
//         // ],
//         'Ultrasound Personnel' => [
//             'partials/add_ultrasound.php',
//             'dashboard/view_ultrasound.php',
//             'dashboard/edit_ultrasound.php'
//         ],
//         'Laboratory Personnel' => [
//             'dashboard/add_laboratory.php',
//             'dashboard/view_laboratory.php',
//             'dashboard/edit_laboratory.php'
//         ],
//         'ECG Personnel' => [
//             'dashboard/add_laboratory.php',
//             'dashboard/view_laboratory.php',
//             'dashboard/edit_laboratory.php'
//         ],
//         '2D Echo Personnel' => [
//             'dashboard/add_laboratory.php',
//             'dashboard/view_laboratory.php',
//             'dashboard/edit_laboratory.php'
//         ],
//         'X-RAY Personnel' => [
//             'dashboard/add_laboratory.php',
//             'dashboard/view_laboratory.php',
//             'dashboard/edit_laboratory.php'
//         ],
//     ];

//     if (isset($extraPages[$role])) {
//         $allowed = array_merge($allowed, $extraPages[$role]);
//     }

//     return $allowed;
// }

// // centralized role menus
function getRoleMenus() {
    $clinicPersonnelMenu = [
        ['icon' => 'fas fa-home', 'label' => 'Home', 'url' => 'home/home_personnel.php'],
        ['icon' => 'fas fa-user-injured', 'label' => 'Patient', 'url' => 'patients/patient.php'],
        ['icon' => 'fas fa-file-alt', 'label' => 'Reports', 'url' => 'reports/reports.php'],
        ['icon' => 'fas fa-clipboard-list', 'label' => 'Logs', 'url' => 'logs/logs.php'],
        ['icon' => 'fas fa-user', 'label' => 'My Profile', 'url' => 'accounts/view_account.php'],
    ];

    return [
        'Administrator' => [
            ['icon' => 'fas fa-home', 'label' => 'Home', 'url' => 'home/home_ad.php'],
            ['icon' => 'fas fa-user', 'label' => 'Accounts', 'url' => 'accounts/accounts.php'],
            ['icon' => 'fas fa-concierge-bell', 'label' => 'Service', 'url' => 'services/service.php'],
            ['icon' => 'fas fa-box', 'label' => 'Packages', 'url' => 'packages/packages.php'],
            ['icon' => 'fas fa-percent', 'label' => 'Discount', 'url' => 'discounts/discounts.php'],
            ['icon' => 'fas fa-user-injured', 'label' => 'Patient', 'url' => 'patients/patient.php'],
            ['icon' => 'fas fa-file-alt', 'label' => 'Reports', 'url' => 'reports/reports.php'],
            ['icon' => 'fas fa-clipboard-list', 'label' => 'Logs', 'url' => 'logs/logs.php'],
            // ['icon' => 'fas fa-user', 'label' => 'My Profile', 'url' => 'accounts/view_account.php'],
        ],
        'Receptionist' => [
            ['icon' => 'fas fa-home', 'label' => 'Home', 'url' => 'home/home_recep.php'],
            ['icon' => 'fas fa-user-injured', 'label' => 'Patient', 'url' => 'patients/patient.php'],
            ['icon' => 'fas fa-concierge-bell', 'label' => 'Service', 'url' => 'services/service.php'],
            ['icon' => 'fas fa-file-alt', 'label' => 'Reports', 'url' => 'reports/reports.php'],
            ['icon' => 'fas fa-clipboard-list', 'label' => 'Logs', 'url' => 'logs/logs.php'],
            ['icon' => 'fas fa-user', 'label' => 'My Profile', 'url' => 'accounts/view_account.php'],
        ],
        'Ultrasound Personnel' => $clinicPersonnelMenu,
        'Laboratory Personnel' => $clinicPersonnelMenu,
        'X-RAY Personnel' => $clinicPersonnelMenu,
        'ECG Personnel' => $clinicPersonnelMenu,
        '2D Echo Personnel' => $clinicPersonnelMenu,
        // (Add more roles as needed)
    ];
}

// Render sidebar with current menu and highlight
function renderSidebar($role, $currentPage = null) {
    $roleMenus = getRoleMenus();
    if (!isset($roleMenus[$role])) {
        return '<div class="nav-item"><i class="fas fa-info-circle"></i><span>No Access</span></div>';
    }
    $menus = $roleMenus[$role];
    $currentPage = $currentPage ?? ($menus[0]['url'] ?? '');
    $html = '';
    $baseUrl = basename($_SERVER['PHP_SELF']);

    foreach ($menus as $menuItem) {
        $activeClass = ($menuItem['url'] === $currentPage) ? ' active' : '';
        $html .= '<a href="' . $baseUrl . '?page=' . urlencode($menuItem['url']) . '" class="nav-item' . $activeClass . '">';
        $html .= '<i class="' . htmlspecialchars($menuItem['icon']) . '"></i><span>' . htmlspecialchars($menuItem['label']) . '</span>';
        $html .= '</a>';
    }
    return $html;
}
?>
