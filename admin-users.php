<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is admin
checkAdminAccess();

$user = $_SESSION['user'];

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_user':
            handleAddUser();
            break;
        case 'edit_user':
            handleEditUser();
            break;
        case 'delete_user':
            handleDeleteUser();
            break;
        case 'reset_password':
            handleResetPassword();
            break;
    }
}

// Handle GET actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'toggle_status':
            handleToggleStatus();
            break;
    }
}

// Get users from database
$users = [];
$totalUsers = 0;
$activeUsers = 0;

try {
    // Get all users
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmtStats = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
        FROM users");
    $stats = $stmtStats->fetch();
    $totalUsers = $stats['total'] ?? 0;
    $activeUsers = $stats['active'] ?? 0;
    
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $_SESSION['error'] = "Error loading user data";
}

// Get unique departments for filter
$departments = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department");
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching departments: " . $e->getMessage());
}

function handleAddUser() {
    global $pdo;
    
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $employeeId = $_POST['employee_id'] ?? '';
    $department = $_POST['department'] ?? '';
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? 'Temp@1234';
    
    // Validation
    $errors = [];
    
    if (empty($name)) $errors[] = "Name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($employeeId)) $errors[] = "Employee ID is required";
    if (empty($department)) $errors[] = "Department is required";
    if (empty($role)) $errors[] = "Role is required";
    
    if (empty($errors)) {
        try {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Email already exists";
            }
            
            // Check if employee ID exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE employee_id = ?");
            $stmt->execute([$employeeId]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Employee ID already exists";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    if (empty($errors)) {
        try {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, department, employee_id, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$name, $email, $hashedPassword, $role, $department, $employeeId]);
            $userId = $pdo->lastInsertId();
            
            $_SESSION['success'] = "User added successfully!";
            header("Location: admin-users.php");
            exit();
            
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding user: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}

function handleEditUser() {
    global $pdo;
    
    $userId = $_POST['user_id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';
    $department = $_POST['department'] ?? '';
    $status = $_POST['status'] ?? '';
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, department = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $email, $role, $department, $status, $userId]);
        
        $_SESSION['success'] = "User updated successfully!";
        header("Location: admin-users.php");
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating user: " . $e->getMessage();
    }
}

function handleDeleteUser() {
    global $pdo;
    
    $userId = $_POST['user_id'] ?? 0;
    
    try {
        // Delete user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        $_SESSION['success'] = "User deleted successfully!";
        header("Location: admin-users.php");
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting user: " . $e->getMessage();
    }
}

function handleResetPassword() {
    global $pdo;
    
    $userId = $_POST['user_id'] ?? 0;
    
    // Cek apakah password yang di-reset bisa login
    $password = 'Temp@1234'; // Password default yang sama dengan add user
    
    try {
        // Cek user exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $checkStmt->execute([$userId]);
        
        if ($checkStmt->rowCount() === 0) {
            $_SESSION['error'] = "User not found";
            header("Location: admin-users.php");
            exit();
        }
        
        // HASH PASSWORD dengan cara yang BENAR
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Debug: Simpan password dan hash untuk testing
        error_log("Reset Password Debug - User ID: $userId, Plain Password: $password, Hashed: $hashedPassword");
        
        // Update password
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        
        // Verifikasi hash yang disimpan
        $verifyStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $verifyStmt->execute([$userId]);
        $storedHash = $verifyStmt->fetchColumn();
        
        // Test if password verification works
        if (password_verify($password, $storedHash)) {
            error_log("Password verification SUCCESS for user $userId");
        } else {
            error_log("Password verification FAILED for user $userId");
            error_log("Stored hash: $storedHash");
            error_log("Expected to verify with: $password");
        }
        
        $_SESSION['success'] = "Password reset successfully! Temporary password: Temp@1234";
        header("Location: admin-users.php");
        exit();
        
    } catch (PDOException $e) {
        error_log("Reset Password Error: " . $e->getMessage());
        $_SESSION['error'] = "Error resetting password: " . $e->getMessage();
    }
}

