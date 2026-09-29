<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = $_POST['fullName'] ?? '';
    $email = $_POST['email'] ?? '';
    $employeeId = $_POST['employeeId'] ?? '';
    $department = $_POST['department'] ?? '';
    $role = $_POST['selectedRole'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($fullName)) $errors[] = "Full name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($employeeId)) $errors[] = "Employee ID is required";
    if (empty($department)) $errors[] = "Department is required";
    if (empty($role)) $errors[] = "Role is required";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
    if ($password !== $confirmPassword) $errors[] = "Passwords do not match";
    
    // Check if email exists in database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Email already exists";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    if (empty($errors)) {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, department, employee_id, status, created_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())");
            $stmt->execute([$fullName, $email, $hashedPassword, $role, $department, $employeeId]);
            
            $_SESSION['success'] = "Account created successfully! You can now log in.";
            header("Location: index.php?signup=success");
            exit();
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['signup_errors'] = $errors;
        $_SESSION['signup_data'] = $_POST;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Digital Checklist System</title>
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
            background: rgba(245, 247, 250, 0.9);
            z-index: 0;
        }

        .container {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1;
        }

        .signup-section {
            padding: 40px;
        }

        .logo {
            margin-bottom: 30px;
            text-align: center;
        }

        .logo-main {
            font-size: 24px;
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

        .signup-header {
            margin-bottom: 30px;
        }

        .signup-header h1 {
            font-size: 26px;
            color: #1a365d;
            margin-bottom: 6px;
            font-weight: 600;
            text-align: center;
        }

        .signup-header p {
            color: #718096;
            font-size: 14px;
            text-align: center;
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

        .required {
            color: #dc2626;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #cbd5e0;
            font-size: 15px;
            transition: border-color 0.2s;
            background: white;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #1a365d;
            box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
        }

        .password-hint {
            font-size: 12px;
            color: #718096;
            margin-top: 5px;
        }

        .role-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .role-option {
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .role-option:hover {
            border-color: #1a365d;
            background: #f7fafc;
        }

        .role-option.selected {
            border-color: #1a365d;
            background: #1a365d;
            color: white;
        }

        .role-option i {
            font-size: 24px;
            margin-bottom: 8px;
            display: block;
        }

        .role-option h4 {
            font-size: 14px;
            margin-bottom: 4px;
        }

        .role-option p {
            font-size: 12px;
            color: inherit;
            opacity: 0.9;
        }

        .signup-btn {
            width: 100%;
            background: #1a365d;
            color: white;
            padding: 14px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
        }

        .signup-btn:hover {
            background: #2d3748;
        }

        .signup-btn:disabled {
            background: #a0aec0;
            cursor: not-allowed;
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            color: #718096;
            font-size: 14px;
        }

        .login-link a {
            color: #1a365d;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .error-message {
            color: #dc2626;
            font-size: 13px;
            margin-top: 5px;
            display: none;
        }

        .success-message {
            background-color: #ecfdf5;
            color: #059669;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
            border-left: 4px solid #10b981;
        }

        .terms-agreement {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 20px;
            font-size: 13px;
            color: #4a5568;
        }

        .terms-agreement input {
            margin-top: 3px;
        }

        .terms-agreement a {
            color: #1a365d;
            text-decoration: none;
        }

        .terms-agreement a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .container {
                max-width: 400px;
            }
            
            .signup-section {
                padding: 30px 25px;
            }
            
            .role-options {
                grid-template-columns: 1fr;
                max-width: 250px;
                margin-left: auto;
                margin-right: auto;
            }
        }

        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s;
        }

        .strength-weak {
            background: #f56565;
        }

        .strength-fair {
            background: #ed8936;
        }

        .strength-good {
            background: #48bb78;
        }

        .strength-strong {
            background: #38a169;
        }

        /* Error message styling */
        .alert-error {
            background-color: #fed7d7;
            color: #9b2c2c;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #c53030;
        }

        .alert-success {
            background-color: #c6f6d5;
            color: #276749;
            padding: 15px;
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
        <div class="signup-section">
            <div class="logo">
                <div class="logo-main">Digital Checklist System</div>
                <div class="system-name">BOSCH PORTAL</div>
            </div>

            <?php if (isset($_SESSION['signup_errors'])): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php 
                    foreach ($_SESSION['signup_errors'] as $error) {
                        echo htmlspecialchars($error) . "<br>";
                    }
                    unset($_SESSION['signup_errors']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <div class="signup-header">
                <h1>Create Account</h1>
                <p>Register for your Bosch account</p>
            </div>

            <form id="signupForm" method="POST" action="">
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" id="fullName" name="fullName" placeholder="Enter your full name" required 
                           value="<?php echo isset($_SESSION['signup_data']['fullName']) ? htmlspecialchars($_SESSION['signup_data']['fullName']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" placeholder="your.name@bosch.com" required
                           value="<?php echo isset($_SESSION['signup_data']['email']) ? htmlspecialchars($_SESSION['signup_data']['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Employee ID <span class="required">*</span></label>
                    <input type="text" id="employeeId" name="employeeId" placeholder="Enter your employee ID" required
                           value="<?php echo isset($_SESSION['signup_data']['employeeId']) ? htmlspecialchars($_SESSION['signup_data']['employeeId']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Department <span class="required">*</span></label>
                    <select id="department" name="department" required>
                        <option value="">Select Department</option>
                        <option value="production" <?php echo (isset($_SESSION['signup_data']['department']) && $_SESSION['signup_data']['department'] == 'production') ? 'selected' : ''; ?>>Production</option>
                        <option value="quality" <?php echo (isset($_SESSION['signup_data']['department']) && $_SESSION['signup_data']['department'] == 'quality') ? 'selected' : ''; ?>>Quality Control</option>
                        <option value="maintenance" <?php echo (isset($_SESSION['signup_data']['department']) && $_SESSION['signup_data']['department'] == 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="assembly" <?php echo (isset($_SESSION['signup_data']['department']) && $_SESSION['signup_data']['department'] == 'assembly') ? 'selected' : ''; ?>>Assembly</option>
                        <option value="it" <?php echo (isset($_SESSION['signup_data']['department']) && $_SESSION['signup_data']['department'] == 'it') ? 'selected' : ''; ?>>IT</option>
                        <option value="management" <?php echo (isset($_SESSION['signup_data']['department']) && $_SESSION['signup_data']['department'] == 'management') ? 'selected' : ''; ?>>Management</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Role <span class="required">*</span></label>
                    <div class="role-options">
                        <!-- Only Admin and Operator options -->
                        <div class="role-option" data-role="operator" onclick="selectRole('operator')">
                            <i class="fas fa-user"></i>
                            <h4>Operator</h4>
                            <p>Perform daily checklists and tasks</p>
                        </div>
                        <div class="role-option" data-role="admin" onclick="selectRole('admin')">
                            <i class="fas fa-user-shield"></i>
                            <h4>Administrator</h4>
                            <p>Manage system and users</p>
                        </div>
                    </div>
                    <input type="hidden" id="selectedRole" name="selectedRole" required 
                           value="<?php echo isset($_SESSION['signup_data']['selectedRole']) ? htmlspecialchars($_SESSION['signup_data']['selectedRole']) : ''; ?>">
                    <div class="error-message" id="roleError">Please select a role</div>
                </div>

                <div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" placeholder="Create a password" required>
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="password-hint">Minimum 8 characters with letters and numbers</div>
                    <div class="error-message" id="passwordError">Password is required</div>
                </div>

                <div class="form-group">
                    <label>Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" required>
                    <div class="error-message" id="confirmPasswordError">Passwords do not match</div>
                </div>

                <div class="terms-agreement">
                    <input type="checkbox" id="agreeTerms" name="agreeTerms" required>
                    <label for="agreeTerms">
                        I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                    </label>
                    <div class="error-message" id="termsError">You must agree to the terms</div>
                </div>

                <button type="submit" class="signup-btn" id="signupButton">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>

                <div class="login-link">
                    Already have an account? <a href="index.php">Sign in here</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Select role function
        function selectRole(role) {
            console.log('Selecting role:', role);
            
            // Remove selected class from all role options
            const roleOptions = document.querySelectorAll('.role-option');
            roleOptions.forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            const selectedOption = document.querySelector(`[data-role="${role}"]`);
            if (selectedOption) {
                selectedOption.classList.add('selected');
            }
            
            // Set hidden input value
            document.getElementById('selectedRole').value = role;
            
            // Hide error if showing
            document.getElementById('roleError').style.display = 'none';
            
            console.log('Role selected:', document.getElementById('selectedRole').value);
        }

        // Check password strength
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('strengthBar');
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            // Update strength bar
            strengthBar.className = 'strength-bar';
            if (strength === 0) {
                strengthBar.style.width = '0%';
            } else if (strength <= 2) {
                strengthBar.style.width = '25%';
                strengthBar.classList.add('strength-weak');
            } else if (strength === 3) {
                strengthBar.style.width = '50%';
                strengthBar.classList.add('strength-fair');
            } else if (strength === 4) {
                strengthBar.style.width = '75%';
                strengthBar.classList.add('strength-good');
            } else {
                strengthBar.style.width = '100%';
                strengthBar.classList.add('strength-strong');
            }
            
            return strength >= 3; // Minimum fair strength required
        }

        // Validate email domain
        function validateBoschEmail(email) {
            return email.toLowerCase().endsWith('@bosch.com');
        }

        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded');
            
            // Pre-select role if it was previously selected (after form submission with errors)
            const selectedRoleValue = document.getElementById('selectedRole').value;
            if (selectedRoleValue) {
                console.log('Pre-selecting role:', selectedRoleValue);
                selectRole(selectedRoleValue);
            }
            
            // Password strength check
            const passwordInput = document.getElementById('password');
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    checkPasswordStrength(this.value);
                });
            }
            
            // Form validation
            const signupForm = document.getElementById('signupForm');
            const signupButton = document.getElementById('signupButton');
            
            if (signupForm) {
                signupForm.addEventListener('submit', function(e) {
                    // Basic validation before submitting to PHP
                    const selectedRole = document.getElementById('selectedRole').value;
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirmPassword').value;
                    
                    // Check if role is selected
                    if (!selectedRole) {
                        e.preventDefault();
                        document.getElementById('roleError').style.display = 'block';
                        return false;
                    }
                    
                    // Check password match
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        document.getElementById('confirmPasswordError').style.display = 'block';
                        return false;
                    }
                    
                    // If all good, form will submit to PHP
                    return true;
                });
            }
            
            // Real-time validation for confirm password
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const passwordMatchError = document.getElementById('confirmPasswordError');
            
            if (confirmPasswordInput) {
                confirmPasswordInput.addEventListener('input', function() {
                    const password = document.getElementById('password').value;
                    if (this.value && password && this.value !== password) {
                        passwordMatchError.textContent = 'Passwords do not match';
                        passwordMatchError.style.display = 'block';
                    } else {
                        passwordMatchError.style.display = 'none';
                    }
                });
            }
            
            // Clear session data after page load
            <?php unset($_SESSION['signup_data']); ?>
        });
    </script>
</body>
</html>