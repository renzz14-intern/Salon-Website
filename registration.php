<?php
session_start();

// DEBUG: Let's see what we have in session
error_log("DEBUG: Session active_form: " . ($_SESSION['active_form'] ?? 'not set'));
error_log("DEBUG: Session register_error: " . ($_SESSION['register_error'] ?? 'not set'));

// Key Variables - Store in local variables but DON'T clear session yet
$errors = [
    'login'=> $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? ''
];
$successMessage = $_SESSION['success_message'] ?? ''; // Displays success messages
$logoutMessage = $_SESSION['logout_message'] ?? '';
$activeForm = $_SESSION['active_form'] ?? 'login'; // Determines which form to show 
$formData = $_SESSION['form_data'] ?? [];
$loginEmail = $_SESSION['login_email'] ?? '';

// DEBUG: Let's see what activeForm we're using
error_log("DEBUG: activeForm variable: " . $activeForm);

function showError($error){
    return !empty($error) ? "<p class='error-message'>$error</p>": '';
}

function showSuccess($message){
    return !empty($message) ? "<p class='success-message'>$message</p>": '';
}

function showLogout($message){
    return !empty($message) ? "<p class='logout-message'>$message</p>": '';
}

function isActiveForm($formName, $activeForm){
    return $formName === $activeForm ? 'active' : '';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Levy Branzuela Salon</title>
    <link rel="stylesheet" href="spa_style.css">
    <link rel="icon" href="images/Untitled.png" type="image/x-icon">
</head>

<body>
    <div class="main-container">
        <!-- Left side with logo and branding -->
        <div class="left-panel">
            <div class="logo-section">
                <img src="images/logo2.png" alt="Levy Branzuela Logo" class="logo">
                <h1><span>LEVY<br>BRANZUELA</br>SALON</span></h1>
                <div class="welcome-text">
                    <p>"Makeup is art. Makeup is passion. Makeup is expression."</p>
                </div>
            </div>
        </div>

        <!-- Right side with forms -->
         <!-- Login form -->
        <div class="right-panel">
            <div class="form-container">
                <div class="form-box <?= isActiveForm('login', $activeForm); ?>" id="login-form">
                    <form action="login_register.php" method="post">
                        <h2>Login</h2>
                        <p class="form-subtitle">Sign in to your account</p>
                        <?= showSuccess($successMessage); ?>
                        <?= showLogout($logoutMessage); ?>
                        <?= showError($errors['login']); ?>
                        <div class="input-group">
                            <input type="email" name="email" placeholder="Email Address" value="<?= htmlspecialchars($loginEmail); ?>" required>
                        </div>
                        <div class="input-group">
                            <input type="password" name="password" id="login-password" placeholder="Password" required>
                            <span class="password-toggle" onclick="togglePassword('login-password', this)">
                                <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </span>
                        </div>
                        <button type="submit" name="login">Sign In</button>
                        <!-- In the LOGIN form -->
                        <div class="form-switch">
                        <p>Don't have an account? <a href="javascript:void(0)" onclick="showForm('register-form'); return false;">Create Account</a></p>
                        </div>
                    </form>
                </div>
                <!-- Registration form -->
                <div class="form-box <?= isActiveForm('register', $activeForm); ?>" id="register-form">
                    <form action="login_register.php" method="post">
                        <h2>Register</h2>
                        <p class="form-subtitle">Create your account</p>
                        <?= showError($errors['register']); ?>
                        <div class="input-group">
                            <input type="text" name="name" placeholder="Full Name" value="<?= htmlspecialchars($formData['name'] ?? ''); ?>" required>
                        </div>
                        <div class="input-group">
                            <input type="email" name="email" placeholder="Email Address" value="<?= htmlspecialchars($formData['email'] ?? ''); ?>" required>
                        </div>
                        <div class="input-group">
                            <input type="tel" name="phone" placeholder="Phone Number" value="<?= htmlspecialchars($formData['phone'] ?? ''); ?>" required>
                        </div>
                        <div class="input-group">
                            <input type="date" name="birth_date" placeholder="Birth Date" value="<?= htmlspecialchars($formData['birth_date'] ?? ''); ?>" required>
                        </div>
                        <div class="input-group">
                            <input type="password" name="password" id="register-password" placeholder="Password (minimum 6 characters)" required>
                            <span class="password-toggle" onclick="togglePassword('register-password', this)">
                                <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </span>
                        </div>
                        <div class="input-group">
                            <input type="password" name="confirm_password" id="confirm-password" placeholder="Confirm Password" required>
                            <span class="password-toggle" onclick="togglePassword('confirm-password', this)">
                                <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </span>
                        </div>
                        <!-- Hidden field for role - automatically set to 'user' -->
                        <input type="hidden" name="role" value="user">
                        <button type="submit" name="register">Register</button>
                        <!-- In the REGISTER form -->
                        <div class="form-switch">
                        <p>Already have an account? <a href="javascript:void(0)" onclick="showForm('login-form'); return false;">Sign In</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Define togglePassword function directly in HTML before loading script.js
        function togglePassword(inputId, toggleElement) {
            const input = document.getElementById(inputId);
            if (!input || !toggleElement) {
                console.error('Password toggle elements not found');
                return;
            }
            
            const icon = toggleElement.querySelector('.eye-icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                toggleElement.classList.add('visible');
                // Change to eye-off icon
                icon.innerHTML = `
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                `;
            } else {
                input.type = 'password';
                toggleElement.classList.remove('visible');
                // Change back to eye icon
                icon.innerHTML = `
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                `;
            }
        }
    </script>
    <script src="script.js"></script>
</body>

</html>

<?php
// Clear messages AFTER displaying them - moved to the very end
unset($_SESSION['login_error']);
unset($_SESSION['register_error']);
unset($_SESSION['success_message']);
unset($_SESSION['logout_message']);
unset($_SESSION['form_data']);
unset($_SESSION['login_email']);
unset($_SESSION['active_form']);
?>