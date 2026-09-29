<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is logged in
checkAuth();

$user = $_SESSION['user'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shift = $_POST['shift'] ?? '';
    $date = $_POST['date'] ?? date('Y-m-d');
    
    // Collect task completions
    $tasks = [];
    $completedTasks = 0;
    
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'task_') === 0) {
            $taskId = str_replace('task_', '', $key);
            $completed = ($value === 'on' || $value === '1');
            $tasks[$taskId] = $completed;
            
            if ($completed) {
                $completedTasks++;
            }
        }
    }
    
    $totalTasks = count($tasks);
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert checklist - REMOVED completion_rate
        $stmt = $pdo->prepare("INSERT INTO checklists (user_id, type, title, shift, date, 
                               total_tasks, completed_tasks, status, submitted_at) 
                               VALUES (?, 'am', 'SMT Production Line - AM Checklist', ?, ?, ?, ?, 'completed', NOW())");
        $stmt->execute([$user['id'], $shift, $date, $totalTasks, $completedTasks]);
        $checklistId = $pdo->lastInsertId();
        
        // Insert task completions
        $taskStmt = $pdo->prepare("INSERT INTO task_completions (checklist_id, task_id, completed, completed_at) 
                                   VALUES (?, ?, ?, ?)");
        
        foreach ($tasks as $taskId => $completed) {
            $taskStmt->execute([
                $checklistId, 
                $taskId, 
                $completed ? 1 : 0, 
                $completed ? date('Y-m-d H:i:s') : null
            ]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // REMOVED audit logging
        // logAuditEvent('checklist_submitted', 'AM checklist submitted', 'info', [
        //     'checklist_id' => $checklistId,
        //     'type' => 'am',
        //     'user_id' => $user['id']
        // ]);
        
        $_SESSION['success'] = "AM Checklist submitted successfully!";
        header("Location: dashboard.php");
        exit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error submitting checklist: " . $e->getMessage();
        header("Location: am-checklist.php");
        exit();
    }
}

// Get tasks from database
$tasks = [];
try {
    $stmt = $pdo->query("SELECT * FROM checklist_tasks WHERE checklist_type = 'am' AND is_active = 1 ORDER BY task_group, task_code");
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching tasks: " . $e->getMessage());
    $_SESSION['error'] = "Error loading checklist tasks";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AM Checklist - Digital Checklist System</title>
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

        .form-section {
            background: white;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 20px;
            margin-bottom: 20px;
            color: #1a365d;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .form-field {
            margin-bottom: 15px;
        }

        .form-field label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            color: #4a5568;
            font-weight: 500;
        }

        .form-field input,
        .form-field select {
            width: 100%;
            padding: 8px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            font-size: 14px;
            background: white;
        }

        .task-groups {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .task-group {
            background: white;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .task-group-header {
            background: #f7fafc;
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 16px;
            font-weight: 600;
            color: #1a365d;
            display: flex;
            align-items: center;
        }

        .task-group-header i {
            margin-right: 10px;
            color: #1a365d;
        }

        .task-group-body {
            padding: 15px;
        }

        .task-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .task-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .task-checkbox {
            margin-right: 15px;
            margin-top: 3px;
        }

        .task-checkbox input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .task-content {
            flex: 1;
        }

        .task-title {
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
        }

        .task-description {
            font-size: 13px;
            color: #718096;
            margin-bottom: 8px;
        }

        .action-buttons {
            background: white;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            display: flex;
            justify-content: space-between;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-save {
            background: #718096;
            color: white;
        }

        .btn-save:hover {
            background: #5a6268;
        }

        .btn-submit {
            background: #48bb78;
            color: white;
        }

        .btn-submit:hover {
            background: #38a169;
        }

        #autoSaveNotification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #48bb78;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            z-index: 1000;
            font-size: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: none;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .task-groups {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }

            .btn {
                width: 100%;
            }
        }

        .task-summary {
            background: #f7fafc;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .task-info {
            display: flex;
            gap: 20px;
        }

        .task-count {
            text-align: center;
        }

        .task-number {
            font-size: 24px;
            font-weight: bold;
            color: #1a365d;
        }

        .task-label {
            font-size: 12px;
            color: #718096;
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
            <a href="am-checklist.php" class="nav-item active">
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
            <h2>SMT Production Line - AM Checklist</h2>
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                Logout
            </button>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div style="background-color: #fed7d7; color: #9b2c2c; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Maintenance Information -->
        <form method="POST" action="" onsubmit="return validateForm()">
            <div class="form-section">
                <h3 class="section-title"><i class="fas fa-info-circle"></i> Maintenance Information</h3>
                <div class="form-grid">
                    <div class="form-field">
                        <label>Date</label>
                        <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-field">
                        <label>Shift</label>
                        <select id="shift" name="shift" required>
                            <option value="">Select Shift</option>
                            <option value="42A">42 A</option>
                            <option value="42B">42 B</option>
                            <option value="42C">42 C</option>
                        </select>
                    </div>
                    
                    <div class="form-field">
                        <label>Employee Name</label>
                        <input type="text" id="employeeName" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                    </div>

                    <div class="form-field">
                        <label>Employee ID</label>
                        <input type="text" id="employeeId" value="<?php echo htmlspecialchars($user['employee_id']); ?>" readonly>
                    </div>
                </div>
            </div>

            <!-- Task Summary (Simplified - no percentages) -->
            <div class="task-summary" id="taskSummary">
                <div class="task-info">
                    <div class="task-count">
                        <div class="task-number" id="completedCount">0</div>
                        <div class="task-label">Completed Tasks</div>
                    </div>
                    <div class="task-count">
                        <div class="task-number" id="totalCount"><?php echo count($tasks); ?></div>
                        <div class="task-label">Total Tasks</div>
                    </div>
                </div>
            </div>

            <!-- Task Groups -->
            <div class="task-groups">
                <?php
                // Group tasks by task_group
                $groupedTasks = [];
                foreach ($tasks as $task) {
                    $group = $task['task_group'] ?? 'General';
                    if (!isset($groupedTasks[$group])) {
                        $groupedTasks[$group] = [];
                    }
                    $groupedTasks[$group][] = $task;
                }
                
                foreach ($groupedTasks as $groupName => $groupTasks):
                ?>
                <div class="task-group">
                    <div class="task-group-header">
                        <i class="fas fa-list-ol"></i> <?php echo htmlspecialchars($groupName); ?>
                    </div>
                    
                    <div class="task-group-body">
                        <?php foreach ($groupTasks as $task): ?>
                        <div class="task-item">
                            <div class="task-checkbox">
                                <input type="checkbox" id="task_<?php echo $task['id']; ?>" 
                                       name="task_<?php echo $task['id']; ?>" 
                                       class="task-checkbox-input"
                                       onchange="updateTaskCount()">
                            </div>
                            <div class="task-content">
                                <div class="task-title"><?php echo htmlspecialchars($task['task_code'] . ': ' . $task['task_title']); ?></div>
                                <?php if (!empty($task['task_description'])): ?>
                                <div class="task-description"><?php echo htmlspecialchars($task['task_description']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button type="button" class="btn btn-save" onclick="saveProgress()">
                    <i class="fas fa-save"></i> Save Progress
                </button>
                <button type="submit" class="btn btn-submit">
                    <i class="fas fa-paper-plane"></i> Submit Checklist
                </button>
            </div>
        </form>

        <!-- Auto-save notification -->
        <div id="autoSaveNotification"></div>
    </div>

    <script>
        function updateTaskCount() {
            const checkboxes = document.querySelectorAll('.task-checkbox-input');
            let completed = 0;
            
            checkboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    completed++;
                }
            });
            
            // Update display
            document.getElementById('completedCount').textContent = completed;
        }

        function validateForm() {
            const date = document.getElementById('date').value;
            const shift = document.getElementById('shift').value;
            
            if (!date || !shift) {
                alert('Please select Date and Shift');
                return false;
            }
            
            const checkboxes = document.querySelectorAll('.task-checkbox-input');
            let completed = 0;
            
            checkboxes.forEach(checkbox => {
                if (checkbox.checked) completed++;
            });
            
            const pending = checkboxes.length - completed;
            
            if (pending > 0) {
                if (!confirm(`You have ${pending} tasks pending. Submit anyway?`)) {
                    return false;
                }
            }
            
            // FINAL CONFIRMATION - Clear localStorage when submitting
            if (confirm('Submit this AM checklist?')) {
                localStorage.removeItem('am_checklist_progress'); // Clear saved progress on submit
                return true;
            }
            return false;
        }

        function saveProgress() {
            const formData = new FormData(document.querySelector('form'));
            const progress = {};
            
            for (let [key, value] of formData.entries()) {
                if (key.startsWith('task_')) {
                    progress[key] = value;
                }
            }
            
            localStorage.setItem('am_checklist_progress', JSON.stringify(progress));
            
            // Show notification
            const completed = document.getElementById('completedCount').textContent;
            const total = document.getElementById('totalCount').textContent;
            showNotification(`Progress saved (${completed}/${total} tasks checked)`);
        }

        function showNotification(message) {
            const notification = document.getElementById('autoSaveNotification');
            notification.textContent = message;
            notification.style.display = 'block';
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    notification.style.display = 'none';
                    notification.style.opacity = '1';
                }, 500);
            }, 3000);
        }

        // Load saved progress on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedProgress = localStorage.getItem('am_checklist_progress');
            if (savedProgress) {
                try {
                    const progress = JSON.parse(savedProgress);
                    
                    for (const [key, value] of Object.entries(progress)) {
                        if (key.startsWith('task_')) {
                            const taskId = key.replace('task_', '');
                            const checkbox = document.getElementById(`task_${taskId}`);
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        }
                    }
                    
                    updateTaskCount();
                    console.log('Loaded saved progress');
                } catch (error) {
                    console.error('Error loading saved progress:', error);
                }
            }
            
            // Initialize task count display
            updateTaskCount();
        });
    </script>
</body>
</html>