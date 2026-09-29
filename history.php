<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is logged in
checkAuth();

$user = $_SESSION['user'];

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $checklistId = $_POST['delete_id'];
    
    // Fetch the checklist to verify ownership/permissions
    $stmt = $pdo->prepare("SELECT * FROM checklists WHERE id = ?");
    $stmt->execute([$checklistId]);
    $checklist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($checklist) {
        // Check permissions - admin can delete any, users can only delete their own
        if ($user['role'] === 'admin' || $checklist['user_id'] == $user['id']) {
            try {
                $pdo->beginTransaction();
                
                // Delete the checklist
                $stmt = $pdo->prepare("DELETE FROM checklists WHERE id = ?");
                $stmt->execute([$checklistId]);
                
                $pdo->commit();
                
                // Set success message and redirect to avoid resubmission
                $_SESSION['success'] = 'Checklist deleted successfully!';
                header('Location: history.php?' . http_build_query($_GET));
                exit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error'] = 'Error deleting checklist: ' . $e->getMessage();
                header('Location: history.php?' . http_build_query($_GET));
                exit();
            }
        } else {
            $_SESSION['error'] = 'You do not have permission to delete this checklist';
            header('Location: history.php?' . http_build_query($_GET));
            exit();
        }
    } else {
        $_SESSION['error'] = 'Checklist not found';
        header('Location: history.php?' . http_build_query($_GET));
        exit();
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filters
$filterType = $_GET['type'] ?? 'all';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

// Build query
$query = "SELECT c.*, u.name as employee_name, u.department 
          FROM checklists c 
          JOIN users u ON c.user_id = u.id 
          WHERE 1=1";
$params = [];

// Apply user filter (non-admin only see their own)
if ($user['role'] !== 'admin') {
    $query .= " AND c.user_id = ?";
    $params[] = $user['id'];
}

// Apply type filter
if ($filterType !== 'all') {
    $query .= " AND c.type = ?";
    $params[] = $filterType;
}

// Apply date filters
if ($filterDateFrom) {
    $query .= " AND DATE(c.created_at) >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $query .= " AND DATE(c.created_at) <= ?";
    $params[] = $filterDateTo;
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM ($query) as total_query";
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($params);
$totalItems = $stmtCount->fetch()['total'];
$totalPages = ceil($totalItems / $limit);

// Apply sorting and pagination
$query .= " ORDER BY c.created_at DESC LIMIT $limit OFFSET $offset";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$checklists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN type = '5s' THEN 1 ELSE 0 END) as count_5s,
    SUM(CASE WHEN type = 'am' THEN 1 ELSE 0 END) as count_am
    FROM checklists c WHERE 1=1";
$statsParams = [];

if ($user['role'] !== 'admin') {
    $statsQuery .= " AND c.user_id = ?";
    $statsParams[] = $user['id'];
}
if ($filterType !== 'all') {
    $statsQuery .= " AND c.type = ?";
    $statsParams[] = $filterType;
}
if ($filterDateFrom) {
    $statsQuery .= " AND DATE(c.created_at) >= ?";
    $statsParams[] = $filterDateFrom;
}
if ($filterDateTo) {
    $statsQuery .= " AND DATE(c.created_at) <= ?";
    $statsParams[] = $filterDateTo;
}

$stmtStats = $pdo->prepare($statsQuery);
$stmtStats->execute($statsParams);
$stats = $stmtStats->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checklist History - Digital Checklist System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f0f2f5;
            color: #333;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 220px;
            background-color: #1a365d;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100%;
            overflow-y: auto;
        }

        .logo {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
            text-align: center;
        }

        .logo img {
            max-width: 100%;
            max-height: 60px;
            object-fit: contain;
            margin-bottom: 10px;
        }

        .logo h1 {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .logo p {
            font-size: 12px;
            color: #a0aec0;
            margin-top: 5px;
        }

        .user-info {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .user-details h3 {
            font-size: 16px;
            margin-bottom: 5px;
        }

        .user-details p {
            font-size: 12px;
            color: #a0aec0;
        }

        .nav-menu {
            padding: 0 10px;
        }

        .nav-item {
            display: block;
            padding: 12px 15px;
            margin-bottom: 5px;
            color: #cbd5e0;
            text-decoration: none;
            border-radius: 4px;
        }

        .nav-item:hover, .nav-item.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }

        .nav-item i {
            margin-right: 10px;
            width: 20px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 220px;
            padding: 20px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .header h2 {
            color: #1a365d;
            font-size: 24px;
        }

        .logout-btn {
            background-color: #1a365d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .logout-btn:hover {
            background-color: #2d3748;
        }

        /* Success/Error Messages */
        .message {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: fadeIn 0.3s ease-in;
        }

        .success {
            background: #48bb78;
            color: white;
        }

        .error {
            background: #e53e3e;
            color: white;
        }

        .message button {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 16px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Statistics */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.icon-5s {
            background: #48bb78;
            color: white;
        }

        .stat-icon.icon-am {
            background: #4299e1;
            color: white;
        }

        .stat-icon.icon-total {
            background: #1a365d;
            color: white;
        }

        .stat-content h3 {
            font-size: 28px;
            margin-bottom: 5px;
            color: #2d3748;
        }

        .stat-content p {
            color: #718096;
            font-size: 14px;
        }

        /* Filters */
        .filters-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .filters-title {
            font-size: 18px;
            margin-bottom: 20px;
            color: #1a365d;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            margin-bottom: 0;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            color: #4a5568;
            font-weight: 500;
        }

        .filter-select {
            width: 100%;
            padding: 8px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            font-size: 14px;
            background: white;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .filter-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn.apply {
            background: #48bb78;
            color: white;
        }

        .filter-btn.apply:hover {
            background: #38a169;
        }

        .filter-btn.reset {
            background: #718096;
            color: white;
        }

        .filter-btn.reset:hover {
            background: #5a6268;
        }

        /* History Table */
        .history-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .history-header h3 {
            font-size: 18px;
            color: #1a365d;
        }

        .export-btn {
            background: #1a365d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .export-btn:hover {
            background: #2d3748;
        }

        .table-container {
            overflow-x: auto;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
        }

        .history-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .history-table tr:hover {
            background: #f7fafc;
        }

        .checklist-type {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .type-5s {
            background: #48bb78;
            color: white;
        }

        .type-am {
            background: #4299e1;
            color: white;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-completed {
            background: #48bb78;
            color: white;
        }

        .view-btn, .delete-form {
            display: inline-block;
        }

        .view-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #1a365d;
            color: white;
            text-decoration: none;
        }

        .view-btn:hover {
            background: #2d3748;
        }

        .delete-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #e53e3e;
            color: white;
            margin-left: 5px;
        }

        .delete-btn:hover {
            background: #c53030;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            margin-top: 20px;
        }

        .pagination-btn {
            padding: 8px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            background: white;
            color: #4a5568;
            text-decoration: none;
            font-size: 14px;
        }

        .pagination-btn:hover {
            background: #f7fafc;
            border-color: #a0aec0;
        }

        .pagination-btn.active {
            background: #1a365d;
            color: white;
            border-color: #1a365d;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            color: #1a365d;
            margin: 0;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #718096;
        }

        .close-btn:hover {
            color: #1a365d;
        }

        .modal-body {
            padding: 20px;
        }

        .detail-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .detail-value {
            color: #2d3748;
        }

        .answers-container {
            margin-top: 10px;
        }

        .answer-item {
            padding: 10px;
            margin-bottom: 10px;
            border-left: 3px solid #718096;
            background: #f7fafc;
            border-radius: 4px;
        }

        .answer-item.completed {
            border-left-color: #48bb78;
        }

        .answer-title {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .answer-status {
            font-size: 13px;
            color: #718096;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
                padding: 10px 0;
            }
            
            .logo h1, .logo p, .user-details, .nav-item span {
                display: none;
            }
            
            .nav-item {
                padding: 12px;
                text-align: center;
            }
            
            .nav-item i {
                margin-right: 0;
            }
            
            .main-content {
                margin-left: 60px;
                padding: 15px;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .filter-row {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                justify-content: flex-start;
            }

            .history-table {
                font-size: 14px;
            }

            .history-table th,
            .history-table td {
                padding: 8px;
            }

            .view-btn, .delete-btn {
                padding: 4px 8px;
                font-size: 11px;
            }
        }

        /* Loading spinner */
        .fa-spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img src="boschlogo.jpg" alt="Bosch Logo">
            <p>MOE 1 Digital Checklist System</p>
        </div>
        
        <div class="user-info">
            <div class="user-details">
                <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                <p><?php echo htmlspecialchars(ucfirst($user['role'])) . ' | ' . htmlspecialchars(ucfirst($user['department'])); ?></p>
            </div>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="5s-checklist.php" class="nav-item">
                <i class="fas fa-broom"></i>
                <span>5S Checklist</span>
            </a>
            <a href="am-checklist.php" class="nav-item">
                <i class="fas fa-tools"></i>
                <span>AM Checklist</span>
            </a>
            <a href="history.php" class="nav-item active">
                <i class="fas fa-history"></i>
                <span>History</span>
            </a>
            <?php if ($user['role'] === 'admin'): ?>
            <a href="admin-dashboard.php" class="nav-item">
                <i class="fas fa-user-shield"></i>
                <span>Admin Panel</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h2>Checklist History</h2>
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                Logout
            </button>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="message success">
            <span>
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); ?>
            </span>
            <button onclick="this.parentElement.style.display='none'">
                &times;
            </button>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="message error">
            <span>
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); ?>
            </span>
            <button onclick="this.parentElement.style.display='none'">
                &times;
            </button>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon icon-5s">
                    <i class="fas fa-broom"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['count_5s'] ?? 0; ?></h3>
                    <p>5S Checklists</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon icon-am">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['count_am'] ?? 0; ?></h3>
                    <p>AM Checklists</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon icon-total">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total'] ?? 0; ?></h3>
                    <p>Total Checklists</p>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-container">
            <h3 class="filters-title">Filter History</h3>
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Checklist Type</label>
                        <select class="filter-select" name="type">
                            <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="5s" <?php echo $filterType === '5s' ? 'selected' : ''; ?>>5S Checklist</option>
                            <option value="am" <?php echo $filterType === 'am' ? 'selected' : ''; ?>>AM Checklist</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Date From</label>
                        <input type="date" class="filter-select" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>Date To</label>
                        <input type="date" class="filter-select" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="filter-btn apply">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <button type="button" class="filter-btn reset" onclick="window.location.href='history.php'">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- History Table -->
        <div class="history-container">
            <div class="history-header">
                <h3>Checklist History</h3>
                <div>
                    <button class="export-btn" onclick="exportData()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
            
            <div class="table-container">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Checklist Type</th>
                            <th>Shift</th>
                            <th>Line/Zone</th>
                            <th>Employee</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($checklists)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #718096;">
                                    <i class="fas fa-clipboard-list" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                                    <h3>No checklists found</h3>
                                    <p>Submit your first checklist to see it here!</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($checklists as $checklist): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($checklist['created_at'])); ?></td>
                                <td>
                                    <span class="checklist-type type-<?php echo $checklist['type']; ?>">
                                        <?php echo strtoupper($checklist['type']); ?> Checklist
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($checklist['shift'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($checklist['line'] ?? $checklist['zone'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($checklist['employee_name']); ?><br>
                                    <small style="color: #718096;"><?php echo htmlspecialchars($checklist['department']); ?></small>
                                </td>
                                <td>
                                    <span class="status-badge status-completed">Completed</span>
                                </td>
                                <td>
                                    <button class="view-btn" onclick="viewDetails(<?php echo $checklist['id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if ($user['role'] === 'admin' || $user['id'] == $checklist['user_id']): ?>
                                    <form method="POST" class="delete-form" style="display: inline;" 
                                          onsubmit="return confirm('Are you sure you want to delete this checklist?')">
                                        <input type="hidden" name="delete_id" value="<?php echo $checklist['id']; ?>">
                                        <!-- Preserve current filters -->
                                        <?php if ($filterType !== 'all'): ?>
                                        <input type="hidden" name="type" value="<?php echo $filterType; ?>">
                                        <?php endif; ?>
                                        <?php if ($filterDateFrom): ?>
                                        <input type="hidden" name="date_from" value="<?php echo $filterDateFrom; ?>">
                                        <?php endif; ?>
                                        <?php if ($filterDateTo): ?>
                                        <input type="hidden" name="date_to" value="<?php echo $filterDateTo; ?>">
                                        <?php endif; ?>
                                        <?php if ($page > 1): ?>
                                        <input type="hidden" name="page" value="<?php echo $page; ?>">
                                        <?php endif; ?>
                                        <button type="submit" class="delete-btn">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&type=<?php echo $filterType; ?>&date_from=<?php echo $filterDateFrom; ?>&date_to=<?php echo $filterDateTo; ?>" 
                       class="pagination-btn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                        <a href="?page=<?php echo $i; ?>&type=<?php echo $filterType; ?>&date_from=<?php echo $filterDateFrom; ?>&date_to=<?php echo $filterDateTo; ?>" 
                           class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                        <span class="pagination-btn">...</span>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&type=<?php echo $filterType; ?>&date_from=<?php echo $filterDateFrom; ?>&date_to=<?php echo $filterDateTo; ?>" 
                       class="pagination-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detail Modal -->
    <div id="detailModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Checklist Details</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                Loading...
            </div>
        </div>
    </div>

    <script>
        function viewDetails(checklistId) {
            // Fetch details via AJAX
            fetch(`get_checklist_details.php?id=${checklistId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showChecklistDetails(data.data);
                    } else {
                        alert('Error loading checklist details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading checklist details');
                });
        }

        function showChecklistDetails(checklist) {
            const modal = document.getElementById('detailModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            
            let detailsHTML = `
                <div class="detail-item">
                    <div class="detail-label">Checklist Type</div>
                    <div class="detail-value">${checklist.type === '5s' ? '5S Checklist' : 'AM Checklist'}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Title</div>
                    <div class="detail-value">${checklist.title || 'N/A'}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Date</div>
                    <div class="detail-value">${new Date(checklist.created_at).toLocaleDateString()}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Shift</div>
                    <div class="detail-value">${checklist.shift || 'N/A'}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Line/Zone</div>
                    <div class="detail-value">${checklist.line || checklist.zone || 'N/A'}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Employee</div>
                    <div class="detail-value">${checklist.employee_name} (${checklist.employee_id || 'N/A'})</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Department</div>
                    <div class="detail-value">${checklist.department || 'N/A'}</div>
                </div>
            `;
            
            if (checklist.type === '5s') {
                detailsHTML += `
                    <div class="detail-item">
                        <div class="detail-label">Answers Summary</div>
                        <div class="detail-value">
                            <div style="display: flex; gap: 10px; margin-top: 5px;">
                                <span style="color: #48bb78;">
                                    <i class="fas fa-check"></i> Yes: ${checklist.yes_answers || 0}
                                </span>
                                <span style="color: #e53e3e;">
                                    <i class="fas fa-times"></i> No: ${checklist.no_answers || 0}
                                </span>
                                <span style="color: #718096;">
                                    <i class="fas fa-ban"></i> N/A: ${checklist.na_answers || 0}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
                
                if (checklist.answers && checklist.answers.length > 0) {
                    detailsHTML += `
                        <div class="detail-item">
                            <div class="detail-label">Detailed Answers</div>
                            <div class="answers-container">
                    `;
                    
                    checklist.answers.forEach(answer => {
                        let statusColor = '#718096';
                        let statusIcon = 'fa-ban';
                        let statusText = 'N/A';
                        
                        if (answer.answer === 'yes') {
                            statusColor = '#48bb78';
                            statusIcon = 'fa-check';
                            statusText = 'Yes';
                        } else if (answer.answer === 'no') {
                            statusColor = '#e53e3e';
                            statusIcon = 'fa-times';
                            statusText = 'No';
                        }
                        
                        detailsHTML += `
                            <div class="answer-item ${answer.answer === 'yes' ? 'completed' : ''}" 
                                 style="border-left-color: ${statusColor}">
                                <div class="answer-title">${answer.question_number}. ${answer.question_text}</div>
                                <div class="answer-status">
                                    <i class="fas ${statusIcon}" style="color: ${statusColor}"></i> ${statusText}
                                    ${answer.comment ? `<br><strong>Comment:</strong> ${answer.comment}` : ''}
                                </div>
                            </div>
                        `;
                    });
                    
                    detailsHTML += `
                            </div>
                        </div>
                    `;
                }
            } else if (checklist.type === 'am') {
                detailsHTML += `
                    <div class="detail-item">
                        <div class="detail-label">Tasks Summary</div>
                        <div class="detail-value">
                            <div style="display: flex; gap: 10px; margin-top: 5px;">
                                <span style="color: #48bb78;">
                                    <i class="fas fa-check"></i> Completed: ${checklist.completed_tasks || 0}
                                </span>
                                <span style="color: #718096;">
                                    <i class="fas fa-clock"></i> Pending: ${checklist.total_tasks - checklist.completed_tasks || 0}
                                </span>
                                <span style="color: #4299e1;">
                                    <i class="fas fa-tasks"></i> Total: ${checklist.total_tasks || 0}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            modalTitle.textContent = `${checklist.type === '5s' ? '5S' : 'AM'} Checklist Details`;
            modalBody.innerHTML = detailsHTML;
            modal.style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('detailModal').style.display = 'none';
        }

        function exportData() {
            const params = new URLSearchParams(window.location.search);
            window.open(`export_history.php?${params.toString()}`, '_blank');
        }

        // Close modal when clicking outside
        document.getElementById('detailModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeModal();
            }
        });

        // Alternative JavaScript delete function if you prefer it over form submit
        function deleteChecklist(checklistId) {
            if (!confirm('Are you sure you want to delete this checklist? This action cannot be undone.')) {
                return;
            }
            
            // Create form data
            const formData = new FormData();
            formData.append('delete_id', checklistId);
            
            // Add current query parameters
            const params = new URLSearchParams(window.location.search);
            params.forEach((value, key) => {
                formData.append(key, value);
            });
            
            // Show loading on the delete button
            const deleteBtn = event.target;
            const originalHTML = deleteBtn.innerHTML;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            deleteBtn.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.redirected) {
                    window.location.href = response.url;
                } else {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting checklist');
                deleteBtn.innerHTML = originalHTML;
                deleteBtn.disabled = false;
            });
        }
    </script>
</body>
</html>