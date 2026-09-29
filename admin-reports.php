<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is admin
checkAdminAccess();

$user = $_SESSION['user'];

// Date range filter
$dateRange = $_GET['date_range'] ?? '30days';
$checklistType = $_GET['type'] ?? 'all';
$department = $_GET['department'] ?? 'all';

// Calculate date range
$dateFrom = '';
$dateTo = date('Y-m-d');

switch ($dateRange) {
    case 'today':
        $dateFrom = $dateTo;
        break;
    case 'yesterday':
        $dateFrom = date('Y-m-d', strtotime('-1 day'));
        $dateTo = $dateFrom;
        break;
    case '7days':
        $dateFrom = date('Y-m-d', strtotime('-7 days'));
        break;
    case '30days':
        $dateFrom = date('Y-m-d', strtotime('-30 days'));
        break;
    case '90days':
        $dateFrom = date('Y-m-d', strtotime('-90 days'));
        break;
    case 'all':
        $dateFrom = '';
        break;
}

// Build query for statistics
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN type = '5s' THEN 1 ELSE 0 END) as count_5s,
    SUM(CASE WHEN type = 'am' THEN 1 ELSE 0 END) as count_am,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_count
    FROM checklists c 
    WHERE 1=1";
$statsParams = [];

if ($dateFrom) {
    $statsQuery .= " AND DATE(c.created_at) >= ?";
    $statsParams[] = $dateFrom;
}

if ($checklistType !== 'all') {
    $statsQuery .= " AND c.type = ?";
    $statsParams[] = $checklistType;
}

// Build query for checklist data
$checklistsQuery = "SELECT c.*, u.name as employee_name, u.department, u.employee_id
                    FROM checklists c 
                    JOIN users u ON c.user_id = u.id 
                    WHERE 1=1";
$checklistsParams = [];

if ($dateFrom) {
    $checklistsQuery .= " AND DATE(c.created_at) >= ?";
    $checklistsParams[] = $dateFrom;
}

if ($dateTo) {
    $checklistsQuery .= " AND DATE(c.created_at) <= ?";
    $checklistsParams[] = $dateTo;
}

if ($checklistType !== 'all') {
    $checklistsQuery .= " AND c.type = ?";
    $checklistsParams[] = $checklistType;
}

if ($department !== 'all') {
    $checklistsQuery .= " AND u.department = ?";
    $checklistsParams[] = $department;
}

$checklistsQuery .= " ORDER BY c.created_at DESC";

// Execute queries
$totalChecklists = $count5S = $countAM = $todayChecklists = 0;
$checklists = [];
$departments = [];
$departmentBreakdown = [];
$trendData = [];

