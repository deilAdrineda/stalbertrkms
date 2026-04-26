<?php
    include 'includes/header_sidebar.php';
    requireRole(['Administrator', 'Receptionist', 'Laboratory Personnel', 'ECG Personnel', 'Ultrasound Personnel', 'X-RAY Personnel', '2D Echo Personnel']);

            // ---------------------SECURED CODE FOR SUBPAGE 2---------------------
            $role = $_SESSION['role'] ?? '';
            $roleMenus = getRoleMenus();
            $defaultPage = $roleMenus[$role][0]['url'] ?? 'home/home_ad.php';
            $defaultPartial = 'partials/' . $defaultPage;
        ?>
        <script>
            // Provide default partial & user role to JS loader
            window.DEFAULT_PARTIAL = <?= json_encode($defaultPartial) ?>;
            window.CURRENT_ROLE = <?= json_encode($role) ?>;
        </script>

<?php 
    include 'includes/footer.php';
?>