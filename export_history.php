<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is logged in
checkAuth();

$user = $_SESSION['user'];

// Build query
$query = "SELECT c.*, u.name as employee_name, u.employee_id, u.department 
          FROM checklists c 
          JOIN users u ON c.user_id = u.id 
          WHERE 1=1";
$params = [];

// Apply user filter (non-admin only see their own)
if ($user['role'] !== 'admin') {
    $query .= " AND c.user_id = ?";
    $params[] = $user['id'];
}

// Apply filters
$filterType = $_GET['type'] ?? 'all';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

if ($filterType !== 'all') {
    $query .= " AND c.type = ?";
    $params[] = $filterType;
}
if ($filterDateFrom) {
    $query .= " AND DATE(c.created_at) >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $query .= " AND DATE(c.created_at) <= ?";
    $params[] = $filterDateTo;
}

$query .= " ORDER BY c.created_at DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$checklists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=checklist_history_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'ID',
    'Type',
    'Date',
    'Time',
    'Shift',
    'Line/Zone',
    'Employee Name',
    'Employee ID',
    'Department',
    'Status',
    'Yes Answers',
    'No Answers',
    'N/A Answers',
    'Completed Tasks',
    'Total Tasks',
    'Completion Rate'
]);

// Add data rows
foreach ($checklists as $checklist) {
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
        $checklist['status'],
        $checklist['yes_answers'] ?? 0,
        $checklist['no_answers'] ?? 0,
        $checklist['na_answers'] ?? 0,
        $checklist['completed_tasks'] ?? 0,
        $checklist['total_tasks'] ?? 0,
        $checklist['completion_rate'] ?? 0
    ]);
}

fclose($output);
?>