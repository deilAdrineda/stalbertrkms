<!-- Main Content -->
<?php
include '../../includes/session_check.php';
  requireRole('Administrator');
?>
  <div class="page-title">
      <div class="title">Accounts</div>

        <!-- Search Bar -->
        <div class="search-bar">
              <i class="fas fa-search"></i>
                <input type="text" id = "searchLive" placeholder="Search..." />
                  </div>

        <div class="action-buttons">
          <button id="addAccountBtn" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Account
          </button>
        </div>
      </div>

      <!-- Filter Bar -->
<div class="filter-bar" style="margin-bottom: 20px; padding: 15px; background: var(--color-surface); border-radius: var(--radius-lg); display: flex; gap: 15px; align-items: center; border: 1px solid var(--color-border);">
    <label style="font-weight: var(--font-weight-medium); color: var(--color-text);">
        <i class="fas fa-filter"></i> Status:
    </label>
    <select id="statusFilter" class="form-control" style="width: 200px;">
        <option value="active" <?= (!isset($_GET['status']) || $_GET['status'] == 'active') ? 'selected' : '' ?>>Active Only</option>
        <option value="archived" <?= (isset($_GET['status']) && $_GET['status'] == 'archived') ? 'selected' : '' ?>>Archived Only</option>
        <option value="all" <?= (isset($_GET['status']) && $_GET['status'] == 'all') ? 'selected' : '' ?>>All Accounts</option>
    </select>
    
    <label style="font-weight: var(--font-weight-medium); color: var(--color-text); margin-left: 15px;">
        <i class="fas fa-sort"></i> Sort By:
    </label>
    <select id="sortFilter" class="form-control" style="width: 200px;">
        <option value="date_desc" <?= (!isset($_GET['sort']) || $_GET['sort'] == 'date_desc') ? 'selected' : '' ?>>Newest First</option>
        <option value="date_asc" <?= (isset($_GET['sort']) && $_GET['sort'] == 'date_asc') ? 'selected' : '' ?>>Oldest First</option>
        <option value="name_asc" <?= (isset($_GET['sort']) && $_GET['sort'] == 'name_asc') ? 'selected' : '' ?>>Name (A-Z)</option>
        <option value="name_desc" <?= (isset($_GET['sort']) && $_GET['sort'] == 'name_desc') ? 'selected' : '' ?>>Name (Z-A)</option>
        <option value="role" <?= (isset($_GET['sort']) && $_GET['sort'] == 'role') ? 'selected' : '' ?>>Role</option>
    </select>
</div>

<!-- Accounts Table -->
<div class="table-card">
    <table class="data-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Role</th>
          <th>Date Created</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>

      <tbody id ="accountsTable">
      <!-- get full name with role and formattedDate  -->
        <?php
        // ========== GET FILTER VALUES ==========
        $statusFilter = $_GET['status'] ?? 'active';
        $sortFilter = $_GET['sort'] ?? 'date_desc';

        // ========== BUILD ARCHIVE CONDITION ==========
        if ($statusFilter == 'archived') {
            $archiveCondition = "ui.is_archived = 1";
        } elseif ($statusFilter == 'active') {
            $archiveCondition = "ui.is_archived = 0";
        } else { // 'all'
            $archiveCondition = "1=1"; // Show everything
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

        // ========== EXECUTE QUERY ==========
        $query = "SELECT ui.user_info_id, 
                         CONCAT(ui.user_fname, ' ', ui.user_lname) AS full_name,
                         r.role_name, 
                         ui.user_created,
                         ui.is_archived
                  FROM user_account ua 
                  JOIN user_info ui ON ua.user_info_id = ui.user_info_id
                  JOIN roles r ON ua.role_id = r.role_id
                  WHERE $archiveCondition
                  ORDER BY $orderBy";

        $result = mysqli_query($con, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
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
        } else {
            echo '<tr><td colspan="5" style="text-align:center; color: var(--color-text-secondary); padding: 30px;">
                    <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.3; display: block; margin-bottom: 10px;"></i>
                    No accounts found.
                  </td></tr>';
        }
        ?>

        <!-- REMIND TO SELF, PUT SCRIPT ON MAIN JS -->
      </tbody>
    </table>         
</div>