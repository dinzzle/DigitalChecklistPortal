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
    $line = $_POST['line'] ?? '';
    $zone = $_POST['zone'] ?? '';
    $date = $_POST['date'] ?? date('Y-m-d');
    
    // Collect answers
    $answers = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'question_') === 0) {
            $questionId = str_replace('question_', '', $key);
            $answers[$questionId] = [
                'answer' => $value,
                'comment' => $_POST['comment_' . $questionId] ?? ''
            ];
        }
    }
    
    // Calculate statistics
    $yesAnswers = 0;
    $noAnswers = 0;
    $naAnswers = 0;
    
    foreach ($answers as $answer) {
        switch ($answer['answer']) {
            case 'yes': $yesAnswers++; break;
            case 'no': $noAnswers++; break;
            case 'na': $naAnswers++; break;
        }
    }
    
    $totalQuestions = count($answers);
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert checklist
        $stmt = $pdo->prepare("INSERT INTO checklists (user_id, type, title, shift, line, zone, date, 
                               total_questions, yes_answers, no_answers, na_answers, status, submitted_at) 
                               VALUES (?, '5s', 'SMT MACHINE - 5S CHECKLIST', ?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())");
        $stmt->execute([$user['id'], $shift, $line, $zone, $date, $totalQuestions, $yesAnswers, $noAnswers, $naAnswers]);
        $checklistId = $pdo->lastInsertId();
        
        // Insert answers
        $answerStmt = $pdo->prepare("INSERT INTO checklist_answers (checklist_id, question_id, answer, comment) 
                                     VALUES (?, ?, ?, ?)");
        
        foreach ($answers as $questionId => $answerData) {
            $answerStmt->execute([$checklistId, $questionId, $answerData['answer'], $answerData['comment']]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = "Checklist submitted successfully!";
        header("Location: dashboard.php");
        exit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error submitting checklist: " . $e->getMessage();
        header("Location: 5s-checklist.php");
        exit();
    }
}

// Get questions from database
$questions = [];
try {
    $stmt = $pdo->query("SELECT * FROM checklist_questions WHERE checklist_type = '5s' AND is_active = 1 ORDER BY section, question_number");
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching questions: " . $e->getMessage());
    $_SESSION['error'] = "Error loading checklist questions";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>5S Checklist - Digital Checklist System</title>
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

        /* Sidebar - Matching AM checklist */
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

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 220px;
            padding: 20px;
        }

        /* Header - Matching AM checklist */
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

        /* Form Section - Matching AM checklist */
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

        /* Task Groups - Matching AM checklist */
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

        /* Task Item - Modified for 5S Yes/No/NA */
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

        /* 5S Answer Options */
        .answer-options {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .answer-btn {
            padding: 6px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
        }

        .answer-btn:hover {
            border-color: #1a365d;
            background: #f7fafc;
        }

        .answer-btn.selected-yes {
            background: #48bb78;
            color: white;
            border-color: #48bb78;
        }

        .answer-btn.selected-no {
            background: #e53e3e;
            color: white;
            border-color: #e53e3e;
        }

        .answer-btn.selected-na {
            background: #718096;
            color: white;
            border-color: #718096;
        }

        .task-comment {
            width: 100%;
            padding: 8px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 13px;
            display: none;
        }

        .task-comment.active {
            display: block;
        }

        /* Task Summary - Matching AM checklist */
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

        /* Action Buttons - Matching AM checklist */
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

        /* Auto-save notification - Matching AM checklist */
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

        /* Responsive - Matching AM checklist */
        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
                padding: 10px 0;
            }
            
            .logo img {
                max-height: 40px;
                margin-bottom: 0;
            }
            
            .logo p, .user-details, .nav-item span {
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

            .answer-options {
                flex-wrap: wrap;
            }

            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar - Matching AM checklist -->
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
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="5s-checklist.php" class="nav-item active">
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
        <!-- Header - Matching AM checklist -->
        <div class="header">
            <h2>SMT MACHINE - 5S CHECKLIST</h2>
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                Logout
            </button>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div style="background-color: #fed7d7; color: #9b2c2c; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Information Section - Matching AM checklist -->
        <form method="POST" action="" onsubmit="return validateForm()">
            <div class="form-section">
                <h3 class="section-title"><i class="fas fa-info-circle"></i> Information</h3>
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
                        <label>Line No.</label>
                        <select id="line" name="line" required>
                            <option value="">Select Line</option>
                            <option value="SMT 1">SMT 1</option>
                            <option value="SMT 2">SMT 2</option>
                            <option value="SMT 3">SMT 3</option>
                            <option value="SMT 4">SMT 4</option>
                            <option value="SMT 5">SMT 5</option>
                            <option value="SMT 6">SMT 6</option>
                            <option value="SMT 9">SMT 9</option>
                            <option value="SMT 11">SMT 11</option>
                            <option value="SMT 12">SMT 12</option>
                            <option value="SMT 13">SMT 13</option>
                            <option value="SMT 15">SMT 15</option>
                            <option value="SMT 16">SMT 16</option>
                            <option value="SMT 18">SMT 18</option>
                            <option value="SMT 19">SMT 19</option>
                        </select>
                    </div>
                    
                    <div class="form-field">
                        <label>Zone</label>
                        <select id="zone" name="zone" required>
                            <option value="">Select Zone</option>
                            <option value="Zone A">Zone A</option>
                            <option value="Zone B">Zone B</option>
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

            <!-- Task Summary - Matching AM checklist -->
            <div class="task-summary" id="taskSummary">
                <div class="task-info">
                    <div class="task-count">
                        <div class="task-number" id="answeredCount">0</div>
                        <div class="task-label">Answered</div>
                    </div>
                    <div class="task-count">
                        <div class="task-number" id="totalCount"><?php echo count($questions); ?></div>
                        <div class="task-label">Total Questions</div>
                    </div>
                    <div class="task-count">
                        <div class="task-number" id="yesCount">0</div>
                        <div class="task-label">Yes</div>
                    </div>
                    <div class="task-count">
                        <div class="task-number" id="noCount">0</div>
                        <div class="task-label">No</div>
                    </div>
                </div>
            </div>

            <!-- Task Groups - Matching AM checklist -->
            <div class="task-groups">
                <?php
                // Group questions by section
                $groupedQuestions = [];
                foreach ($questions as $question) {
                    $section = $question['section'] ?? 'General';
                    if (!isset($groupedQuestions[$section])) {
                        $groupedQuestions[$section] = [];
                    }
                    $groupedQuestions[$section][] = $question;
                }
                
                foreach ($groupedQuestions as $sectionName => $sectionQuestions):
                    $sectionIcon = match($sectionName) {
                        'Zone A', 'Zone B' => 'map-marker-alt',
                        'Oven Area' => 'thermometer-half',
                        default => 'list-alt'
                    };
                ?>
                <div class="task-group">
                    <div class="task-group-header">
                        <i class="fas fa-<?php echo $sectionIcon; ?>"></i> 
                        <?php echo htmlspecialchars(strtoupper($sectionName)); ?>
                    </div>
                    
                    <div class="task-group-body">
                        <?php 
                        // Group by equipment
                        $equipmentGroups = [];
                        foreach ($sectionQuestions as $question) {
                            $equipment = $question['equipment'] ?? 'General';
                            if (!isset($equipmentGroups[$equipment])) {
                                $equipmentGroups[$equipment] = [];
                            }
                            $equipmentGroups[$equipment][] = $question;
                        }
                        
                        foreach ($equipmentGroups as $equipmentName => $equipmentQuestions):
                        ?>
                        <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #e2e8f0;">
                            <div style="font-weight: 600; color: #1a365d; margin-bottom: 10px; font-size: 15px;">
                                <i class="fas fa-cog"></i> <?php echo htmlspecialchars($equipmentName); ?>
                            </div>
                            
                            <?php foreach ($equipmentQuestions as $question): ?>
                            <div class="task-item">
                                <div class="task-content">
                                    <div class="task-title">
                                        <?php echo htmlspecialchars($question['question_number'] . '. ' . $question['question_text']); ?>
                                    </div>
                                    <div class="answer-options">
                                        <button type="button" class="answer-btn" 
                                                data-question="<?php echo $question['id']; ?>" 
                                                data-value="yes" 
                                                onclick="selectAnswer(this, 'yes')">
                                            Yes
                                        </button>
                                        <button type="button" class="answer-btn" 
                                                data-question="<?php echo $question['id']; ?>" 
                                                data-value="no" 
                                                onclick="selectAnswer(this, 'no')">
                                            No
                                        </button>
                                        <button type="button" class="answer-btn" 
                                                data-question="<?php echo $question['id']; ?>" 
                                                data-value="na" 
                                                onclick="selectAnswer(this, 'na')">
                                            N/A
                                        </button>
                                    </div>
                                    <textarea class="task-comment" 
                                              id="comment_<?php echo $question['id']; ?>" 
                                              name="comment_<?php echo $question['id']; ?>" 
                                              placeholder="Add comment (required if 'No' is selected)..." 
                                              style="display: none;"></textarea>
                                    <input type="hidden" 
                                           id="question_<?php echo $question['id']; ?>" 
                                           name="question_<?php echo $question['id']; ?>" 
                                           value="">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Action Buttons - Matching AM checklist -->
            <div class="action-buttons">
                <button type="button" class="btn btn-save" onclick="saveProgress()">
                    <i class="fas fa-save"></i> Save Progress
                </button>
                <button type="submit" class="btn btn-submit">
                    <i class="fas fa-paper-plane"></i> Submit Checklist
                </button>
            </div>
        </form>

        <!-- Auto-save notification - Matching AM checklist -->
        <div id="autoSaveNotification"></div>
    </div>

    <script>
        function selectAnswer(button, value) {
            const questionId = button.dataset.question;
            const buttons = button.parentElement.querySelectorAll('.answer-btn');
            const commentBox = document.getElementById('comment_' + questionId);
            const hiddenInput = document.getElementById('question_' + questionId);
            
            // Remove all selected classes
            buttons.forEach(btn => {
                btn.classList.remove('selected-yes', 'selected-no', 'selected-na');
            });
            
            // Add appropriate selected class
            button.classList.add('selected-' + value);
            
            // Set hidden input value
            hiddenInput.value = value;
            
            // Show/hide comment box
            if (value === 'no') {
                commentBox.style.display = 'block';
                commentBox.required = true;
            } else {
                commentBox.style.display = 'none';
                commentBox.required = false;
                commentBox.value = ''; // Clear comment if not needed
            }
            
            // Update task counts
            updateTaskCounts();
        }

        function updateTaskCounts() {
            const totalQuestions = <?php echo count($questions); ?>;
            const answered = document.querySelectorAll('.answer-btn.selected-yes, .answer-btn.selected-no, .answer-btn.selected-na').length;
            const yesCount = document.querySelectorAll('.answer-btn.selected-yes').length;
            const noCount = document.querySelectorAll('.answer-btn.selected-no').length;
            
            document.getElementById('answeredCount').textContent = answered;
            document.getElementById('yesCount').textContent = yesCount;
            document.getElementById('noCount').textContent = noCount;
        }

        function validateForm() {
            const date = document.getElementById('date').value;
            const shift = document.getElementById('shift').value;
            const line = document.getElementById('line').value;
            const zone = document.getElementById('zone').value;
            
            if (!date || !shift || !line || !zone) {
                alert('Please fill in all required fields (Date, Shift, Line No., and Zone)');
                return false;
            }
            
            // Check if all questions are answered
            const unanswered = <?php echo count($questions); ?> - document.querySelectorAll('.answer-btn.selected-yes, .answer-btn.selected-no, .answer-btn.selected-na').length;
            
            if (unanswered > 0) {
                if (!confirm(`You have ${unanswered} unanswered questions. Submit anyway?`)) {
                    return false;
                }
            }
            
            // Check if "No" answers have comments
            const noButtons = document.querySelectorAll('.answer-btn.selected-no');
            let missingComments = 0;
            
            noButtons.forEach(btn => {
                const questionId = btn.dataset.question;
                const commentBox = document.getElementById('comment_' + questionId);
                if (!commentBox.value.trim()) {
                    missingComments++;
                }
            });
            
            if (missingComments > 0) {
                alert(`You have ${missingComments} "No" answers without comments. Please add comments for all "No" answers.`);
                return false;
            }
            
            // FINAL CONFIRMATION - Clear localStorage when submitting
            if (confirm('Submit this 5S checklist?')) {
                localStorage.removeItem('5s_checklist_progress'); // Clear saved progress on submit
                return true;
            }
            return false;
        }

        function saveProgress() {
            const formData = new FormData(document.querySelector('form'));
            const progress = {};
            
            for (let [key, value] of formData.entries()) {
                if (key.startsWith('question_') || key.startsWith('comment_')) {
                    progress[key] = value;
                }
            }
            
            localStorage.setItem('5s_checklist_progress', JSON.stringify(progress));
            
            // Show notification
            const answered = document.getElementById('answeredCount').textContent;
            const total = document.getElementById('totalCount').textContent;
            showNotification(`Progress saved (${answered}/${total} questions answered)`);
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
            const savedProgress = localStorage.getItem('5s_checklist_progress');
            if (savedProgress) {
                try {
                    const progress = JSON.parse(savedProgress);
                    
                    for (const [key, value] of Object.entries(progress)) {
                        if (key.startsWith('question_')) {
                            const questionId = key.replace('question_', '');
                            const buttons = document.querySelectorAll(`[data-question="${questionId}"]`);
                            buttons.forEach(button => {
                                if (button.dataset.value === value) {
                                    selectAnswer(button, value);
                                }
                            });
                        }
                        
                        if (key.startsWith('comment_')) {
                            const questionId = key.replace('comment_', '');
                            const commentBox = document.getElementById(`comment_${questionId}`);
                            if (commentBox && value) {
                                commentBox.value = value;
                            }
                        }
                    }
                    
                    console.log('Loaded saved progress');
                } catch (error) {
                    console.error('Error loading saved progress:', error);
                }
            }
            
            // Initialize task counts
            updateTaskCounts();
        });
    </script>
</body>
</html>