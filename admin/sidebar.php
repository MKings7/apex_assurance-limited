<?php
// Get user initials for avatar
if (!isset($initials)) {
    $name_parts = explode(' ', $_SESSION['user_name']);
    $initials = '';
    foreach ($name_parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
}

// Determine current page for highlighting active menu item
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <h2>Apex Assurance</h2>
        <div class="role-badge">Administrator</div>
    </div>
    <div class="sidebar-menu">
        <div class="menu-category">Management</div>
        <ul>
            <li>
                <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="users.php" class="<?php echo ($current_page == 'users.php' || $current_page == 'view-user.php') ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>User Management</span>
                </a>
            </li>
            <li>
                <a href="claims-management.php" class="<?php echo ($current_page == 'claims-management.php' || $current_page == 'view-claim.php') ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Claims Management</span>
                </a>
            </li>
            <li>
                <a href="policies.php" class="<?php echo ($current_page == 'policies.php') ? 'active' : ''; ?>">
                    <i class="fas fa-shield-alt"></i>
                    <span>Policy Management</span>
                </a>
            </li>
            <li>
                <a href="repair-centers.php" class="<?php echo ($current_page == 'repair-centers.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tools"></i>
                    <span>Repair Centers</span>
                </a>
            </li>
        </ul>
        <div class="menu-category">System</div>
        <ul>
            <li>
                <a href="reports.php" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports & Analytics</span>
                </a>
            </li>
            <li>
                <a href="settings.php" class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>System Settings</span>
                </a>
            </li>
            <li>
                <a href="audit-log.php" class="<?php echo ($current_page == 'audit-log.php') ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>
                    <span>Audit Log</span>
                </a>
            </li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo $initials; ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                <div class="user-role">Administrator</div>
            </div>
        </div>
        <div class="logout-button">
            <a href="../logout.php" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>