try {
    // Get statistics
    if (!empty($statsParams)) {
        $stmtStats = $pdo->prepare($statsQuery);
        $stmtStats->execute($statsParams);
        $stats = $stmtStats->fetch();
    } else {
        $stmtStats = $pdo->query($statsQuery);
        $stats = $stmtStats->fetch();
    }
    
    $totalChecklists = $stats['total'] ?? 0;
    $count5S = $stats['count_5s'] ?? 0;
    $countAM = $stats['count_am'] ?? 0;
    $todayChecklists = $stats['today_count'] ?? 0;
    
    // Get checklists
    if (!empty($checklistsParams)) {
        $stmtChecklists = $pdo->prepare($checklistsQuery);
        $stmtChecklists->execute($checklistsParams);
        $checklists = $stmtChecklists->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtChecklists = $pdo->query($checklistsQuery);
        $checklists = $stmtChecklists->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get departments for filter
    $stmtDepts = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department");
    $departments = $stmtDepts->fetchAll(PDO::FETCH_COLUMN);
    
    // Get department breakdown
    $deptBreakdownQuery = "SELECT 
        u.department,
        COUNT(*) as total,
        SUM(CASE WHEN c.type = '5s' THEN 1 ELSE 0 END) as count_5s,
        SUM(CASE WHEN c.type = 'am' THEN 1 ELSE 0 END) as count_am
        FROM checklists c 
        JOIN users u ON c.user_id = u.id 
        WHERE 1=1";
    
    $deptParams = [];
    if ($dateFrom) {
        $deptBreakdownQuery .= " AND DATE(c.created_at) >= ?";
        $deptParams[] = $dateFrom;
    }
    if ($dateTo) {
        $deptBreakdownQuery .= " AND DATE(c.created_at) <= ?";
        $deptParams[] = $dateTo;
    }
    if ($checklistType !== 'all') {
        $deptBreakdownQuery .= " AND c.type = ?";
        $deptParams[] = $checklistType;
    }
    
    $deptBreakdownQuery .= " GROUP BY u.department ORDER BY total DESC";
    
    if (!empty($deptParams)) {
        $stmtDeptBreakdown = $pdo->prepare($deptBreakdownQuery);
        $stmtDeptBreakdown->execute($deptParams);
        $departmentBreakdown = $stmtDeptBreakdown->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtDeptBreakdown = $pdo->query($deptBreakdownQuery);
        $departmentBreakdown = $stmtDeptBreakdown->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get daily trend data
    $trendQuery = "SELECT 
        DATE(created_at) as date,
        COUNT(*) as count,
        SUM(CASE WHEN type = '5s' THEN 1 ELSE 0 END) as count_5s,
        SUM(CASE WHEN type = 'am' THEN 1 ELSE 0 END) as count_am
        FROM checklists 
        WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at) 
        ORDER BY date";
    
    $stmtTrend = $pdo->query($trendQuery);
    $trendData = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Reports error: " . $e->getMessage());
    $error = "Error loading report data: " . $e->getMessage();
}

// Function to export data
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="checklists_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');

    // Function to export data
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="checklists_report_' . date('Y-m-d_H-i') . '.csv"');
    
    // Add BOM for UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Header row
    fputcsv($output, [
        'ID',
        'Submission Date',
        'Submission Time', 
        'Type',
        'Employee Name', 
        'Department', 
        'Employee ID', 
        'Line/Zone', 
        'Shift', 
        'Status',
        'Total Questions/Tasks',
        'Completed',
        'Yes Answers',
        'No Answers',
        'N/A Answers'
    ]);
    
    // Data rows - Format date properly for Excel
    foreach ($checklists as $row) {
        $dateTime = new DateTime($row['created_at']);
        
        fputcsv($output, [
            $row['id'],
            $dateTime->format('Y-m-d'),  // Separate date in YYYY-MM-DD format
            $dateTime->format('H:i:s'),  // Separate time in HH:MM:SS format
            strtoupper($row['type']),
            $row['employee_name'],
            $row['department'],
            $row['employee_id'],
            $row['line'] ?? $row['zone'] ?? 'N/A',
            $row['shift'] ?? 'N/A',
            'Completed',
            $row['total_questions'] ?? $row['total_tasks'] ?? 0,
            $row['completed_tasks'] ?? 0,
            $row['yes_answers'] ?? 0,
            $row['no_answers'] ?? 0,
            $row['na_answers'] ?? 0
        ]);
    }
    
    fclose($output);
    exit;
}
    
    // Header row
    fputcsv($output, ['Date', 'Type', 'Employee Name', 'Department', 'Employee ID', 'Line/Zone', 'Shift', 'Status']);
    
    // Data rows
    foreach ($checklists as $row) {
        fputcsv($output, [
            $row['created_at'],
            strtoupper($row['type']),
            $row['employee_name'],
            $row['department'],
            $row['employee_id'],
            $row['line'] ?? $row['zone'] ?? 'N/A',
            $row['shift'] ?? 'N/A',
            'Completed'
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Admin</title>
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
            --accent-red: #ef4444;
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
            filter: brightness(0) invert(1);
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

        /* Reports Container */
        .reports-container {
            background: linear-gradient(145deg, var(--bg-secondary), var(--bg-tertiary));
            border-radius: 16px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
        }

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 700;
        }

        .section-title i {
            color: var(--accent-blue);
            margin-right: 10px;
        }

        /* Filters */
        .report-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            background: var(--bg-tertiary);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .filter-group {
            margin-bottom: 0;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .filter-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 15px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-select option {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-tertiary);
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
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
        }

        .stat-card-total::before { background: linear-gradient(90deg, var(--accent-blue), transparent); }
        .stat-card-today::before { background: linear-gradient(90deg, var(--accent-green), transparent); }
        .stat-card-5s::before { background: linear-gradient(90deg, var(--accent-orange), transparent); }
        .stat-card-am::before { background: linear-gradient(90deg, var(--accent-purple), transparent); }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
            border-color: var(--accent-blue);
        }

        .stat-card h3 {
            font-size: 36px;
            margin-bottom: 10px;
            color: var(--text-primary);
            font-weight: 800;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .stat-card p {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 5px;
            font-weight: 500;
        }

        .subtext {
            font-size: 12px;
            color: var(--text-tertiary);
            margin-top: 10px;
        }

        /* Summary Table */
        .summary-table-container {
            background: var(--bg-tertiary);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            font-size: 18px;
            color: var(--text-primary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .generate-btn {
            background: linear-gradient(135deg, var(--accent-blue), #1d4ed8);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
        }

        .generate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .summary-table thead {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-indigo));
        }

        .summary-table th {
            padding: 16px 20px;
            text-align: left;
            color: white;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid var(--border-color);
        }

        .summary-table td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 14px;
        }

        .summary-table tr {
            transition: all 0.3s ease;
            background: var(--bg-tertiary);
        }

        .summary-table tr:hover {
            background: var(--bg-secondary);
            transform: translateX(5px);
        }

        /* Badges */
        .type-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
            color: white;
        }

        .type-5s {
            background: linear-gradient(135deg, var(--accent-orange), #c2410c);
        }

        .type-am {
            background: linear-gradient(135deg, var(--accent-purple), #7c3aed);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
            color: white;
        }

        .status-completed {
            background: linear-gradient(135deg, var(--accent-green), #059669);
        }

        /* Department Breakdown */
        .department-breakdown {
            background: var(--bg-tertiary);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }

        .breakdown-title {
            font-size: 18px;
            margin-bottom: 20px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .breakdown-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 15px;
        }

        .breakdown-item {
            background: var(--bg-secondary);
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid var(--accent-blue);
            transition: all 0.3s ease;
        }

        .breakdown-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--card-shadow);
        }

        .breakdown-item h4 {
            font-size: 16px;
            margin-bottom: 12px;
            color: var(--text-primary);
        }

        .breakdown-item p {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        /* Charts Section - PERBAIKAN UTAMA */
        .charts-section {
            background: var(--bg-tertiary);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }

        .chart-container {
            background: var(--bg-secondary);
            border-radius: 10px;
            padding: 20px;
            border: 1px solid var(--border-color);
            height: 320px;
            position: relative;
            overflow: hidden;
        }

        .chart-title {
            font-size: 16px;
            margin-bottom: 20px;
            color: var(--text-primary);
            font-weight: 600;
            text-align: center;
            height: 24px;
        }

        /* Chart Wrapper untuk pie chart */
        .chart-wrapper {
            width: 100%;
            height: calc(100% - 24px);
            position: relative;
        }

        .chart-wrapper canvas {
            position: absolute;
            left: 0;
            top: 0;
            width: 100% !important;
            height: 100% !important;
            max-width: 100%;
            max-height: 100%;
        }

        /* Report Actions */
        .report-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid var(--border-color);
        }

        .action-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .export-btn {
            background: linear-gradient(135deg, var(--accent-blue), #1d4ed8);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }

        .print-btn {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .print-btn:hover {
            background: var(--bg-tertiary);
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: var(--text-tertiary);
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--text-secondary);
        }

        .empty-state p {
            font-size: 15px;
            color: var(--text-tertiary);
        }

        /* Error Message */
        .error-message {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
            color: #ef4444;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid rgba(239, 68, 68, 0.2);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        /* MODAL STYLES - DITAMBAHKAN */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal-content {
            background: var(--bg-secondary);
            border-radius: 16px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
            position: relative;
        }

        .modal-header {
            padding: 20px 30px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-tertiary);
            border-radius: 16px 16px 0 0;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 24px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        .close-btn:hover {
            color: var(--accent-red);
            background: rgba(239, 68, 68, 0.1);
        }

        .modal-body {
            padding: 30px;
        }

        /* Detail Item Styles */
        .detail-item {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-label {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 5px;
            font-weight: 500;
        }

        .detail-value {
            font-size: 16px;
            color: var(--text-primary);
        }

        .answers-container {
            margin-top: 10px;
        }

        .answer-item {
            background: var(--bg-tertiary);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .answer-item:hover {
            transform: translateX(5px);
            background: var(--bg-secondary);
        }

        .answer-item.completed {
            border-left-color: #48bb78;
        }

        .answer-title {
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-weight: 500;
        }

        .answer-status {
            font-size: 13px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                height: 300px;
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
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            
            .report-filters {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .breakdown-grid {
                grid-template-columns: 1fr;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                height: 280px;
            }
            
            .report-actions {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            
            .modal-content {
                width: 95%;
                max-height: 85vh;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
            
            .reports-container {
                padding: 20px;
            }
            
            .chart-container {
                height: 250px;
            }
            
            .summary-table {
                font-size: 12px;
            }
            
            .summary-table th,
            .summary-table td {
                padding: 12px;
            }
            
            .modal-body {
                padding: 20px;
            }
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-tertiary);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--accent-blue);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #2563eb;
        }

        /* Print Styles */
        @media print {
            .sidebar, .logout-btn, .generate-btn, .action-btn {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 20px !important;
            }
            
            body {
                background: white !important;
                color: black !important;
            }
            
            .reports-container {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
            }
            
            .stat-card, .breakdown-item, .chart-container {
                break-inside: avoid;
            }
            
            .modal {
                display: none !important;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img src="boschlogo.jpg" alt="Bosch Logo" class="logo-img">
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
            <a href="admin-dashboard.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="admin-users.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>User Management</span>
            </a>
            <a href="admin-reports.php" class="nav-item active">
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
            <h2>Checklist Reports & Analytics</h2>
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
        
        <!-- Reports Container -->
        <div class="reports-container">
            <div class="section-title">
                <div>
                    <i class="fas fa-chart-line"></i> Checklist Summary Report
                </div>
                <div style="font-size: 14px; color: var(--text-secondary);">
                    Last Updated: <?php echo date('H:i:s'); ?>
                </div>
            </div>
            
            <!-- Error Message -->
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <form method="GET" action="" id="filterForm">
                <div class="report-filters">
                    <div class="filter-group">
                        <label>Date Range</label>
                        <select class="filter-select" name="date_range" onchange="submitForm()">
                            <option value="all" <?php echo $dateRange === 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="yesterday" <?php echo $dateRange === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                            <option value="7days" <?php echo $dateRange === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="30days" <?php echo $dateRange === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="90days" <?php echo $dateRange === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Checklist Type</label>
                        <select class="filter-select" name="type" onchange="submitForm()">
                            <option value="all" <?php echo $checklistType === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="5s" <?php echo $checklistType === '5s' ? 'selected' : ''; ?>>5S Checklist</option>
                            <option value="am" <?php echo $checklistType === 'am' ? 'selected' : ''; ?>>AM Checklist</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Department</label>
                        <select class="filter-select" name="department" onchange="submitForm()">
                            <option value="all" <?php echo $department === 'all' ? 'selected' : ''; ?>>All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($dept)); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card stat-card-total">
                    <h3><?php echo $totalChecklists; ?></h3>
                    <p>Total Checklists</p>
                    <div class="subtext">Date range: <?php echo $dateFrom ? date('M d', strtotime($dateFrom)) . ' - ' . date('M d', strtotime($dateTo)) : 'All time'; ?></div>
                </div>
                
                <div class="stat-card stat-card-today">
                    <h3><?php echo $todayChecklists; ?></h3>
                    <p>Today's Checklists</p>
                    <div class="subtext">Submitted today</div>
                </div>
                
                <div class="stat-card stat-card-5s">
                    <h3><?php echo $count5S; ?></h3>
                    <p>5S Checklists</p>
                    <div class="subtext">Completed 5S audits</div>
                </div>
                
                <div class="stat-card stat-card-am">
                    <h3><?php echo $countAM; ?></h3>
                    <p>AM Checklists</p>
                    <div class="subtext">Completed maintenance checks</div>
                </div>
            </div>
            
            <!-- Checklist Summary Table -->
            <div class="summary-table-container">
                <div class="table-header">
                    <h3>
                        <i class="fas fa-list-alt"></i> All Submitted Checklists
                        <span style="font-size: 14px; color: var(--text-secondary); margin-left: 10px;">
                            (<?php echo count($checklists); ?> checklists)
                        </span>
                    </h3>
                    <button class="generate-btn" onclick="exportCSV()">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>
                
                <div style="overflow-x: auto;">
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Type</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Line/Zone</th>
                                <th>Shift</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($checklists)): ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state">
                                            <i class="fas fa-clipboard-list"></i>
                                            <h3>No checklists found</h3>
                                            <p>No checklists have been submitted yet</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($checklists as $checklist): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($checklist['created_at'])); ?></td>
                                    <td>
                                        <span class="type-badge type-<?php echo $checklist['type']; ?>">
                                            <?php echo strtoupper($checklist['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($checklist['employee_name']); ?><br>
                                        <small style="color: var(--text-tertiary);">ID: <?php echo htmlspecialchars($checklist['employee_id']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars(ucfirst($checklist['department'])); ?></td>
                                    <td><?php echo htmlspecialchars($checklist['line'] ?? $checklist['zone'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($checklist['shift'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge status-completed">Completed</span>
                                    </td>
                                    <td>
                                        <button class="generate-btn" style="padding: 6px 12px; font-size: 13px;" 
                                                onclick="viewDetails(<?php echo $checklist['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Department Breakdown -->
            <?php if (!empty($departmentBreakdown)): ?>
            <div class="department-breakdown">
                <h3 class="breakdown-title"><i class="fas fa-building"></i> Department Breakdown</h3>
                <div class="breakdown-grid">
                    <?php foreach ($departmentBreakdown as $dept): ?>
                    <div class="breakdown-item">
                        <h4><?php echo htmlspecialchars(ucfirst($dept['department'])); ?></h4>
                        <p><strong><?php echo $dept['total']; ?></strong> total checklists</p>
                        <p style="font-size: 12px; color: var(--accent-blue);">
                            <?php echo $dept['count_5s']; ?> 5S • <?php echo $dept['count_am']; ?> AM
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Charts Section -->
            <div class="charts-section">
                <h3 class="breakdown-title"><i class="fas fa-chart-bar"></i> Visual Analytics</h3>
                <div class="charts-grid">
                    <div class="chart-container">
                        <div class="chart-title">Daily Submission Trend (Last 30 Days)</div>
                        <div class="chart-wrapper">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-title">Checklist Type Distribution</div>
                        <div class="chart-wrapper">
                            <canvas id="typeDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Report Actions -->
            <div class="report-actions">
                <button class="action-btn export-btn" onclick="exportPDF()">
                    <i class="fas fa-file-pdf"></i> Export Full Report (PDF)
                </button>
                <button class="action-btn export-btn" onclick="exportDetailedCSV()">
                    <i class="fas fa-file-excel"></i> Export Detailed Data
                </button>
                <button class="action-btn print-btn" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>
    </div>

    <!-- DETAILS MODAL - DITAMBAHKAN -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">
                    <i class="fas fa-clipboard-list"></i> Checklist Details
                </h3>
                <button class="close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Detail content akan dimuat di sini -->
                <div class="empty-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <h3>Loading details...</h3>
                    <p>Please wait while we fetch the checklist details</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function submitForm() {
            document.getElementById('filterForm').submit();
        }
        
        // Prepare chart data
        const trendData = <?php echo json_encode($trendData); ?>;
        
        // Prepare labels and datasets
        const dates = trendData.map(item => new Date(item.date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'}));
        const totalCounts = trendData.map(item => item.count);
        const fiveSCounts = trendData.map(item => item.count_5s);
        const amCounts = trendData.map(item => item.count_am);
        
        // Create trend chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Total',
                        data: totalCounts,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2
                    },
                    {
                        label: '5S',
                        data: fiveSCounts,
                        borderColor: '#f97316',
                        backgroundColor: 'rgba(249, 115, 22, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2
                    },
                    {
                        label: 'AM',
                        data: amCounts,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: '#94a3b8',
                            font: {
                                size: 11
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: {
                                size: 10
                            },
                            maxRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        });
        
        // Create type distribution chart dengan perbaikan untuk container
        const typeCtx = document.getElementById('typeDistributionChart').getContext('2d');
        const typeChart = new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: ['5S Checklists', 'AM Checklists'],
                datasets: [{
                    data: [<?php echo $count5S; ?>, <?php echo $countAM; ?>],
                    backgroundColor: ['#f97316', '#10b981'],
                    borderWidth: 1,
                    borderColor: '#334155',
                    spacing: 5,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: '#94a3b8',
                            font: {
                                size: 11
                            },
                            padding: 15,
                            boxWidth: 12,
                            boxHeight: 12
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += context.parsed;
                                return label;
                            }
                        }
                    }
                },
                layout: {
                    padding: {
                        top: 10,
                        bottom: 10,
                        left: 10,
                        right: 10
                    }
                },
                cutout: '60%',
                radius: '90%'
            }
        });
        
        // Fungsi untuk resize chart dengan benar
        function resizeCharts() {
            try {
                trendChart.resize();
                typeChart.resize();
                
                // Force re-render untuk pie chart
                setTimeout(() => {
                    typeChart.update('none');
                }, 50);
            } catch(e) {
                console.log('Chart resize error:', e);
            }
        }
        
        // Fungsi viewDetails yang baru - DITAMBAHKAN
        function viewDetails(checklistId) {
            const modal = document.getElementById('detailModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            
            // Show loading state
            modalTitle.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            modalBody.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <h3>Loading checklist details</h3>
                    <p>Please wait while we fetch the details...</p>
                </div>
            `;
            modal.style.display = 'flex';
            
            // Fetch details via AJAX
            fetch(`get_checklist_details.php?id=${checklistId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showChecklistDetails(data.data);
                    } else {
                        showError('Error loading checklist details: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Error loading checklist details. Please try again.');
                });
        }
        
        function showChecklistDetails(checklist) {
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            
            let detailsHTML = `
                <div class="detail-item">
                    <div class="detail-label">Checklist Type</div>
                    <div class="detail-value">
                        <span class="type-badge type-${checklist.type}">
                            ${checklist.type === '5s' ? '5S CHECKLIST' : 'AM CHECKLIST'}
                        </span>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Title</div>
                    <div class="detail-value">${checklist.title || 'N/A'}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Date & Time</div>
                    <div class="detail-value">${new Date(checklist.created_at).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })}</div>
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
                            <div style="display: flex; gap: 20px; margin-top: 5px; flex-wrap: wrap;">
                                <span style="color: #48bb78; display: flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-check"></i> Yes: ${checklist.yes_answers || 0}
                                </span>
                                <span style="color: #e53e3e; display: flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-times"></i> No: ${checklist.no_answers || 0}
                                </span>
                                <span style="color: #718096; display: flex; align-items: center; gap: 5px;">
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
                                    ${answer.comment ? `<div style="margin-top: 5px; font-size: 12px; color: var(--text-tertiary);"><strong>Comment:</strong> ${answer.comment}</div>` : ''}
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
                            <div style="display: flex; gap: 20px; margin-top: 5px; flex-wrap: wrap;">
                                <span style="color: #48bb78; display: flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-check"></i> Completed: ${checklist.completed_tasks || 0}
                                </span>
                                <span style="color: #718096; display: flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-clock"></i> Pending: ${(checklist.total_tasks || 0) - (checklist.completed_tasks || 0)}
                                </span>
                                <span style="color: #4299e1; display: flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-tasks"></i> Total: ${checklist.total_tasks || 0}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
                
                if (checklist.tasks && checklist.tasks.length > 0) {
                    detailsHTML += `
                        <div class="detail-item">
                            <div class="detail-label">Detailed Tasks</div>
                            <div class="answers-container">
                    `;
                    
                    checklist.tasks.forEach(task => {
                        let statusColor = task.status === 'completed' ? '#48bb78' : '#718096';
                        let statusIcon = task.status === 'completed' ? 'fa-check' : 'fa-clock';
                        let statusText = task.status === 'completed' ? 'Completed' : 'Pending';
                        
                        detailsHTML += `
                            <div class="answer-item ${task.status === 'completed' ? 'completed' : ''}" 
                                 style="border-left-color: ${statusColor}">
                                <div class="answer-title">${task.task_number}. ${task.task_description}</div>
                                <div class="answer-status">
                                    <i class="fas ${statusIcon}" style="color: ${statusColor}"></i> ${statusText}
                                    ${task.notes ? `<div style="margin-top: 5px; font-size: 12px; color: var(--text-tertiary);"><strong>Notes:</strong> ${task.notes}</div>` : ''}
                                </div>
                            </div>
                        `;
                    });
                    
                    detailsHTML += `
                            </div>
                        </div>
                    `;
                }
            }
            
            modalTitle.innerHTML = `<i class="fas fa-clipboard-list"></i> ${checklist.type === '5s' ? '5S' : 'AM'} Checklist Details`;
            modalBody.innerHTML = detailsHTML;
        }
        
        function showError(message) {
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            
            modalTitle.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error';
            modalBody.innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> ${message}
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <button class="generate-btn" onclick="closeModal()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            `;
        }
        
        function closeModal() {
            const modal = document.getElementById('detailModal');
            modal.style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('detailModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
        
        function exportCSV() {
            const params = new URLSearchParams(window.location.search);
            window.open(`admin-reports.php?${params.toString()}&export=csv`, '_blank');
        }
        
        function exportDetailedCSV() {
            const params = new URLSearchParams(window.location.search);
            params.set('detailed', 'true');
            window.open(`admin-reports.php?${params.toString()}&export=csv`, '_blank');
        }
        
        function exportPDF() {
            // For now, use the browser's print to PDF feature
            window.print();
            // In production, you would call a backend PDF generation service
        }
        
        // Event listeners untuk resize
        window.addEventListener('resize', function() {
            resizeCharts();
        });
        
        // Inisialisasi setelah halaman load
        window.addEventListener('load', function() {
            setTimeout(() => {
                resizeCharts();
            }, 500);
        });
        
        // Auto-refresh page every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
        
        // Fix untuk chart yang belum ter-render dengan benar
        setTimeout(() => {
            if (typeChart) {
                typeChart.update();
                resizeCharts();
            }
        }, 1000);
    </script>
</body>
</html>