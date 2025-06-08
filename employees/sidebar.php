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
        <div class="role-badge">Employee</div>
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
                <a href="tasks.php" class="<?php echo ($current_page == 'tasks.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tasks"></i>
                    <span>My Tasks</span>
                </a>
            </li>
            <li>
                <a href="customer-service.php" class="<?php echo ($current_page == 'customer-service.php') ? 'active' : ''; ?>">
                    <i class="fas fa-headset"></i>
                    <span>Customer Service</span>
                </a>
            </li>
            <li>
                <a href="reports.php" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Performance Reports</span>
                </a>
            </li>
            <li>
                <a href="notifications.php" class="<?php echo ($current_page == 'notifications.php') ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
            </li>
            <li>
                <a href="../profile.php" class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog"></i>
                    <span>Profile Settings</span>
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
                <div class="user-role">Employee</div>
            </div>
        </div>
        <div class="logout-button">
            <a href="../logout.php" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>

<style>
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 250px;
    height: 100vh;
    background: linear-gradient(135deg, #e83e8c, #0056b3);
    color: white;
    z-index: 1000;
    transition: all 0.3s;
    display: flex;
    flex-direction: column;
}

.sidebar-header {
    padding: 25px 20px;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-header h2 {
    font-size: 1.5rem;
    margin-bottom: 8px;
    font-weight: 600;
}

.role-badge {
    background-color: rgba(255, 255, 255, 0.2);
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.sidebar-menu {
    flex: 1;
    padding: 20px 0;
}

.sidebar-menu ul {
    list-style: none;
}

.sidebar-menu li {
    margin-bottom: 5px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    padding: 12px 25px;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: all 0.3s;
    border-left: 3px solid transparent;
}

.sidebar-menu a:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: white;
    border-left-color: rgba(255, 255, 255, 0.5);
}

.sidebar-menu a.active {
    background-color: rgba(255, 255, 255, 0.15);
    color: white;
    border-left-color: white;
}

.sidebar-menu i {
    width: 20px;
    margin-right: 12px;
    text-align: center;
}

.sidebar-footer {
    padding: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.user-info {
    display: flex;
    align-items: center;
    flex: 1;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    font-weight: bold;
    font-size: 0.9rem;
}

.user-details {
    flex: 1;
}

.user-name {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 2px;
}

.user-role {
    font-size: 0.75rem;
    opacity: 0.8;
}

.logout-button a {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background-color: rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-decoration: none;
    transition: all 0.3s;
}

.logout-button a:hover {
    background-color: rgba(255, 255, 255, 0.2);
}

/* Responsive */
@media (max-width: 991px) {
    .sidebar {
        width: 70px;
    }
    
    .sidebar-header h2,
    .role-badge,
    .sidebar-menu span,
    .user-details {
        display: none;
    }
    
    .sidebar-menu a {
        justify-content: center;
        padding: 12px;
    }
    
    .sidebar-menu i {
        margin-right: 0;
    }
    
    .user-info {
        justify-content: center;
    }
    
    .sidebar-footer {
        flex-direction: column;
        gap: 10px;
    }
}
</style>
