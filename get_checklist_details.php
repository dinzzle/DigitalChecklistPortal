<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is logged in
checkAuth();

$user = $_SESSION['user'];
$checklistId = $_GET['id'] ?? 0;

try {
    // Get checklist details
    $stmt = $pdo->prepare("
        SELECT c.*, u.name as employee_name, u.employee_id, u.department 
        FROM checklists c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$checklistId]);
    $checklist = $stmt->fetch();
    
    // Check if user has permission to view
    if (!$checklist || ($user['role'] !== 'admin' && $checklist['user_id'] != $user['id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    
    // Get answers if it's a 5S checklist
    $answers = [];
    if ($checklist['type'] === '5s') {
        $stmtAnswers = $pdo->prepare("
            SELECT ca.*, cq.question_number, cq.question_text 
            FROM checklist_answers ca 
            JOIN checklist_questions cq ON ca.question_id = cq.id 
            WHERE ca.checklist_id = ?
            ORDER BY cq.question_number
        ");
        $stmtAnswers->execute([$checklistId]);
        $answers = $stmtAnswers->fetchAll();
    }
    
    // Get tasks if it's an AM checklist
    $tasks = [];
    if ($checklist['type'] === 'am') {
        $stmtTasks = $pdo->prepare("
            SELECT tc.*, ct.task_code, ct.task_title, ct.task_description 
            FROM task_completions tc 
            JOIN checklist_tasks ct ON tc.task_id = ct.id 
            WHERE tc.checklist_id = ?
            ORDER BY ct.task_code
        ");
        $stmtTasks->execute([$checklistId]);
        $tasks = $stmtTasks->fetchAll();
    }
    
    $checklist['answers'] = $answers;
    $checklist['tasks'] = $tasks;
    
    echo json_encode([
        'success' => true,
        'data' => $checklist
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching checklist details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>