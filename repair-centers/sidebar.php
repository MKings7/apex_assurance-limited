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
        <div class="role-badge">Repair Center</div>
    </div>
    <div class="sidebar-menu">
        <ul>
            <li>
                <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="assigned-claims.php" class="<?php echo ($current_page == 'assigned-claims.php') ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Assigned Claims</span>
                </a>
            </li>
            <li>
                <a href="estimates.php" class="<?php echo ($current_page == 'estimates.php') ? 'active' : ''; ?>">
                    <i class="fas fa-calculator"></i>
                    <span>Repair Estimates</span>
                </a>
            </li>
            <li>
                <a href="work-orders.php" class="<?php echo ($current_page == 'work-orders.php') ? 'active' : ''; ?>">
                    <i class="fas fa-wrench"></i>
                    <span>Work Orders</span>
                </a>
            </li>
            <li>
                <a href="profile.php" class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog"></i>
                    <span>Center Profile</span>
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
                <div class="user-role">Repair Center</div>
            </div>
        </div>
        <div class="logout-button">
            <a href="../logout.php" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>
