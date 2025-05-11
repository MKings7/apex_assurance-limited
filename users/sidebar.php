<?php
// Get user initials for avatar
if (!isset($initials)) {
    $name_parts = explode(' ', $_SESSION['user_name']);
    $initials = '';
    foreach ($name_parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
}

// Get notification count if not already set
if (!isset($notification_count)) {
    $notification_count = get_unread_notification_count($_SESSION['user_id']);
}

// Determine current page for highlighting active menu item
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <h2>Apex Assurance</h2>
    </div>
    <div class="sidebar-menu">
        <div class="menu-category">Main</div>
        <ul>
            <li>
                <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="claims.php" class="<?php echo ($current_page == 'claims.php') ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>My Claims</span>
                </a>
            </li>
            <li>
                <a href="report-accident.php" class="<?php echo ($current_page == 'report-accident.php') ? 'active' : ''; ?>">
                    <i class="fas fa-car-crash"></i>
                    <span>Report Accident</span>
                </a>
            </li>
            <li>
                <a href="policies.php" class="<?php echo ($current_page == 'policies.php') ? 'active' : ''; ?>">
                    <i class="fas fa-shield-alt"></i>
                    <span>My Policies</span>
                </a>
            </li>
        </ul>
        <div class="menu-category">Account</div>
        <ul>
            <li>
                <a href="profile.php" class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li>
                <a href="vehicles.php" class="<?php echo ($current_page == 'vehicles.php') ? 'active' : ''; ?>">
                    <i class="fas fa-car"></i>
                    <span>My Vehicles</span>
                </a>
            </li>
            <li>
                <a href="notifications.php" class="<?php echo ($current_page == 'notifications.php') ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                    <?php if ($notification_count > 0): ?>
                        <span class="badge"><?php echo $notification_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
        <div class="menu-category">Support</div>
        <ul>
            <li>
                <a href="help.php" class="<?php echo ($current_page == 'help.php') ? 'active' : ''; ?>">
                    <i class="fas fa-question-circle"></i>
                    <span>Help Center</span>
                </a>
            </li>
            <li>
                <a href="emergency.php" class="<?php echo ($current_page == 'emergency.php') ? 'active' : ''; ?>">
                    <i class="fas fa-ambulance"></i>
                    <span>Emergency</span>
                </a>
            </li>
            <li>
                <a href="contact.php" class="<?php echo ($current_page == 'contact.php') ? 'active' : ''; ?>">
                    <i class="fas fa-headset"></i>
                    <span>Contact Us</span>
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
                <div class="user-role">Policyholder</div>
            </div>
        </div>
        <div class="logout-button">
            <a href="../logout.php" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>
