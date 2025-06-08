<?php
// Get current page name for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-shield-alt"></i>
            <span>Apex Assurance</span>
        </div>
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                <div class="user-role">Policy Holder</div>
            </div>
        </div>
    </div>

    <div class="sidebar-menu">
        <ul class="menu-list">
            <li class="menu-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <a href="index.php" class="menu-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <li class="menu-item <?php echo $current_page == 'policies.php' ? 'active' : ''; ?>">
                <a href="policies.php" class="menu-link">
                    <i class="fas fa-shield-alt"></i>
                    <span>My Policies</span>
                </a>
            </li>
            
            <li class="menu-item <?php echo $current_page == 'claims.php' ? 'active' : ''; ?>">
                <a href="claims.php" class="menu-link">
                    <i class="fas fa-file-alt"></i>
                    <span>Claims</span>
                </a>
            </li>
            
            <li class="menu-item <?php echo $current_page == 'vehicles.php' ? 'active' : ''; ?>">
                <a href="vehicles.php" class="menu-link">
                    <i class="fas fa-car"></i>
                    <span>My Vehicles</span>
                </a>
            </li>
            
            <li class="menu-item <?php echo $current_page == 'payments.php' ? 'active' : ''; ?>">
                <a href="payments.php" class="menu-link">
                    <i class="fas fa-credit-card"></i>
                    <span>Payments</span>
                </a>
            </li>
            
            <li class="menu-item <?php echo $current_page == 'documents.php' ? 'active' : ''; ?>">
                <a href="documents.php" class="menu-link">
                    <i class="fas fa-folder"></i>
                    <span>Documents</span>
                </a>
            </li>
            
            <li class="menu-item <?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>">
                <a href="notifications.php" class="menu-link">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                    <?php
                    // Get unread notification count
                    $unread_count = 0;
                    if (isset($_SESSION['user_id'])) {
                        $user_id = $_SESSION['user_id'];
                        $result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0");
                        if ($result && $row = $result->fetch_assoc()) {
                            $unread_count = $row['count'];
                        }
                    }
                    if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="menu-separator"></li>
            
            <li class="menu-item <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                <a href="profile.php" class="menu-link">
                    <i class="fas fa-user-circle"></i>
                    <span>Profile</span>
                </a>
            </li>
            
            <li class="menu-item <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                <a href="settings.php" class="menu-link">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="help.php" class="menu-link">
                    <i class="fas fa-question-circle"></i>
                    <span>Help & Support</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../logout.php" class="menu-link logout-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
</nav>

<style>
.sidebar {
    width: 250px;
    height: 100vh;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    position: fixed;
    left: 0;
    top: 0;
    z-index: 1000;
    transition: all 0.3s;
    overflow-y: auto;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
}

.sidebar-header {
    padding: 25px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.logo {
    display: flex;
    align-items: center;
    color: white;
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 20px;
}

.logo i {
    margin-right: 10px;
    font-size: 1.8rem;
}

.user-info {
    display: flex;
    align-items: center;
    padding: 15px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    backdrop-filter: blur(10px);
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    color: white;
}

.user-details {
    flex: 1;
}

.user-name {
    color: white;
    font-weight: 600;
    margin-bottom: 2px;
}

.user-role {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.8rem;
}

.sidebar-menu {
    padding: 0;
}

.menu-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.menu-item {
    margin: 0;
}

.menu-link {
    display: flex;
    align-items: center;
    padding: 15px 25px;
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    transition: all 0.3s;
    position: relative;
}

.menu-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    padding-left: 30px;
}

.menu-item.active .menu-link {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border-right: 3px solid white;
}

.menu-link i {
    margin-right: 12px;
    width: 20px;
    text-align: center;
    font-size: 1.1rem;
}

.menu-link span {
    font-weight: 500;
}

.notification-badge {
    background: #dc3545;
    color: white;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: auto;
    font-weight: bold;
    min-width: 18px;
    text-align: center;
}

.menu-separator {
    height: 1px;
    background: rgba(255, 255, 255, 0.1);
    margin: 10px 20px;
}

.logout-link:hover {
    background: rgba(220, 53, 69, 0.2);
    color: #ff6b6b;
}

/* Responsive */
@media (max-width: 991px) {
    .sidebar {
        width: 70px;
    }
    
    .sidebar-header {
        padding: 20px 10px;
    }
    
    .logo span,
    .user-details,
    .menu-link span {
        display: none;
    }
    
    .user-info {
        justify-content: center;
    }
    
    .menu-link {
        justify-content: center;
        padding: 15px;
    }
    
    .menu-link i {
        margin-right: 0;
    }
    
    .notification-badge {
        position: absolute;
        top: 8px;
        right: 8px;
    }
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: 250px;
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .logo span,
    .user-details,
    .menu-link span {
        display: block;
    }
    
    .user-info {
        justify-content: flex-start;
    }
    
    .menu-link {
        justify-content: flex-start;
        padding: 15px 25px;
    }
    
    .menu-link i {
        margin-right: 12px;
    }
}
</style>

<script>
// Toggle sidebar on mobile
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('active');
}

// Add click outside to close sidebar on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    
    if (window.innerWidth <= 768) {
        if (!sidebar.contains(event.target) && !toggleBtn?.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    }
});

// Update notification badge
function updateNotificationBadge() {
    fetch('get-notification-count.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.notification-badge');
            if (data.count > 0) {
                if (badge) {
                    badge.textContent = data.count;
                } else {
                    // Create badge if it doesn't exist
                    const notificationLink = document.querySelector('[href="notifications.php"]');
                    if (notificationLink) {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'notification-badge';
                        newBadge.textContent = data.count;
                        notificationLink.appendChild(newBadge);
                    }
                }
            } else if (badge) {
                badge.remove();
            }
        })
        .catch(error => console.log('Error updating notifications:', error));
}

// Update notification badge every minute
setInterval(updateNotificationBadge, 60000);
</script>
