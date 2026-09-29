<?php
session_start();
require_once 'config/database.php';

// Log audit event
if (isset($_SESSION['user_id'])) {
    logAuditEvent('logout', 'User logged out', 'info', [
        'user_id' => $_SESSION['user_id']
    ]);
}

// Clear session
session_unset();
session_destroy();

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirect to login
header("Location: index.php");
exit();

function logAuditEvent($actionType, $description, $severity = 'info', $details = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, action_description, severity, ip_address, user_agent, details) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $actionType,
            $description,
            $severity,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'],
            $details ? json_encode($details) : null
        ]);
    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}
?>