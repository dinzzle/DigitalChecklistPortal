<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is admin
checkAdminAccess();

// Get filters
$dateRange = $_GET['date_range'] ?? '30days';
$checklistType = $_GET['type'] ?? 'all';
$department = $_GET['department'] ?? 'all';
$detailed = isset($_GET['detailed']);

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

// Build query
$query = "SELECT c.*, u.name as employee_name, u.employee_id, u.department, u.role as user_role 
          FROM checklists c 
          JOIN users u ON c.user_id = u.id 
          WHERE 1=1";
$params = [];

if ($dateFrom) {
    $query .= " AND DATE(c.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $query .= " AND DATE(c.created_at) <= ?";
    $params[] = $dateTo;
}

if ($checklistType !== 'all') {
    $query .= " AND c.type = ?";
    $params[] = $checklistType;
}

if ($department !== 'all') {
    $query .= " AND u.department = ?";
    $params[] = $department;
}

$query .= " ORDER BY c.created_at DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$checklists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=checklists_report_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
if ($detailed) {
    fputcsv($output, [
        'ID',
        'Checklist Type',
        'Date',
        'Time',
        'Shift',
        'Line/Zone',
        'Employee Name',
        'Employee ID',
        'Department',
        'User Role',
        'Total Questions',
        'Yes Answers',
        'No Answers',
        'N/A Answers',
        'Completed Tasks',
        'Total Tasks',
        'Completion Rate',
        'Status',
        'Created At',
        'Submitted At'
    ]);
} else {
    fputcsv($output, [
        'Date',
        'Type',
        'Employee',
        'Department',
        'Line/Zone',
        'Shift',
        'Status',
        'Yes Answers',
        'No Answers',
        'N/A Answers',
        'Completion Rate'
    ]);
}

// Add data rows
foreach ($checklists as $checklist) {
    if ($detailed) {
        fputcsv($output, [
            $checklist['id'],
            strtoupper($checklist['type']),
            date('Y-m-d', strtotime($checklist['created_at'])),
            date('H:i:s', strtotime($checklist['created_at'])),
            $checklist['shift'],
            $checklist['line'] ?? $checklist['zone'] ?? '',
            $checklist['employee_name'],
            $checklist['employee_id'],
            $checklist['department'],
            $checklist['user_role'],
            $checklist['total_questions'] ?? 0,
            $checklist['yes_answers'] ?? 0,
            $checklist['no_answers'] ?? 0,
            $checklist['na_answers'] ?? 0,
            $checklist['completed_tasks'] ?? 0,
            $checklist['total_tasks'] ?? 0,
            $checklist['completion_rate'] ?? 0,
            $checklist['status'],
            $checklist['created_at'],
            $checklist['submitted_at']
        ]);
    } else {
        fputcsv($output, [
            date('Y-m-d H:i', strtotime($checklist['created_at'])),
            strtoupper($checklist['type']),
            $checklist['employee_name'],
            $checklist['department'],
            $checklist['line'] ?? $checklist['zone'] ?? '',
            $checklist['shift'],
            $checklist['status'],
            $checklist['yes_answers'] ?? 0,
            $checklist['no_answers'] ?? 0,
            $checklist['na_answers'] ?? 0,
            $checklist['completion_rate'] ?? 0
        ]);
    }
}

fclose($output);
?>