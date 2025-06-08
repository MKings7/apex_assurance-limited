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

<style>
/* Sidebar Styles */
.sidebar {
    width: 250px;
    background-color: var(--sidebar-color);
    color: white;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    transition: all 0.3s;
    z-index: 100;
}

.sidebar-header {
    padding: 20px;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    background-color: rgba(0, 0, 0, 0.1);
}

.sidebar-header h2 {
    color: white;
    font-size: 1.3rem;
    margin-bottom: 5px;
}

.role-badge {
    background-color: var(--user-color);
    color: white;
    font-size: 0.7rem;
    padding: 3px 10px;
    border-radius: 20px;
    display: inline-block;
    margin-top: 5px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.sidebar-menu ul {
    list-style: none;
    padding: 0;
    margin: 20px 0;
}

.sidebar-menu li {
    margin-bottom: 2px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    transition: all 0.3s;
}

.sidebar-menu a:hover, 
.sidebar-menu a.active {
    background-color: rgba(255, 255, 255, 0.1);
    color: white;
    border-left: 4px solid var(--user-color);
}

.sidebar-menu i {
    margin-right: 10px;
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
}

.sidebar-footer {
    padding: 15px 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    position: absolute;
    bottom: 0;
    width: 100%;
    background-color: rgba(0, 0, 0, 0.2);
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
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background-color: var(--user-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.9rem;
    margin-right: 10px;
}

.user-details {
    overflow: hidden;
}

.user-name {
    font-weight: bold;
    font-size: 0.8rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-role {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.5);
}

.logout-button a {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    padding: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    transition: all 0.3s;
}

.logout-button a:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: white;
}

/* Mobile sidebar */
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
    
    .sidebar-footer {
        padding: 10px;
        justify-content: center;
    }
    
    .user-info {
        justify-content: center;
    }
    
    .user-avatar {
        margin-right: 0;
    }
    
    .logout-button {
        display: none;
    }
}
</style>

<aside class="sidebar">
    <div class="sidebar-header">
        <h2>Apex Assurance</h2>
        <div class="role-badge">Policyholder</div>
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
                <a href="policies.php" class="<?php echo ($current_page == 'policies.php') ? 'active' : ''; ?>">
                    <i class="fas fa-shield-alt"></i>
                    <span>My Policies</span>
                </a>
            </li>
            <li>
                <a href="claims.php" class="<?php echo ($current_page == 'claims.php') ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>My Claims</span>
                </a>
            </li>
            <li>
                <a href="../vehicles.php" class="<?php echo ($current_page == 'vehicles.php') ? 'active' : ''; ?>">
                    <i class="fas fa-car"></i>
                    <span>My Vehicles</span>
                </a>
            </li>
            <li>
                <a href="payments.php" class="<?php echo ($current_page == 'payments.php') ? 'active' : ''; ?>">
                    <i class="fas fa-credit-card"></i>
                    <span>Payments</span>
                </a>
            </li>
            <li>
                <a href="documents.php" class="<?php echo ($current_page == 'documents.php') ? 'active' : ''; ?>">
                    <i class="fas fa-folder"></i>
                    <span>Documents</span>
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
                    <span>Profile</span>
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
