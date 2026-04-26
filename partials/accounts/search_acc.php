<?php
// include '../../includes/connection.php';
// include '../../includes/session_check.php';
// requireRole('Administrator');
// $search = isset($_POST['search']) ? trim($_POST['search']) : '';

// // Base query with JOIN to get role_name
// $baseQuery = "
//     SELECT ui.user_info_id, CONCAT(ui.user_fname, ' ', ui.user_lname) AS full_name,
//            r.role_name, ui.user_created
//     FROM user_account ua
//     JOIN user_info ui ON ua.user_info_id = ui.user_info_id
//     JOIN roles r ON ua.role_id = r.role_id
//     WHERE ui.is_archived = 0
// ";

// if ($search === '') {
//     // Default query (no filter)
//     $query = $baseQuery . " ORDER BY ui.user_created DESC";
//     $result = mysqli_query($con, $query);
// } else {
//     // Search query
//     $query = $baseQuery . "
//         AND (CONCAT(ui.user_fname, ' ', ui.user_lname) LIKE ? OR r.role_name LIKE ?)
//         ORDER BY ui.user_created DESC
//     ";
//     $stmt = mysqli_prepare($con, $query);
//     $param = "%$search%";
//     mysqli_stmt_bind_param($stmt, "ss", $param, $param);
//     mysqli_stmt_execute($stmt);
//     $result = mysqli_stmt_get_result($stmt);
// }

// $existResult = false;

// while ($row = mysqli_fetch_assoc($result)) {
//     $existResult = true;
//     $formattedDate = date("M d, Y", strtotime($row['user_created']));
//     echo "<tr>";
//     echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
//     echo "<td>" . htmlspecialchars($row['role_name']) . "</td>";
//     echo "<td>" . $formattedDate . "</td>";
//     echo '<td>
//             <button type="button" class="btn btn-outline btn-sm view-account-btn" 
//                     data-user_info_id="' . htmlspecialchars($row['user_info_id']) . '">
//                 <i class="fas fa-eye"></i> View
//             </button>
//         </td>';
//     echo "</tr>";
// }

// if (!$existResult) {
//     echo "<tr><td colspan='4' style='text-align:center;'>No results found for <b>" . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . "</b>.</td></tr>";
// }
?>

<?php
include '../../includes/connection.php';
include '../../includes/session_check.php';
requireRole('Administrator');

$search = isset($_POST['search']) ? trim($_POST['search']) : '';
$statusFilter = isset($_POST['status']) ? $_POST['status'] : 'active';
$sortFilter = isset($_POST['sort']) ? $_POST['sort'] : 'date_desc';

// ========== BUILD ARCHIVE CONDITION ==========
if ($statusFilter == 'archived') {
    $archiveCondition = "ui.is_archived = 1";
} elseif ($statusFilter == 'active') {
    $archiveCondition = "ui.is_archived = 0";
} else { // 'all'
    $archiveCondition = "1=1";
}

// ========== BUILD ORDER BY CLAUSE ==========
switch ($sortFilter) {
    case 'date_asc':
        $orderBy = "ui.user_created ASC";
        break;
    case 'name_asc':
        $orderBy = "ui.user_fname ASC, ui.user_lname ASC";
        break;
    case 'name_desc':
        $orderBy = "ui.user_fname DESC, ui.user_lname DESC";
        break;
    case 'role':
        $orderBy = "r.role_name ASC, ui.user_fname ASC";
        break;
    case 'date_desc':
    default:
        $orderBy = "ui.user_created DESC";
        break;
}

// Base query with JOIN to get role_name and is_archived
$baseQuery = "
    SELECT ui.user_info_id, 
           CONCAT(ui.user_fname, ' ', ui.user_lname) AS full_name,
           r.role_name, 
           ui.user_created,
           ui.is_archived
    FROM user_account ua
    JOIN user_info ui ON ua.user_info_id = ui.user_info_id
    JOIN roles r ON ua.role_id = r.role_id
    WHERE $archiveCondition
";

if ($search === '') {
    // No search filter - just apply archive filter and sort
    $query = $baseQuery . " ORDER BY $orderBy";
    $result = mysqli_query($con, $query);
} else {
    // Search query with archive filter
    $query = $baseQuery . "
        AND (CONCAT(ui.user_fname, ' ', ui.user_lname) LIKE ? OR r.role_name LIKE ?)
        ORDER BY $orderBy
    ";
    $stmt = mysqli_prepare($con, $query);
    $param = "%$search%";
    mysqli_stmt_bind_param($stmt, "ss", $param, $param);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

$existResult = false;

while ($row = mysqli_fetch_assoc($result)) {
    $existResult = true;
    $formattedDate = date("M d, Y", strtotime($row['user_created']));
    $isArchived = $row['is_archived'] == 1;
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['role_name']) . "</td>";
    echo "<td>" . $formattedDate . "</td>";
    
    // Status badge
    echo "<td>";
    if ($isArchived) {
        echo '<span class="status status--error">Inactive</span>';
    } else {
        echo '<span class="status status--success">Active</span>';
    }
    echo "</td>";
    
    // Actions
    echo '<td>
            <button type="button" class="btn btn-outline btn-sm view-account-btn" 
                    data-user_info_id="' . htmlspecialchars($row['user_info_id']) . '">
                <i class="fas fa-eye"></i> View
            </button>
        </td>';
    echo "</tr>";
}

if (!$existResult) {
    $statusText = '';
    if ($statusFilter == 'archived') {
        $statusText = ' in archived accounts';
    } elseif ($statusFilter == 'active') {
        $statusText = ' in active accounts';
    }
    
    if ($search !== '') {
        echo "<tr><td colspan='5' style='text-align:center; padding: 30px; color: var(--color-text-secondary);'>
                <i class='fas fa-search' style='font-size: 48px; opacity: 0.3; display: block; margin-bottom: 10px;'></i>
                No results found for <b>" . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . "</b>" . $statusText . ".
              </td></tr>";
    } else {
        echo "<tr><td colspan='5' style='text-align:center; padding: 30px; color: var(--color-text-secondary);'>
                <i class='fas fa-inbox' style='font-size: 48px; opacity: 0.3; display: block; margin-bottom: 10px;'></i>
                No accounts found" . $statusText . ".
              </td></tr>";
    }
}
?>