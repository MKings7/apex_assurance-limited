<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure the user is logged in
require_login();

// Only for policyholders
require_role('Policyholder');

$user_id = $_SESSION['user_id'];
$success_message = '';

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_read') {
        $notification_id = filter_var($_POST['notification_id'], FILTER_VALIDATE_INT);
        if ($notification_id) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE Id = ? AND user_id = ?");
            $stmt->bind_param("ii", $notification_id, $user_id);
            $stmt->execute();
        }
    } elseif ($_POST['action'] === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $success_message = "All notifications marked as read.";
    }
}

// Get notifications with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$sql = "SELECT * FROM notifications WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if ($filter === 'unread') {
    $sql .= " AND is_read = 0";
}

$sql .= " ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$notifications = $stmt->get_result();

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
if ($filter === 'unread') {
    $count_sql .= " AND is_read = 0";
}
$stmt = $conn->prepare($count_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_notifications = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_notifications / $per_page);

// Get unread count
$stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Apex Assurance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        // ...existing user styles...
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary-color: #0056b3;
            --user-color: #007bff;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --dark-color: #333;
        }
        
        body {
            line-height: 1.6;
            background-color: #f8f9fa;
            color: var(--dark-color);
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
        
        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .filter-tabs {
            display: flex;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 5px;
            margin-bottom: 20px;
        }
        
        .filter-tab {
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            color: #777;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .filter-tab.active {
            background-color: var(--user-color);
            color: white;
        }
        
        .notifications-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .notifications-list {
            max-height: 70vh;
            overflow-y: auto;
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
        
        .notification-item.unread {
            background-color: rgba(0, 123, 255, 0.02);
            border-left: 4px solid var(--user-color);
        }
        
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .notification-title {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
        
        .notification-type {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .type-claim {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .type-policy {
            background-color: rgba(0, 123, 255, 0.1);
            color: var(--user-color);
        }
        
        .type-payment {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .type-system {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        .notification-meta {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: #777;
        }
        
        .notification-actions {
            display: flex;
            gap: 5px;
        }
        
        .action-btn {
            padding: 5px 8px;
            border: none;
            border-radius: 3px;
            background-color: transparent;
            color: #777;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            background-color: #e9ecef;
        }
        
        .notification-message {
            color: #555;
            line-height: 1.5;
            margin-bottom: 10px;
        }
        
        .unread-indicator {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--user-color);
        }
        
        /* Pagination */
        .pagination {
            padding: 20px;
            text-align: center;
            border-top: 1px solid #eee;
        }
        
        .pagination a,
        .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 2px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #555;
        }
        
        .pagination a:hover {
            background-color: var(--user-color);
            color: white;
            border-color: var(--user-color);
        }
        
        .pagination .current {
            background-color: var(--user-color);
            color: white;
            border-color: var(--user-color);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--user-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background-color: #0056b3;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--user-color);
            color: var(--user-color);
        }
        
        .btn-outline:hover {
            background-color: var(--user-color);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
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
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            color: var(--success-color);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="notifications-header">
                <div class="content-title">
                    <h1>Notifications</h1>
                    <p>Stay updated with your insurance activities</p>
                </div>
                <div class="header-actions">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-outline">
                            <i class="fas fa-check-double"></i> Mark All Read
                        </button>
                    </form>
                </div>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    All Notifications (<?php echo $total_notifications; ?>)
                </a>
                <a href="?filter=unread" class="filter-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                    Unread (<?php echo $unread_count; ?>)
                </a>
            </div>

            <!-- Notifications Container -->
            <div class="notifications-container">
                <?php if ($notifications->num_rows > 0): ?>
                    <div class="notifications-list">
                        <?php while ($notification = $notifications->fetch_assoc()): ?>
                            <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                                <?php if (!$notification['is_read']): ?>
                                    <div class="unread-indicator"></div>
                                <?php endif; ?>
                                
                                <div class="notification-header">
                                    <div>
                                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                        <span class="notification-type type-<?php echo strtolower($notification['type']); ?>">
                                            <?php echo $notification['type']; ?>
                                        </span>
                                    </div>
                                    <div class="notification-meta">
                                        <span class="notification-time"><?php echo time_ago($notification['created_at']); ?></span>
                                        <div class="notification-actions">
                                            <?php if (!$notification['is_read']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="mark_read">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['Id']; ?>">
                                                    <button type="submit" class="action-btn" title="Mark as read">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="notification-message">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page-1; ?>&filter=<?php echo $filter; ?>">&laquo; Previous</a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page+1; ?>&filter=<?php echo $filter; ?>">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bell"></i>
                        <h3>No Notifications</h3>
                        <p><?php echo $filter === 'unread' ? 'No unread notifications' : 'You have no notifications yet'; ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
