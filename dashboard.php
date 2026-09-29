<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is logged in
checkAuth();

$user = $_SESSION['user'];

// Get statistics
try {
    // 5S Checklists count
    $stmt5s = $pdo->prepare("SELECT COUNT(*) as count FROM checklists WHERE type = '5s' AND user_id = ?");
    $stmt5s->execute([$user['id']]);
    $count5S = $stmt5s->fetch()['count'];
    
    // AM Checklists count
    $stmtAM = $pdo->prepare("SELECT COUNT(*) as count FROM checklists WHERE type = 'am' AND user_id = ?");
    $stmtAM->execute([$user['id']]);
    $countAM = $stmtAM->fetch()['count'];
    
    // Today's checklists
    $today = date('Y-m-d');
    $stmtToday = $pdo->prepare("SELECT COUNT(*) as count FROM checklists WHERE DATE(created_at) = ? AND user_id = ?");
    $stmtToday->execute([$today, $user['id']]);
    $countToday = $stmtToday->fetch()['count'];
    
    // Total checklists
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) as count FROM checklists WHERE user_id = ?");
    $stmtTotal->execute([$user['id']]);
    $countTotal = $stmtTotal->fetch()['count'];
    
    // Recent activities (last 5 checklists)
    $stmtActivities = $pdo->prepare("
        SELECT c.*, u.name as employee_name 
        FROM checklists c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.user_id = ? 
        ORDER BY c.created_at DESC 
        LIMIT 5
    ");
    $stmtActivities->execute([$user['id']]);
    $activities = $stmtActivities->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $count5S = $countAM = $countToday = $countTotal = 0;
    $activities = [];
}

