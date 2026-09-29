<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    header("Location: forgot_password.php");
    exit();
}

$token = $_GET['token'];
$message = '';
$error = '';
$valid_token = false;
$user_id = null;

// Validate token
try {
    require_once 'config/database.php';
    
    $stmt = $pdo->prepare("SELECT id, reset_token_expiry FROM users WHERE reset_token = ? AND status = 'active'");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Check if token is expired
        $now = new DateTime();
        $expiry = new DateTime($user['reset_token_expiry']);
        
        if ($now < $expiry) {
            $valid_token = true;
            $user_id = $user['id'];
        } else {
            $error = "Password reset link has expired. Please request a new one.";
        }
    } else {
        $error = "Invalid password reset link.";
    }
} catch (PDOException $e) {
    error_log("Reset password error: " . $e->getMessage());
    $error = "Database error occurred. Please try again.";
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($password)) {
        $error = "Password is required";
    } elseif (empty($confirm_password)) {
        $error = "Please confirm your password";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } else {
        try {
            // Hash the new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update user's password and clear reset token
            $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $message = "Password has been reset successfully! You can now login with your new password.";
            $valid_token = false; // Prevent form from showing again
            
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = "Error resetting password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Digital Checklist System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            background: url("Bosch-Penang-Car-Multimedia-Plant.jpg") no-repeat center center/cover;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
        }

        body::before {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(245, 247, 250, 0.85);
            z-index: 0;
        }

        .container {
            width: 100%;
            max-width: 460px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            position: relative;
            z-index: 1;
        }

        .login-section {
            padding: 40px;
        }

        .logo {
            margin-bottom: 30px;
            text-align: center;
        }

        .logo-main {
            font-size: 20px;
            font-weight: 600;
            color: #1a365d;
            letter-spacing: 0.3px;
        }

        .system-name {
            font-size: 12px;
            color: #718096;
            margin-top: 4px;
            letter-spacing: 0.5px;
        }

        .login-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .login-header h1 {
            font-size: 24px;
            color: #1a365d;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .login-header p {
            color: #718096;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #374151;
            font-weight: 500;
        }

        input[type="password"] {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #cbd5e0;
            font-size: 15px;
            transition: border-color 0.2s;
            background: white;
        }

        input[type="password"]:focus {
            outline: none;
            border-color: #1a365d;
        }

        .password-strength {
            margin-top: 5px;
            font-size: 12px;
            color: #718096;
        }

        .strength-meter {
            height: 4px;
            background-color: #e2e8f0;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background-color 0.3s;
        }

        .reset-btn {
            width: 100%;
            background: #1a365d;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
        }

        .reset-btn:hover {
            background: #2d3748;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #1a365d;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .instructions {
            background-color: #f7fafc;
            border-left: 4px solid #4299e1;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
        }

        .instructions ul {
            padding-left: 20px;
            margin-bottom: 10px;
        }

        .instructions li {
            margin-bottom: 5px;
        }

        /* Error and success messages */
        .alert-error {
            background-color: #fed7d7;
            color: #9b2c2c;
            padding: 12px 14px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #c53030;
        }

        .alert-success {
            background-color: #c6f6d5;
            color: #276749;
            padding: 12px 14px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #38a169;
        }

        .alert-error i,
        .alert-success i {
            margin-right: 8px;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="login-section">
            <div class="logo">
                <div class="logo-main">Digital Checklist System</div>
                <div class="system-name">BOSCH PORTAL</div>
            </div>

            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="index.php" class="back-link">
                        <i class="fas fa-sign-in-alt"></i> Go to Login Page
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($valid_token && !$message): ?>
                <div class="login-header">
                    <h1>Reset Your Password</h1>
                    <p>Create a new password for your account</p>
                </div>

                <div class="instructions">
                    <p><strong>Password Requirements:</strong></p>
                    <ul>
                        <li>At least 8 characters long</li>
                        <li>Should include uppercase and lowercase letters</li>
                        <li>Should include numbers</li>
                        <li>Should include special characters</li>
                    </ul>
                </div>

                <form id="resetPasswordForm" method="POST" action="">
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter new password" required>
                        <div class="password-strength" id="password-strength-text"></div>
                        <div class="strength-meter">
                            <div class="strength-fill" id="strength-fill"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                        <div class="password-strength" id="confirm-strength-text"></div>
                    </div>

                    <button type="submit" class="reset-btn">
                        <i class="fas fa-lock"></i> Reset Password
                    </button>
                </form>

                <a href="forgot_password.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Forgot Password
                </a>
            <?php elseif (!$valid_token && !$message): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i> Invalid or expired password reset link.
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="forgot_password.php" class="back-link">
                        <i class="fas fa-key"></i> Request New Password Reset
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetPasswordForm');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthText = document.getElementById('password-strength-text');
            const strengthFill = document.getElementById('strength-fill');
            const confirmText = document.getElementById('confirm-strength-text');
            
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    const strength = checkPasswordStrength(password);
                    
                    // Update strength text and bar
                    strengthText.textContent = strength.text;
                    strengthText.style.color = strength.color;
                    strengthFill.style.width = strength.percent + '%';
                    strengthFill.style.backgroundColor = strength.color;
                });
            }
            
            if (confirmPasswordInput && passwordInput) {
                confirmPasswordInput.addEventListener('input', function() {
                    const password = passwordInput.value;
                    const confirmPassword = this.value;
                    
                    if (confirmPassword === '') {
                        confirmText.textContent = '';
                    } else if (password === confirmPassword) {
                        confirmText.textContent = '✓ Passwords match';
                        confirmText.style.color = '#38a169';
                    } else {
                        confirmText.textContent = '✗ Passwords do not match';
                        confirmText.style.color = '#e53e3e';
                    }
                });
            }
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    const password = passwordInput ? passwordInput.value : '';
                    const confirmPassword = confirmPasswordInput ? confirmPasswordInput.value : '';
                    
                    // Check if passwords match
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        alert('Passwords do not match');
                        if (confirmPasswordInput) confirmPasswordInput.focus();
                        return false;
                    }
                    
                    // Check password strength
                    if (password.length < 8) {
                        e.preventDefault();
                        alert('Password must be at least 8 characters long');
                        if (passwordInput) passwordInput.focus();
                        return false;
                    }
                    
                    return true;
                });
            }
            
            // Auto-focus password field if it exists
            if (passwordInput) {
                passwordInput.focus();
            }
            
            function checkPasswordStrength(password) {
                let score = 0;
                let text = '';
                let color = '#e53e3e'; // Red
                let percent = 0;
                
                // Length check
                if (password.length >= 8) score++;
                if (password.length >= 12) score++;
                
                // Character type checks
                if (/[A-Z]/.test(password)) score++; // Uppercase
                if (/[a-z]/.test(password)) score++; // Lowercase
                if (/[0-9]/.test(password)) score++; // Numbers
                if (/[^A-Za-z0-9]/.test(password)) score++; // Special characters
                
                // Determine strength
                if (score <= 2) {
                    text = 'Weak';
                    color = '#e53e3e';
                    percent = 33;
                } else if (score <= 4) {
                    text = 'Medium';
                    color = '#ed8936';
                    percent = 66;
                } else {
                    text = 'Strong';
                    color = '#38a169';
                    percent = 100;
                }
                
                return { text, color, percent };
            }
        });
    </script>
</body>
</html>