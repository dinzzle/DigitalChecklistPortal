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

$errors = [];

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validate inputs
    if (empty($email)) {
        $errors[] = "Email is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    if (empty($errors)) {
        try {
            // Connect to database
            require_once 'config/database.php';
            
            // Check if user exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'department' => $user['department'],
                    'employee_id' => $user['employee_id']
                ];
                
                // Update last login
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: admin-dashboard.php");
                    exit();
                } else {
                    header("Location: dashboard.php");
                    exit();
                }
            } else {
                $errors[] = "Invalid email or password";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $errors[] = "Database error occurred. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Digital Checklist System</title>
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
            max-width: 880px;
            display: flex;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            position: relative;
            z-index: 1;
        }

        .login-section {
            flex: 1.3;
            padding: 48px 52px;
        }

        .welcome-section {
            flex: 1;
            background: #1a365d;
            padding: 48px 36px;
            color: white;
        }

        .logo {
            margin-bottom: 36px;
        }

        .logo-main {
            font-size: 22px;
            font-weight: 600;
            color: #1a365d;
            letter-spacing: 0.3px;
        }

        .system-name {
            font-size: 13px;
            color: #718096;
            margin-top: 4px;
            letter-spacing: 0.5px;
        }

        .login-header {
            margin-bottom: 32px;
        }

        .login-header h1 {
            font-size: 26px;
            color: #1a365d;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .login-header p {
            color: #718096;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 22px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            color: #374151;
            font-weight: 500;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 11px 14px;
            border-radius: 6px;
            border: 1px solid #cbd5e0;
            font-size: 15px;
            transition: border-color 0.2s;
            background: white;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #1a365d;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            margin-top: 6px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .remember-me label {
            margin-bottom: 0;
            font-size: 14px;
            color: #4b5563;
            font-weight: 400;
            cursor: pointer;
        }

        .forgot-password {
            color: #1a365d;
            text-decoration: none;
            font-size: 14px;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .login-btn {
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
        }

        .login-btn:hover {
            background: #2d3748;
        }

        .signup-link {
            text-align: center;
            margin-top: 24px;
            color: #6b7280;
            font-size: 14px;
        }

        .signup-link a {
            color: #1a365d;
            text-decoration: none;
            font-weight: 500;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        /* Updated: Bosch logo styling */
        .bosch-logo {
            margin-bottom: 24px;
            text-align: left;
        }

        .bosch-logo img {
            max-height: 50px;
            max-width: 100%;
            object-fit: contain;
        }

        .welcome-header {
            font-size: 24px;
            margin-bottom: 14px;
            font-weight: 600;
            line-height: 1.3;
        }

        .welcome-text {
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 32px;
            opacity: 0.95;
        }

        .features {
            margin-top: 28px;
        }

        .feature {
            display: flex;
            align-items: flex-start;
            margin-bottom: 16px;
            font-size: 14px;
            line-height: 1.5;
        }

        .feature-icon {
            margin-right: 10px;
            margin-top: 2px;
            font-size: 14px;
        }

        .footer-text {
            margin-top: 40px;
            font-size: 12px;
            opacity: 0.7;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                max-width: 460px;
            }
            
            .welcome-section {
                order: -1;
                padding: 36px 28px;
            }
            
            .login-section {
                padding: 36px 28px;
            }
            
            .bosch-logo img {
                max-height: 40px;
            }
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

        .alert-info {
            background-color: #bee3f8;
            color: #2c5282;
            padding: 12px 14px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #4299e1;
        }

        .alert-error i,
        .alert-success i,
        .alert-info i {
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

            <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php 
                    foreach ($errors as $error) {
                        echo htmlspecialchars($error) . "<br>";
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_GET['signup']) && $_GET['signup'] === 'success'): ?>
                <div class="alert-success">
                    <i class="fas fa-check-circle"></i> Account created successfully! You can now log in.
                </div>
            <?php endif; ?>

            <div class="login-header">
                <h1>Welcome back</h1>
                <p>Please enter your credentials to continue</p>
            </div>

            <form id="loginForm" method="POST" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="your.name@bosch.com" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter password" required>
                </div>

                <div class="form-options">
                    <div class="remember-me">
                        <input type="checkbox" id="rememberMe" name="rememberMe">
                        <label for="rememberMe">Keep me signed in</label>
                    </div>
                    <a href="forgot_password.php" class="forgot-password">Forgot password?</a>
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Sign in
                </button>

                <div class="signup-link">
                    Don't have an account? <a href="signup.php">Sign up here</a>
                </div>
            </form>
        </div>

        <div class="welcome-section">
            <!-- Updated: Bosch logo image instead of text -->
            <div class="bosch-logo">
                <img src="boschlogo.jpg" alt="Bosch Logo" />
            </div>
            <h2 class="welcome-header">Digital Checklist System</h2>
            <p class="welcome-text">
                Manage your daily checklists, track task completion, and maintain compliance standards all in one place.
            </p>
            
            <div class="features">
                <div class="feature">
                    <i class="fas fa-check-circle feature-icon"></i>
                    <span>Track tasks in real-time</span>
                </div>
                <div class="feature">
                    <i class="fas fa-lock feature-icon"></i>
                    <span>Secure data management</span>
                </div>
                <div class="feature">
                    <i class="fas fa-chart-bar feature-icon"></i>
                    <span>Advanced analytics</span>
                </div>
                <div class="feature">
                    <i class="fas fa-user-shield feature-icon"></i>
                    <span>Role-based access control</span>
                </div>
            </div>
            
            <p class="footer-text">© <?php echo date('Y'); ?> Bosch. All rights reserved.</p>
        </div>
    </div>

    <script>
        // Simple client-side validation
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            
            // Check for remembered user in localStorage
            const rememberedUser = localStorage.getItem('rememberedUser');
            if (rememberedUser) {
                document.getElementById('email').value = rememberedUser;
                document.getElementById('rememberMe').checked = true;
            }
            
            loginForm.addEventListener('submit', function(e) {
                const email = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value;
                
                // Basic validation
                if (!email) {
                    e.preventDefault();
                    alert('Please enter your email');
                    document.getElementById('email').focus();
                    return false;
                }
                
                if (!password) {
                    e.preventDefault();
                    alert('Please enter your password');
                    document.getElementById('password').focus();
                    return false;
                }
                
                // Save to localStorage if remember me is checked
                if (document.getElementById('rememberMe').checked) {
                    localStorage.setItem('rememberedUser', email);
                } else {
                    localStorage.removeItem('rememberedUser');
                }
                
                return true;
            });
            
            // Auto-focus email field
            document.getElementById('email').focus();
        });
    </script>
</body>
</html>