// Determine greeting based on time
$hour = date('H');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 17) $greeting = 'Good afternoon';
else $greeting = 'Good evening';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Digital Checklist System</title>
    <style>
        /* ... (Keep all CSS from original dashboard.html) ... */
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

        .main-content {
            flex: 1;
            margin-left: 220px;
            padding: 20px;
        }

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

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 6px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
        }

        .stat-content h3 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .stat-content p {
            color: #718096;
            font-size: 14px;
        }

        .icon-5s { background-color: #4299e1; }
        .icon-am { background-color: #48bb78; }
        .icon-today { background-color: #ed8936; }
        .icon-total { background-color: #718096; }

        .quick-actions {
            background: white;
            border-radius: 6px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section-title {
            color: #1a365d;
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .info-btn {
            background: none;
            border: none;
            color: #4299e1;
            cursor: pointer;
            font-size: 16px;
            padding: 5px;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }

        .info-btn:hover {
            background-color: #f0f8ff;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }

        .action-btn {
            background-color: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            border-color: #1a365d;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .action-btn i {
            font-size: 28px;
            color: #1a365d;
            margin-bottom: 10px;
        }

        .action-btn h4 {
            margin-bottom: 5px;
            color: #1a365d;
        }

        .action-btn p {
            font-size: 13px;
            color: #718096;
        }

        .recent-activity {
            background: white;
            border-radius: 6px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .activity-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
        }

        .activity-details {
            flex: 1;
        }

        .activity-details h4 {
            margin-bottom: 3px;
            font-size: 14px;
        }

        .activity-details p {
            color: #718096;
            font-size: 13px;
        }

        .activity-time {
            font-size: 12px;
            color: #718096;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
                padding: 10px 0;
            }
            
            .logo h1, .logo p, .user-details, .nav-item span {
                display: none;
            }
            
            .logo img {
                max-height: 40px;
                margin-bottom: 0;
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
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }

        /* Message styles */
        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .success-message {
            background-color: #c6f6d5;
            color: #276749;
            border: 1px solid #9ae6b4;
        }

        .error-message {
            background-color: #fed7d7;
            color: #9b2c2c;
            border: 1px solid #fc8181;
        }

        /* Modal Styles - ADDED FOR INFO BUTTON */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 800px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #718096;
            background: none;
            border: none;
        }

        .close-modal:hover {
            color: #1a365d;
        }

        .info-section {
            margin-bottom: 25px;
        }

        .info-section:last-child {
            margin-bottom: 0;
        }

        .info-section h3 {
            color: #1a365d;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #e2e8f0;
        }

        .info-points {
            list-style-type: none;
            padding-left: 0;
        }

        .info-points li {
            padding: 8px 0;
            padding-left: 25px;
            position: relative;
        }

        .info-points li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #48bb78;
            font-weight: bold;
        }

        .info-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 8px;
            margin-bottom: 5px;
        }

        .badge-5s { background-color: #4299e1; color: white; }
        .badge-am { background-color: #48bb78; color: white; }
        .badge-tip { background-color: #ed8936; color: white; }
        .badge-important { background-color: #e53e3e; color: white; }

        .step-item {
            margin-bottom: 15px;
            padding: 15px;
            background-color: #f7fafc;
            border-radius: 6px;
            border-left: 4px solid #4299e1;
        }

        .step-title {
            font-weight: bold;
            color: #1a365d;
            margin-bottom: 5px;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <!-- Updated: Replaced BOSCH text with logo image -->
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
            <a href="dashboard.php" class="nav-item active">
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
            <a href="history.php" class="nav-item">
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
            <h2><?php echo $greeting . ', ' . htmlspecialchars($user['name']) . '!'; ?></h2>
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                Logout
            </button>
        </div>

        <!-- Display messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon icon-5s">
                    <i class="fas fa-broom"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $count5S; ?></h3>
                    <p>5S Checklists</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon icon-am">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $countAM; ?></h3>
                    <p>AM Checklists</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon icon-today">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $countToday; ?></h3>
                    <p>Today's Checklists</p>
                </div>
            </div>
            
            <div class="stat-card">
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
            <h3 class="section-title">
                Quick Actions
                <button class="info-btn" onclick="showInfoModal()" title="Click for checklist information">
                    <i class="fas fa-info-circle"></i>
                </button>
            </h3>
            <div class="action-buttons">
                <a href="5s-checklist.php" class="action-btn">
                    <i class="fas fa-broom"></i>
                    <h4>5S CHECKLIST</h4>
                    <p>Begin a new 5S checklist</p>
                </a>
                
                <a href="am-checklist.php" class="action-btn">
                    <i class="fas fa-tools"></i>
                    <h4>AM CHECKLIST</h4>
                    <p>Perform equipment check</p>
                </a>
                
                <a href="history.php" class="action-btn">
                    <i class="fas fa-history"></i>
                    <h4>View History</h4>
                    <p>Check completed checklists</p>
                </a>
                
                <?php if ($user['role'] === 'admin'): ?>
                <a href="admin-dashboard.php" class="action-btn">
                    <i class="fas fa-user-shield"></i>
                    <h4>Admin Panel</h4>
                    <p>Manage system settings</p>
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="recent-activity">
            <h3 class="section-title">Recent Activity</h3>
            <div class="activity-list">
                <?php if (empty($activities)): ?>
                    <div class="activity-item">
                        <div class="activity-icon" style="background-color: #4299e1;">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="activity-details">
                            <h4>No recent activity</h4>
                            <p>Submit your first checklist to see activity here</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon" style="background-color: <?php echo $activity['type'] === '5s' ? '#4299e1' : '#48bb78'; ?>;">
                            <i class="fas <?php echo $activity['type'] === '5s' ? 'fa-broom' : 'fa-tools'; ?>"></i>
                        </div>
                        <div class="activity-details">
                            <h4><?php echo $activity['type'] === '5s' ? '5S Checklist' : 'AM Checklist'; ?> Completed</h4>
                            <p>
                                <?php 
                                $location = $activity['line'] ?? $activity['zone'] ?? 'Unknown location';
                                echo htmlspecialchars($location) . ' - ' . htmlspecialchars($activity['shift'] ?? 'N/A'); 
                                ?>
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

    <!-- Info Modal - ADDED HERE -->
    <div id="infoModal" class="modal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeInfoModal()">&times;</button>
            <h2>Checklist Information & Guidelines</h2>
            
            <div class="info-section">
                <h3><span class="info-badge badge-5s">5S</span> SMT MACHINE - 5S CHECKLIST GUIDELINES</h3>
                <p>Complete this checklist for workplace organization and cleanliness in SMT production area.</p>
                
                <div class="step-item">
                    <div class="step-title">Required Information</div>
                    <p>Before starting, ensure you have filled in all required fields:</p>
                    <ul class="info-points">
                        <li><strong>Date:</strong> Current date of inspection</li>
                        <li><strong>Shift:</strong> Your working shift (e.g., Morning, Evening, Night)</li>
                        <li><strong>Line No.:</strong> Production line number being inspected</li>
                        <li><strong>Zone:</strong> Specific area/zone of the SMT machine</li>
                        <li><strong>Employee Name & ID:</strong> Your identification details</li>
                    </ul>
                </div>
                
                <div class="step-item">
                    <div class="step-title">OVEN AREA - Automated Optical Inspection (AOI)</div>
                    <p><strong>1. Check PCBs for defects:</strong></p>
                    <ul class="info-points">
                        <li><strong>Yes:</strong> PCBs are being inspected properly for defects</li>
                        <li><strong>No:</strong> PCB defect checking is not happening or equipment is faulty</li>
                        <li><strong>N/A:</strong> Not applicable if oven area is not in use</li>
                    </ul>
                </div>
                
                <div class="step-item">
                    <div class="step-title">ZONE A - Magazine Loader</div>
                    <p><strong>1. Is the area clean and free from debris?</strong></p>
                    <ul class="info-points">
                        <li><strong>Yes:</strong> Area is completely clean with no dust, dirt or debris</li>
                        <li><strong>No:</strong> Debris present, needs cleaning</li>
                        <li><strong>N/A:</strong> Magazine loader not in use</li>
                    </ul>
                    <p><strong>2. Are magazines properly organized and labeled?</strong></p>
                    <ul class="info-points">
                        <li><strong>Yes:</strong> All magazines are correctly placed and clearly labeled</li>
                        <li><strong>No:</strong> Magazines are disorganized or missing labels</li>
                        <li><strong>N/A:</strong> No magazines currently loaded</li>
                    </ul>
                </div>
                
                <div class="step-item">
                    <div class="step-title">ZONE B - Solder Paste Inspection</div>
                    <p><strong>1. Is the camera lens clean and free from smudges?</strong></p>
                    <ul class="info-points">
                        <li><strong>Yes:</strong> Camera lens is crystal clear with no marks</li>
                        <li><strong>No:</strong> Lens has smudges affecting inspection quality</li>
                        <li><strong>N/A:</strong> SPI machine not in operation</li>
                    </ul>
                </div>
                
                <div class="step-item">
                    <div class="step-title">PCB Destacker</div>
                    <p><strong>3. Is the destacker free from dust and contamination?</strong></p>
                    <ul class="info-points">
                        <li><strong>Yes:</strong> Destacker is clean and contamination-free</li>
                        <li><strong>No:</strong> Dust or contamination present on destacker</li>
                        <li><strong>N/A:</strong> Destacker not in use</li>
                    </ul>
                </div>
            </div>
            
            <div class="info-section">
                <h3><span class="info-badge badge-am">AM</span> SMT PRODUCTION LINE - AM CHECKLIST GUIDELINES</h3>
                <p>Autonomous Maintenance checklist for SMT machine preventive maintenance.</p>
                
                <div class="step-item">
                    <div class="step-title">Maintenance Information</div>
                    <p>Before starting maintenance tasks, fill in:</p>
                    <ul class="info-points">
                        <li><strong>Date:</strong> Current maintenance date (auto-filled as 06/02/2026)</li>
                        <li><strong>Shift:</strong> Your working shift during maintenance</li>
                        <li><strong>Employee Name:</strong> Person performing maintenance</li>
                        <li><strong>System Admin:</strong> Always shows as BOSCH-ADMIN-001</li>
                    </ul>
                </div>
                
                <div class="step-item">
                    <div class="step-title">T1-T5: Daily Maintenance Tasks</div>
                    
                    <p><strong>T1: Visual Inspection of SMT Machine</strong></p>
                    <ul class="info-points">
                        <li>Check entire machine for physical damage, cracks, or loose parts</li>
                        <li>Look for any abnormal conditions or warning lights</li>
                        <li>Verify all safety guards are in place</li>
                    </ul>
                    
                    <p><strong>T2: Check Magazine Loader Operation</strong></p>
                    <ul class="info-points">
                        <li>Verify magazines load smoothly without jamming</li>
                        <li>Check PCB alignment during loading process</li>
                        <li>Ensure loader sensors are functioning properly</li>
                    </ul>
                    
                    <p><strong>T3: PCB Destacker Verification</strong></p>
                    <ul class="info-points">
                        <li>Test destacker operation with sample PCB</li>
                        <li>Ensure no jams or misalignments occur</li>
                        <li>Check vacuum suction if applicable</li>
                    </ul>
                    
                    <p><strong>T4: PCB Cleaner Inspection</strong></p>
                    <ul class="info-points">
                        <li>Check cleaning solution levels in tanks</li>
                        <li>Inspect filters for clogging or replacement need</li>
                        <li>Verify cleaning brushes/rollers are in good condition</li>
                    </ul>
                    
                    <p><strong>T5: Conveyor System Check</strong></p>
                    <ul class="info-points">
                        <li>Verify conveyor belts move smoothly</li>
                        <li>Check alignment of all conveyor sections</li>
                        <li>Listen for abnormal noises during operation</li>
                    </ul>
                </div>
                
                <div class="step-item">
                    <div class="step-title">T6-T10: Weekly/Periodic Maintenance Tasks</div>
                    
                    <p><strong>T6: SPI Machine Calibration Check</strong></p>
                    <ul class="info-points">
                        <li>Verify solder paste inspection accuracy using calibration standards</li>
                        <li>Check camera calibration and focus</li>
                        <li>Validate measurement accuracy with test patterns</li>
                    </ul>
                    
                    <p><strong>T7: EKRA Printer Maintenance</strong></p>
                    <ul class="info-points">
                        <li>Check stencil alignment with PCB fiducials</li>
                        <li>Verify solder paste consistency and viscosity</li>
                        <li>Inspect squeegee blades for wear</li>
                    </ul>
                    
                    <p><strong>T8: Pick & Place Machine Verification</strong></p>
                    <ul class="info-points">
                        <li>Check component placement accuracy with vision system</li>
                        <li>Inspect nozzles for wear or clogging</li>
                        <li>Verify feeder operation and component alignment</li>
                    </ul>
                    
                    <p><strong>T9: Reflow Oven Temperature Profile</strong></p>
                    <ul class="info-points">
                        <li>Verify temperature zones are within specification</li>
                        <li>Check temperature profile accuracy with thermocouple test</li>
                        <li>Ensure conveyor speed matches profile requirements</li>
                    </ul>
                    
                    <p><strong>T10: Cooling System Check</strong></p>
                    <ul class="info-points">
                        <li>Inspect cooling fans for proper operation</li>
                        <li>Check temperature sensors accuracy</li>
                        <li>Verify cooling efficiency for PCBs exiting oven</li>
                    </ul>
                </div>
            </div>
            
            <div class="info-section">
                <h3><span class="info-badge badge-tip">Tips</span> CHECKLIST COMPLETION GUIDELINES</h3>
                <ul class="info-points">
                    <li><strong>Complete Before Shift Start:</strong> Perform 5S checklist at beginning of each shift</li>
                    <li><strong>AM Checklist Frequency:</strong> T1-T5 daily, T6-T10 weekly or as scheduled</li>
                    <li><strong>Be Thorough:</strong> Don't rush inspections - quality is more important than speed</li>
                    <li><strong>Document Issues:</strong> Take photos of any problems found for reporting</li>
                    <li><strong>Verify All Items:</strong> Ensure every checkbox is completed before submission</li>
                    <li><strong>Use N/A Appropriately:</strong> Only mark N/A if equipment is truly not in operation</li>
                </ul>
            </div>
            
            <div class="info-section">
                <h3><span class="info-badge badge-important">Important</span> SAFETY PROCEDURES</h3>
                <ul class="info-points">
                    <li><strong>Lockout-Tagout:</strong> Always follow LOTO procedures before maintenance</li>
                    <li><strong>PPE Required:</strong> Safety glasses, gloves, and ESD protection mandatory</li>
                    <li><strong>High Temperature Areas:</strong> Be cautious around reflow ovens and heated components</li>
                    <li><strong>Moving Parts:</strong> Ensure all motion has stopped before inspection</li>
                    <li><strong>Report Immediately:</strong> Report any safety concerns to supervisor immediately</li>
                    <li><strong>Emergency Stop:</strong> Know location and use of emergency stop buttons</li>
                </ul>
            </div>
            
            <div class="info-section">
                <h3><span class="info-badge badge-5s">Status</span> CHECKBOX INTERPRETATION</h3>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 10px;">
                    <div style="background: #c6f6d5; padding: 10px; border-radius: 6px;">
                        <strong>✓ Yes / Checked:</strong>
                        <p style="font-size: 12px; margin-top: 5px;">Item is satisfactory, clean, or functioning properly</p>
                    </div>
                    <div style="background: #fed7d7; padding: 10px; border-radius: 6px;">
                        <strong>✗ No / Unchecked:</strong>
                        <p style="font-size: 12px; margin-top: 5px;">Issue found, needs attention or corrective action</p>
                    </div>
                    <div style="background: #e2e8f0; padding: 10px; border-radius: 6px;">
                        <strong>N/A:</strong>
                        <p style="font-size: 12px; margin-top: 5px;">Not applicable - equipment not in use or not required</p>
                    </div>
                    <div style="background: #feebc8; padding: 10px; border-radius: 6px;">
                        <strong>Blank:</strong>
                        <p style="font-size: 12px; margin-top: 5px;">Item not yet inspected - complete before submission</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto refresh dashboard every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutes

        // Display notifications
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `message ${type}-message`;
            notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i> ${message}`;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '1000';
            notification.style.maxWidth = '300px';
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Modal Functions - ADDED FOR INFO BUTTON
        function showInfoModal() {
            document.getElementById('infoModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeInfoModal() {
            document.getElementById('infoModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('infoModal');
            if (event.target === modal) {
                closeInfoModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeInfoModal();
            }
        });
    </script>
</body>
</html>