function handleToggleStatus() {
    global $pdo;
    
    $userId = $_GET['id'] ?? 0;
    $status = $_GET['status'] ?? '';
    
    if ($userId && in_array($status, ['active', 'inactive'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$status, $userId]);
            
            $_SESSION['success'] = "User status updated successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating user status: " . $e->getMessage();
        }
    }
    
    header("Location: admin-users.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin</title>
    <!-- KEEP YOUR ORIGINAL STYLE -->
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
            margin-bottom: 30px;
            padding-bottom: 20px;
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

        /* User Management Section */
        .user-management {
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
        }

        .section-title > div {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .section-title > div i {
            color: var(--accent-blue);
            font-size: 24px;
        }

        .section-title span {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
            margin-left: 15px;
        }

        .add-user-btn {
            background: linear-gradient(135deg, var(--accent-green), #059669);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
        }

        .add-user-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }

        /* Search and Filter */
        .search-filter {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 15px;
            margin-bottom: 25px;
            background: var(--bg-tertiary);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .search-box input {
            width: 100%;
            padding: 12px 20px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-box input::placeholder {
            color: var(--text-tertiary);
        }

        .filter-select {
            padding: 12px 20px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 150px;
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

        /* Messages */
        .success-message {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
            color: #10b981;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid rgba(16, 185, 129, 0.2);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

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

        /* Table Container */
        .table-container {
            overflow-x: auto;
            background: var(--bg-tertiary);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        .users-table thead {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-indigo));
        }

        .users-table th {
            padding: 18px 20px;
            text-align: left;
            color: white;
            font-weight: 600;
            font-size: 15px;
            border-bottom: 2px solid var(--border-color);
        }

        .users-table td {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 15px;
        }

        .users-table tbody tr {
            transition: all 0.3s ease;
            background: var(--bg-tertiary);
        }

        .users-table tbody tr:hover {
            background: var(--bg-secondary);
            transform: translateX(5px);
        }

        /* User Roles */
        .user-role {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.3px;
            display: inline-block;
        }

        .role-admin {
            background: linear-gradient(135deg, var(--accent-purple), #7c3aed);
            color: white;
        }

        .role-operator {
            background: linear-gradient(135deg, var(--accent-blue), #1d4ed8);
            color: white;
        }

        .role-supervisor {
            background: linear-gradient(135deg, var(--accent-orange), #c2410c);
            color: white;
        }

        .role-manager {
            background: linear-gradient(135deg, var(--accent-green), #059669);
            color: white;
        }

        /* User Status */
        .user-status {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.3px;
            display: inline-block;
        }

        .status-active {
            background: linear-gradient(135deg, var(--accent-green), #059669);
            color: white;
        }

        .status-inactive {
            background: linear-gradient(135deg, #64748b, #475569);
            color: white;
        }

        .status-pending {
            background: linear-gradient(135deg, var(--accent-orange), #c2410c);
            color: white;
        }

        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.3s ease;
            color: white;
        }

        .edit-btn {
            background: linear-gradient(135deg, var(--accent-blue), #1d4ed8);
        }

        .edit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .reset-btn {
            background: linear-gradient(135deg, var(--accent-orange), #c2410c);
        }

        .reset-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(249, 115, 22, 0.3);
        }

        .delete-btn {
            background: linear-gradient(135deg, var(--accent-red), #dc2626);
        }

        .delete-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
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

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: linear-gradient(145deg, var(--bg-secondary), var(--bg-tertiary));
            border-radius: 16px;
            width: 100%;
            max-width: 600px;
            border: 1px solid var(--border-color);
            box-shadow: var(--hover-shadow);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 30px;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h3 i {
            color: var(--accent-blue);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }

        .close-btn:hover {
            color: var(--accent-red);
            background: rgba(239, 68, 68, 0.1);
        }

        .modal-body {
            padding: 30px;
        }

        .modal-body label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 14px;
        }

        .modal-body input,
        .modal-body select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 15px;
            transition: all 0.3s ease;
            margin-bottom: 5px;
        }

        .modal-body input:focus,
        .modal-body select:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .modal-body small {
            color: var(--text-tertiary);
            font-size: 12px;
        }

        .modal-footer {
            padding: 25px 30px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-blue), #1d4ed8);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .search-filter {
                grid-template-columns: 1fr;
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
            
            .modal-content {
                margin: 20px;
                max-width: calc(100% - 40px);
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            
            .section-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            
            .add-user-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
            
            .user-management {
                padding: 20px;
            }
            
            .modal-body, .modal-footer, .modal-header {
                padding: 20px;
            }
            
            .action-btns {
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
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
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            <a href="admin-users.php" class="nav-item active">
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
            <h2>User Management</h2>
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
        
        <!-- User Management -->
        <div class="user-management">
            <div class="section-title">
                <div>
                    <i class="fas fa-users"></i> All Users
                    <span>(<?php echo $totalUsers; ?> total, <?php echo $activeUsers; ?> active)</span>
                </div>
                <button class="add-user-btn" onclick="showAddUserModal()">
                    <i class="fas fa-user-plus"></i> Add New User
                </button>
            </div>
            
            <!-- Search and Filter -->
            <div class="search-filter">
                <div class="search-box">
                    <input type="text" id="searchUsers" placeholder="Search by name, email, or department..." 
                           onkeyup="filterUsers()">
                </div>
                <select class="filter-select" id="filterRole" onchange="filterUsers()">
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="operator">Operator</option>
                </select>
                <select class="filter-select" id="filterStatus" onchange="filterUsers()">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select class="filter-select" id="filterDepartment" onchange="filterUsers()">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept); ?>">
                        <?php echo htmlspecialchars(ucfirst($dept)); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Display messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Users Table -->
            <div class="table-container">
                <table class="users-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Employee ID</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <?php if (empty($users)): ?>
                            <tr class="empty-state-row">
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <h3>No users found</h3>
                                        <p>Add your first user to get started</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                            <tr data-user-id="<?php echo $u['id']; ?>"
                                data-role="<?php echo htmlspecialchars($u['role']); ?>"
                                data-status="<?php echo htmlspecialchars($u['status']); ?>"
                                data-department="<?php echo htmlspecialchars($u['department']); ?>">
                                <td><?php echo htmlspecialchars($u['name']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <span class="user-role role-<?php echo $u['role']; ?>">
                                        <?php echo ucfirst($u['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(ucfirst($u['department'])); ?></td>
                                <td><?php echo htmlspecialchars($u['employee_id']); ?></td>
                                <td>
                                    <span class="user-status status-<?php echo $u['status']; ?>">
                                        <?php echo ucfirst($u['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['last_login']): ?>
                                        <?php echo date('M d, H:i', strtotime($u['last_login'])); ?>
                                    <?php else: ?>
                                        Never
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn edit-btn" onclick="showEditUserModal(<?php echo $u['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn reset-btn" onclick="resetPassword(<?php echo $u['id']; ?>)">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <?php if ($u['id'] != $user['id']): ?>
                                        <button class="action-btn delete-btn" onclick="deleteUser(<?php echo $u['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New User</h3>
                <button class="close-btn" onclick="closeModal('addUserModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-body">
                    <div style="display: grid; gap: 15px;">
                        <div>
                            <label>Full Name *</label>
                            <input type="text" name="name" required placeholder="Enter full name">
                        </div>
                        
                        <div>
                            <label>Email *</label>
                            <input type="email" name="email" required placeholder="your.name@bosch.com">
                        </div>
                        
                        <div>
                            <label>Employee ID *</label>
                            <input type="text" name="employee_id" required placeholder="Enter employee ID">
                        </div>
                        
                        <div>
                            <label>Department *</label>
                            <select name="department" required>
                                <option value="">Select Department</option>
                                <option value="production">Production</option>
                                <option value="quality">Quality Control</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="assembly">Assembly</option>
                                <option value="it">IT</option>
                                <option value="management">Management</option>
                            </select>
                        </div>
                        
                        <div>
                            <label>Role *</label>
                            <select name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin">Administrator</option>
                                <option value="operator">Operator</option>
                            </select>
                        </div>
                        
                        <div>
                            <label>Temporary Password</label>
                            <input type="text" name="password" value="Temp@1234" readonly>
                            <small>User will be asked to change this on first login</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save User
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit User</h3>
                <button class="close-btn" onclick="closeModal('editUserModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" id="edit_user_id" name="user_id">
                <div class="modal-body">
                    <div style="display: grid; gap: 15px;">
                        <div>
                            <label>Full Name *</label>
                            <input type="text" id="edit_name" name="name" required>
                        </div>
                        
                        <div>
                            <label>Email *</label>
                            <input type="email" id="edit_email" name="email" required>
                        </div>
                        
                        <div>
                            <label>Role *</label>
                            <select id="edit_role" name="role" required>
                                <option value="admin">Administrator</option>
                                <option value="operator">Operator</option>
                            </select>
                        </div>
                        
                        <div>
                            <label>Department *</label>
                            <select id="edit_department" name="department" required>
                                <option value="production">Production</option>
                                <option value="quality">Quality Control</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="assembly">Assembly</option>
                                <option value="it">IT</option>
                                <option value="management">Management</option>
                            </select>
                        </div>
                        
                        <div>
                            <label>Status *</label>
                            <select id="edit_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update User
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Debounce function to limit how often filterUsers is called
        let filterTimeout;
        
        function showAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
        }

        function showEditUserModal(userId) {
            // Get the table row with matching user ID
            const row = document.querySelector(`tr[data-user-id="${userId}"]`);
            if (row) {
                document.getElementById('edit_user_id').value = userId;
                document.getElementById('edit_name').value = row.cells[0].textContent;
                document.getElementById('edit_email').value = row.cells[1].textContent;
                
                // Get role from data attribute
                const role = row.dataset.role;
                document.getElementById('edit_role').value = role;
                
                // Get department from data attribute
                const department = row.dataset.department;
                document.getElementById('edit_department').value = department;
                
                // Get status from data attribute
                const status = row.dataset.status;
                document.getElementById('edit_status').value = status;
                
                document.getElementById('editUserModal').style.display = 'flex';
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function resetPassword(userId) {
            if (confirm('Reset password for this user? A temporary password will be set.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'reset_password';
                form.appendChild(actionInput);
                
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                form.appendChild(userIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_user';
                form.appendChild(actionInput);
                
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                form.appendChild(userIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        function filterUsers() {
            const search = document.getElementById('searchUsers').value.toLowerCase();
            const role = document.getElementById('filterRole').value;
            const status = document.getElementById('filterStatus').value;
            const department = document.getElementById('filterDepartment').value;
            
            const rows = document.querySelectorAll('#usersTableBody tr');
            let hasVisibleRows = false;
            
            rows.forEach(row => {
                if (row.classList.contains('empty-state-row')) return;
                
                const name = row.cells[0].textContent.toLowerCase();
                const email = row.cells[1].textContent.toLowerCase();
                const userDept = row.cells[3].textContent.toLowerCase();
                
                // Get data attributes
                const userRole = row.dataset.role || '';
                const userStatus = row.dataset.status || '';
                const userDepartment = row.dataset.department || '';
                
                let show = true;
                
                // Search filter
                if (search && !name.includes(search) && !email.includes(search) && !userDept.includes(search)) {
                    show = false;
                }
                
                // Role filter
                if (role && userRole !== role) {
                    show = false;
                }
                
                // Status filter
                if (status && userStatus !== status) {
                    show = false;
                }
                
                // Department filter
                if (department && userDepartment !== department) {
                    show = false;
                }
                
                if (show) {
                    row.style.display = '';
                    hasVisibleRows = true;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Check if we need to show the empty state
            const emptyStateRow = document.querySelector('.empty-state-row');
            
            if (!hasVisibleRows) {
                if (!emptyStateRow) {
                    const tbody = document.getElementById('usersTableBody');
                    const row = tbody.insertRow();
                    row.className = 'empty-state-row';
                    row.innerHTML = `
                        <td colspan="8">
                            <div class="empty-state">
                                <i class="fas fa-search"></i>
                                <h3>No users found</h3>
                                <p>Try adjusting your search or filters</p>
                            </div>
                        </td>
                    `;
                }
            } else if (emptyStateRow) {
                emptyStateRow.remove();
            }
        }

        // Add debounced filter for search input
        document.getElementById('searchUsers').addEventListener('input', function() {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(filterUsers, 300);
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Close modal when clicking outside
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(event) {
                    if (event.target === this) {
                        this.style.display = 'none';
                    }
                });
            });
            
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(event) {
                // Ctrl+Shift+A to add new user
                if (event.ctrlKey && event.shiftKey && event.key === 'A') {
                    event.preventDefault();
                    showAddUserModal();
                }
                
                // Escape to close modals
                if (event.key === 'Escape') {
                    closeModal('addUserModal');
                    closeModal('editUserModal');
                }
            });
        });
    </script>
</body>
</html>