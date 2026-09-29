<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is already logged in
if (isset($_SESSION['user'])) {
    // Redirect based on role
    if ($_SESSION['user']['role'] === 'admin') {
        header("Location: admin-dashboard.php");
        exit();
    } else {
        header("Location: dashboard.php");
        exit();
    }
}

$message = '';
$error = '';

// Handle forgot password form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    // Validate email
    if (empty($email)) {
        $error = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        try {
            // Connect to database
            require_once 'config/database.php';
            
            // Check if user exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate reset token
                $reset_token = bin2hex(random_bytes(32));
                $expiry_time = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token in database
                $updateStmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
                $updateStmt->execute([$reset_token, $expiry_time, $user['id']]);
                
                // Create reset link
                $reset_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                              "://$_SERVER[HTTP_HOST]" . 
                              dirname($_SERVER['PHP_SELF']) . 
                              "/reset_password.php?token=" . $reset_token;
                
                // Email content (you can replace this with actual email sending)
                $subject = "Password Reset Request - Digital Checklist System";
                $message_body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #1a365d; color: white; padding: 20px; text-align: center; }
                        .content { padding: 30px; background-color: #f9f9f9; }
                        .button { display: inline-block; padding: 12px 24px; background-color: #1a365d; color: white; text-decoration: none; border-radius: 4px; }
                        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                        .warning { color: #d32f2f; background-color: #ffebee; padding: 10px; border-radius: 4px; margin: 15px 0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Digital Checklist System</h2>
                            <p>Password Reset Request</p>
                        </div>
                        <div class='content'>
                            <h3>Hello " . htmlspecialchars($user['name']) . ",</h3>
                            <p>We received a request to reset your password for your Digital Checklist System account.</p>
                            <p>To reset your password, click the button below:</p>
                            <p style='text-align: center; margin: 30px 0;'>
                                <a href='" . $reset_link . "' class='button'>Reset Password</a>
                            </p>
                            <p>Or copy and paste this link into your browser:</p>
                            <p><code>" . $reset_link . "</code></p>
                            <div class='warning'>
                                <p><strong>Important:</strong> This link will expire in 1 hour for security reasons.</p>
                            </div>
                            <p>If you didn't request a password reset, you can safely ignore this email.</p>
                            <p>Thank you,<br>The Digital Checklist System Team</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message. Please do not reply to this email.</p>
                            <p>&copy; " . date('Y') . " Bosch. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                // For now, we'll store the reset link in session for demonstration
                // In production, you should send this via email
                $_SESSION['reset_link_demo'] = $reset_link;
                
                $message = "Password reset link has been generated! For demonstration purposes, the reset link is: " . 
                          "<a href='" . $reset_link . "'>" . $reset_link . "</a><br><br>" .
                          "In a production environment, this link would be sent to your email address.";
                
            } else {
                $error = "No account found with that email address";
            }
        } catch (PDOException $e) {
            error_log("Forgot password error: " . $e->getMessage());
            $error = "Database error occurred. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Digital Checklist System</title>
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

        input[type="email"] {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #cbd5e0;
            font-size: 15px;
            transition: border-color 0.2s;
            background: white;
        }

        input[type="email"]:focus {
            outline: none;
            border-color: #1a365d;
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

        .instructions h4 {
            margin-bottom: 8px;
            color: #2d3748;
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

        .alert-success a {
            color: #2b6cb0;
            text-decoration: underline;
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
                    <i class="fas fa-check-circle"></i> 
                    <?php echo $message; // Note: $message contains HTML, so no htmlspecialchars here ?>
                </div>
            <?php endif; ?>

            <div class="login-header">
                <h1>Forgot Password</h1>
                <p>Enter your email to reset your password</p>
            </div>

            <?php if (!$message): ?>
                <div class="instructions">
                    <h4>Instructions:</h4>
                    <ul>
                        <li>Enter your registered email address</li>
                        <li>We'll send you a password reset link</li>
                        <li>The link will expire in 1 hour</li>
                        <li>Check your email and follow the instructions</li>
                    </ul>
                </div>

                <form id="forgotPasswordForm" method="POST" action="">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="your.name@bosch.com" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <button type="submit" class="reset-btn">
                        <i class="fas fa-key"></i> Send Reset Link
                    </button>
                </form>
            <?php endif; ?>

            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotPasswordForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const email = document.getElementById('email').value.trim();
                    
                    if (!email) {
                        e.preventDefault();
                        alert('Please enter your email address');
                        document.getElementById('email').focus();
                        return false;
                    }
                    
                    // Basic email validation
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        e.preventDefault();
                        alert('Please enter a valid email address');
                        document.getElementById('email').focus();
                        return false;
                    }
                    
                    return true;
                });
            }
            
            // Auto-focus email field if it exists
            const emailField = document.getElementById('email');
            if (emailField) {
                emailField.focus();
            }
        });
    </script>
</body>
</html>