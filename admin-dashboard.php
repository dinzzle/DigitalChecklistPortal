<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is admin
checkAdminAccess();

$user = $_SESSION['user'];

// Get statistics (same as before)
try {
    $stmtUsers = $pdo->query("SELECT COUNT(*) as count FROM users");
    $totalUsers = $stmtUsers->fetch()['count'];
    
    $stmtActive = $pdo->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $activeUsers = $stmtActive->fetch()['count'];
    
    $stmt5s = $pdo->query("SELECT COUNT(*) as count FROM checklists WHERE type = '5s'");
    $count5S = $stmt5s->fetch()['count'];
    
    $stmtAM = $pdo->query("SELECT COUNT(*) as count FROM checklists WHERE type = 'am'");
    $countAM = $stmtAM->fetch()['count'];
    
    $today = date('Y-m-d');
    $stmtToday = $pdo->prepare("SELECT COUNT(*) as count FROM checklists WHERE DATE(created_at) = ?");
    $stmtToday->execute([$today]);
    $countToday = $stmtToday->fetch()['count'];
    
    $stmtTotal = $pdo->query("SELECT COUNT(*) as count FROM checklists");
    $countTotal = $stmtTotal->fetch()['count'];
    
    $stmtActivities = $pdo->query("
        SELECT c.*, u.name as employee_name, u.department 
        FROM checklists c 
        JOIN users u ON c.user_id = u.id 
        ORDER BY c.created_at DESC 
        LIMIT 5
    ");
    $activities = $stmtActivities->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $totalUsers = $activeUsers = $count5S = $countAM = $countToday = $countTotal = 0;
    $activities = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Digital Checklist System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-tertiary: #64748b;
            --accent-blue: #3b82f6;
            --accent-green: #10b981;
            --accent-orange: #f97316;
            --accent-purple: #8b5cf6;
            --accent-pink: #ec4899;
            --accent-indigo: #6366f1;
            --border-color: #334155;
            --shadow-color: rgba(0, 0, 0, 0.3);
            --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            --hover-shadow: 0 12px 48px rgba(0, 0, 0, 0.3);
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* Sidebar - Dark Mode */
        .sidebar {
            width: 260px;
            background-color: var(--bg-secondary);
            padding: 25px 0;
            position: fixed;
            height: 100%;
            overflow-y: auto;
            border-right: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
            z-index: 100;
        }

        .logo {
            padding: 0 25px 25px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 25px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .logo-img {
            width: 150px;
            height: 60px;
            object-fit: contain;
            margin-bottom: 12px;
            filter: brightness(0) invert(1); /* Makes logo white */
            transition: all 0.3s ease;
        }

        .logo-img:hover {
            transform: scale(1.05);
            filter: brightness(0) invert(1) drop-shadow(0 0 8px rgba(59, 130, 246, 0.5));
        }

        .logo p {
            font-size: 13px;
            color: var(--text-secondary);
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .admin-info {
            padding: 0 25px 25px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 25px;
        }

        .admin-details h3 {
            font-size: 16px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-primary);
        }

        .admin-badge {
            background: linear-gradient(135deg, var(--accent-orange), #ea580c);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .admin-details p {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .nav-menu {
            padding: 0 15px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            margin-bottom: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        .nav-item:hover {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            border-color: var(--accent-blue);
            transform: translateX(5px);
        }

        .nav-item.active {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-indigo));
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-item i {
            margin-right: 15px;
            width: 20px;
            font-size: 18px;
        }

        .switch-user {
            padding: 25px 15px;
            margin-top: auto;
            border-top: 1px solid var(--border-color);
        }

        /* Main Content - Dark Mode */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 40px;
            background-color: var(--bg-primary);
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--border-color);
        }

        .header h2 {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--text-primary), var(--text-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .date-display {
            background: var(--bg-secondary);
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 15px;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
        }

        .logout-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2);
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        }

        /* Stats Cards - Dark Mode */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: linear-gradient(145deg, var(--bg-secondary), var(--bg-tertiary));
            border-radius: 16px;
            padding: 30px;
            display: flex;
            align-items: center;
            gap: 25px;
            box-shadow: var(--card-shadow);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-blue), transparent);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--hover-shadow);
            border-color: var(--accent-blue);
        }

        .stat-icon {
            width: 80px;
            height: 80px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }

        .stat-icon::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
        }

        .stat-icon.icon-users {
            background: linear-gradient(135deg, var(--accent-blue), #1d4ed8);
        }

        .stat-icon.icon-active {
            background: linear-gradient(135deg, var(--accent-green), #047857);
        }

        .stat-icon.icon-5s {
            background: linear-gradient(135deg, var(--accent-orange), #c2410c);
        }

        .stat-icon.icon-am {
            background: linear-gradient(135deg, var(--accent-purple), #7c3aed);
        }

        .stat-icon.icon-today {
            background: linear-gradient(135deg, var(--accent-pink), #be185d);
        }

        .stat-icon.icon-total {
            background: linear-gradient(135deg, var(--accent-indigo), #4f46e5);
        }

        .stat-content h3 {
            font-size: 42px;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 800;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .stat-content p {
            color: var(--text-secondary);
            font-size: 15px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        /* Quick Actions - Dark Mode */
        .quick-actions {
            background: linear-gradient(145deg, var(--bg-secondary), var(--bg-tertiary));
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 20px;
            margin-bottom: 25px;
            color: var(--text-primary);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: var(--accent-blue);
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }

        .action-btn {
            background: var(--bg-tertiary);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            text-decoration: none;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .action-btn:hover {
            background: var(--bg-secondary);
            border-color: var(--accent-blue);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            color: var(--text-primary);
        }

        .action-btn:hover::before {
            left: 100%;
        }

        .action-btn i {
            font-size: 36px;
            margin-bottom: 20px;
            color: var(--accent-blue);
        }

        .action-btn h4 {
            font-size: 18px;
            margin-bottom: 10px;
            color: var(--text-primary);
            font-weight: 600;
        }

        .action-btn p {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        /* Recent Activity - Dark Mode */
        .recent-activity {
            background: linear-gradient(145deg, var(--bg-secondary), var(--bg-tertiary));
            border-radius: 16px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
        }

        .activity-list {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 15px;
        }

        .activity-list::-webkit-scrollbar {
            width: 8px;
        }

        .activity-list::-webkit-scrollbar-track {
            background: var(--bg-tertiary);
            border-radius: 4px;
        }

        .activity-list::-webkit-scrollbar-thumb {
            background: var(--accent-blue);
            border-radius: 4px;
        }

        .activity-list::-webkit-scrollbar-thumb:hover {
            background: #2563eb;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 20px;
            margin-bottom: 12px;
            background: var(--bg-tertiary);
            border-radius: 12px;
            border-left: 4px solid var(--accent-blue);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .activity-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.02), transparent);
            transform: translateX(-100%);
        }

        .activity-item:hover {
            background: var(--bg-secondary);
            transform: translateX(10px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .activity-item:hover::before {
            transform: translateX(100%);
            transition: transform 0.6s ease;
        }

        .activity-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
            margin-right: 20px;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }

        .activity-icon::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
        }

        .activity-details {
            flex: 1;
        }

        .activity-details h4 {
            font-size: 16px;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 600;
        }

        .activity-details p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .activity-time {
            text-align: right;
            font-size: 13px;
            color: var(--text-tertiary);
            min-width: 90px;
            background: rgba(0,0,0,0.2);
            padding: 8px 12px;
            border-radius: 8px;
            margin-left: 10px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            color: var(--text-tertiary);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 220px;
            }
            
            .main-content {
                margin-left: 220px;
                padding: 30px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                padding: 20px 0;
            }
            
            .logo-img {
                width: 40px;
                height: 40px;
                margin-bottom: 5px;
            }
            
            .logo p, .admin-details, .nav-item span, 
            .switch-user .nav-item span {
                display: none;
            }
            
            .logo {
                padding: 0 15px 20px;
                text-align: center;
            }
            
            .nav-item {
                padding: 15px;
                justify-content: center;
            }
            
            .nav-item i {
                margin-right: 0;
                font-size: 20px;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .stat-card {
                padding: 25px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
            
            .header h2 {
                font-size: 24px;
            }
            
            .stat-card {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .stat-icon {
                width: 70px;
                height: 70px;
            }
            
            .activity-item {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .activity-icon {
                margin-right: 0;
            }
            
            .activity-time {
                text-align: center;
                margin-left: 0;
                width: 100%;
            }
        }

        /* Animation for stats */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card, .quick-actions, .recent-activity {
            animation: fadeInUp 0.5s ease forwards;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .stat-card:nth-child(5) { animation-delay: 0.5s; }
        .stat-card:nth-child(6) { animation-delay: 0.6s; }

        /* Alternative logo styling for dark background logos */
        .logo-img.dark-mode-logo {
            filter: brightness(1) invert(0); /* Keep original colors */
        }
        
        .logo-img.dark-mode-logo:hover {
            filter: brightness(1.1) invert(0) drop-shadow(0 0 8px rgba(59, 130, 246, 0.5));
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <!-- Replace with your boschlogo.jpg image -->
            <img src="boschlogo.jpg" alt="Bosch Logo" class="logo-img" id="boschLogo">
            <p>Admin Panel</p>
        </div>
        
        <div class="admin-info">
            <div class="admin-details">
                <h3>
                    <?php echo htmlspecialchars($user['name']); ?> 
                    <span class="admin-badge">ADMIN</span>
                </h3>
                <p>System Administrator</p>
            </div>
        </div>
        
        <div class="nav-menu">
            <a href="admin-dashboard.php" class="nav-item active">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="admin-users.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>User Management</span>
            </a>
            <a href="admin-reports.php" class="nav-item">
                <i class="fas fa-chart-bar"></i>
                <span>Reports & Analytics</span>
            </a>
        </div>
        
        <div class="switch-user">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-exchange-alt"></i>
                <span>Switch to User View</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h2>Admin Dashboard</h2>
            <div class="header-actions">
                <div class="date-display" id="currentDate">
                    <?php echo date('l, F j, Y'); ?>
                </div>
                <button class="logout-btn" onclick="window.location.href='logout.php'">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card" onclick="window.location.href='admin-users.php'">
                <div class="stat-icon icon-users">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $totalUsers; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            
            <div class="stat-card" onclick="window.location.href='admin-users.php?status=active'">
                <div class="stat-icon icon-active">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $activeUsers; ?></h3>
                    <p>Active Users</p>
                </div>
            </div>
            
            <div class="stat-card" onclick="window.location.href='admin-reports.php?type=5s'">
                <div class="stat-icon icon-5s">
                    <i class="fas fa-broom"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $count5S; ?></h3>
                    <p>5S Checklists</p>
                </div>
            </div>
            
            <div class="stat-card" onclick="window.location.href='admin-reports.php?type=am'">
                <div class="stat-icon icon-am">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $countAM; ?></h3>
                    <p>AM Checklists</p>
                </div>
            </div>
            
            <div class="stat-card" onclick="window.location.href='admin-reports.php?period=today'">
                <div class="stat-icon icon-today">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $countToday; ?></h3>
                    <p>Today's Checklists</p>
                </div>
            </div>
            
            <div class="stat-card" onclick="window.location.href='admin-reports.php'">
                <div class="stat-icon icon-total">
                    <i class="fas fa-list-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $countTotal; ?></h3>
                    <p>Total Checklists</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3 class="section-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
            <div class="action-buttons">
                <a href="admin-users.php?action=add" class="action-btn">
                    <i class="fas fa-user-plus"></i>
                    <h4>Add New User</h4>
                    <p>Create new user account</p>
                </a>
                
                <a href="admin-reports.php" class="action-btn">
                    <i class="fas fa-chart-pie"></i>
                    <h4>Generate Report</h4>
                    <p>Create detailed reports</p>
                </a>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="recent-activity">
            <h3 class="section-title"><i class="fas fa-history"></i> Recent Activity</h3>
            <div class="activity-list" id="recentActivity">
                <?php if (empty($activities)): ?>
                    <div class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <h4>No recent activity</h4>
                        <p>No checklists have been submitted yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon" style="background: <?php echo $activity['type'] === '5s' ? 'linear-gradient(135deg, #f97316, #c2410c)' : 'linear-gradient(135deg, #8b5cf6, #7c3aed)'; ?>;">
                            <i class="fas <?php echo $activity['type'] === '5s' ? 'fa-broom' : 'fa-tools'; ?>"></i>
                        </div>
                        <div class="activity-details">
                            <h4><?php echo $activity['type'] === '5s' ? '5S' : 'AM'; ?> Checklist Submitted</h4>
                            <p>
                                <?php echo htmlspecialchars($activity['employee_name']); ?> - 
                                <?php echo htmlspecialchars($activity['department']); ?>
                            </p>
                        </div>
                        <div class="activity-time">
                            <?php echo date('H:i', strtotime($activity['created_at'])); ?><br>
                            <?php echo date('M d', strtotime($activity['created_at'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);

        // Add click animations
        document.querySelectorAll('.stat-card, .action-btn').forEach(item => {
            item.addEventListener('click', function(e) {
                if (this.getAttribute('href')) {
                    e.preventDefault();
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        window.location.href = this.getAttribute('href');
                    }, 150);
                }
            });
        });

        // Update date display with live time
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            document.getElementById('currentDate').textContent = 
                now.toLocaleDateString('en-US', options);
        }

        // Update time every second
        setInterval(updateDateTime, 1000);
        updateDateTime(); // Initial call

        // Check if logo needs dark mode adjustment
        document.addEventListener('DOMContentLoaded', function() {
            const logo = document.getElementById('boschLogo');
            // If logo has a dark background, use the alternative class
            // You can adjust this based on your actual logo
            if (logo.src.includes('boschlogo.jpg')) {
                // You can add a class here if needed
                // logo.classList.add('dark-mode-logo');
            }
        });
    </script>
</body>
</html>