<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policy holders
require_role('PolicyHolder');

$user_id = $_SESSION['user_id'];
$success_message = '';

// Handle notification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'mark_read':
            $notification_id = filter_var($_POST['notification_id'], FILTER_VALIDATE_INT);
            if ($notification_id) {
                $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE Id = ? AND user_id = ?");
                $stmt->bind_param("ii", $notification_id, $user_id);
                $stmt->execute();
            }
            break;
            
        case 'mark_all_read':
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $success_message = "All notifications marked as read.";
            break;
            
        case 'delete':
            $notification_id = filter_var($_POST['notification_id'], FILTER_VALIDATE_INT);
            if ($notification_id) {
                $stmt = $conn->prepare("DELETE FROM notifications WHERE Id = ? AND user_id = ?");
                $stmt->bind_param("ii", $notification_id, $user_id);
                $stmt->execute();
            }
            break;
    }
}

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on filter
$sql = "SELECT * FROM notifications WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if ($filter === 'unread') {
    $sql .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $sql .= " AND is_read = 1";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$notifications = $stmt->get_result();

// Get notification counts
$counts = [
    'all' => 0,
    'unread' => 0,
    'read' => 0
];

$result = $conn->query("SELECT 
                       COUNT(*) as all_count,
                       SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_count,
                       SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_count
                       FROM notifications WHERE user_id = $user_id");

if ($row = $result->fetch_assoc()) {
    $counts['all'] = $row['all_count'];
    $counts['unread'] = $row['unread_count'];
    $counts['read'] = $row['read_count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Customer Portal - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        // ...existing customer portal styles...
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --customer-color: #007bff;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }
        
        body {
            line-height: 1.6;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--customer-color), #0056b3);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        /* Filter and Actions Bar */
        .filter-actions-bar {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
        }
        
        .filter-tab {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: white;
            color: #666;
            text-decoration: none;
            border-radius: 20px;
            font-size: 0.9rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .filter-tab.active {
            background: var(--customer-color);
            color: white;
            border-color: var(--customer-color);
        }
        
        .filter-tab:hover:not(.active) {
            background: #f8f9fa;
        }
        
        .badge {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .filter-tab:not(.active) .badge {
            background: var(--customer-color);
            color: white;
        }
        
        .bulk-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 0.85rem;
        }
        
        .btn-customer {
            background-color: var(--customer-color);
            color: white;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #ddd;
            color: #555;
        }
        
        /* Notifications Container */
        .notifications-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .container-header {
            background-color: var(--customer-color);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notifications-list {
            padding: 0;
        }
        
        .notification-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            transition: all 0.3s;
            position: relative;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item:hover {
            background-color: rgba(0, 123, 255, 0.02);
        }
        
        .notification-item.unread {
            background-color: rgba(0, 123, 255, 0.05);
            border-left: 4px solid var(--customer-color);
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1rem;
            color: white;
            flex-shrink: 0;
        }
        
        .notification-icon.policy { background-color: var(--customer-color); }
        .notification-icon.claim { background-color: var(--info-color); }
        .notification-icon.payment { background-color: var(--success-color); }
        .notification-icon.system { background-color: var(--warning-color); }
        .notification-icon.alert { background-color: var(--danger-color); }
        
        .notification-content {
            flex: 1;
            display: flex;
            align-items: flex-start;
        }
        
        .notification-body {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
            font-size: 1rem;
        }
        
        .notification-message {
            color: #666;
            line-height: 1.5;
            margin-bottom: 10px;
        }
        
        .notification-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 0.85rem;
            color: #999;
        }
        
        .notification-date {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .notification-type {
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            color: #666;
        }
        
        .notification-actions {
            display: flex;
            gap: 5px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .notification-item:hover .notification-actions {
            opacity: 1;
        }
        
        .notification-actions form {
            display: inline;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 0.75rem;
        }
        
        .btn-icon {
            padding: 6px;
            min-width: auto;
        }
        
        .unread-indicator {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 8px;
            height: 8px;
            background-color: var(--customer-color);
            border-radius: 50%;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #777;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            color: var(--success-color);
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
            }
        }
        
        @media (max-width: 768px) {
            .filter-actions-bar {
                flex-direction: column;
                gap: 20px;
                align-items: stretch;
            }
            
            .filter-tabs {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .bulk-actions {
                justify-content: center;
            }
            
            .notification-content {
                flex-direction: column;
            }
            
            .notification-actions {
                opacity: 1;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Notifications</h1>
                <p>Stay updated with your insurance activities</p>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <!-- Filter and Actions Bar -->
            <div class="filter-actions-bar">
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        All <?php if ($counts['all'] > 0): ?><span class="badge"><?php echo $counts['all']; ?></span><?php endif; ?>
                    </a>
                    <a href="?filter=unread" class="filter-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                        Unread <?php if ($counts['unread'] > 0): ?><span class="badge"><?php echo $counts['unread']; ?></span><?php endif; ?>
                    </a>
                    <a href="?filter=read" class="filter-tab <?php echo $filter === 'read' ? 'active' : ''; ?>">
                        Read <?php if ($counts['read'] > 0): ?><span class="badge"><?php echo $counts['read']; ?></span><?php endif; ?>
                    </a>
                </div>
                
                <div class="bulk-actions">
                    <?php if ($counts['unread'] > 0): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="mark_all_read">
                            <button type="submit" class="btn btn-customer">
                                <i class="fas fa-check-double"></i> Mark All Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notifications Container -->
            <div class="notifications-container">
                <div class="container-header">
                    <h2>
                        <?php 
                        switch($filter) {
                            case 'unread': echo 'Unread Notifications'; break;
                            case 'read': echo 'Read Notifications'; break;
                            default: echo 'All Notifications'; break;
                        }
                        ?> 
                        (<?php echo $notifications->num_rows; ?>)
                    </h2>
                </div>

                <?php if ($notifications->num_rows > 0): ?>
                    <div class="notifications-list">
                        <?php while ($notification = $notifications->fetch_assoc()): ?>
                            <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                                <?php if (!$notification['is_read']): ?>
                                    <div class="unread-indicator"></div>
                                <?php endif; ?>
                                
                                <div class="notification-content">
                                    <div class="notification-icon <?php echo strtolower($notification['type']); ?>">
                                        <?php
                                        $icon = 'bell';
                                        switch(strtolower($notification['type'])) {
                                            case 'policy': $icon = 'shield-alt'; break;
                                            case 'claim': $icon = 'file-alt'; break;
                                            case 'payment': $icon = 'credit-card'; break;
                                            case 'system': $icon = 'cog'; break;
                                            case 'alert': $icon = 'exclamation-triangle'; break;
                                        }
                                        ?>
                                        <i class="fas fa-<?php echo $icon; ?>"></i>
                                    </div>
                                    
                                    <div class="notification-body">
                                        <div class="notification-title">
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                        </div>
                                        <div class="notification-message">
                                            <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                        </div>
                                        <div class="notification-meta">
                                            <div class="notification-date">
                                                <i class="fas fa-clock"></i>
                                                <?php echo time_ago($notification['created_at']); ?>
                                            </div>
                                            <div class="notification-type">
                                                <?php echo ucfirst($notification['type']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="notification-actions">
                                        <?php if (!$notification['is_read']): ?>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['Id']; ?>">
                                                <button type="submit" class="btn btn-outline btn-sm btn-icon" title="Mark as read">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this notification?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['Id']; ?>">
                                            <button type="submit" class="btn btn-outline btn-sm btn-icon" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No Notifications</h3>
                        <p>
                            <?php 
                            switch($filter) {
                                case 'unread': echo 'You have no unread notifications.'; break;
                                case 'read': echo 'You have no read notifications.'; break;
                                default: echo 'You have no notifications yet.'; break;
                            }
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Auto-refresh every 2 minutes for new notifications
        setInterval(function() {
            if (window.location.search.includes('filter=unread') || window.location.search === '') {
                location.reload();
            }
        }, 120000);

        // Smooth animations on load
        document.addEventListener('DOMContentLoaded', function() {
            const notificationItems = document.querySelectorAll('.notification-item');
            notificationItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateX(0)';
                }, index * 50);
            });
        });
    </script>
</body>
</html>
