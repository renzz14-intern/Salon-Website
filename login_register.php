<?php
session_start();
require_once 'config.php';

// Function to validate email format
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Function to validate password strength
function validatePassword($password) {
    return strlen($password) >= 6; // At least 6 characters
}

// Function to validate phone number (accepts any reasonable phone format)
function validatePhone($phone) {
    // Remove any spaces, dashes, parentheses, dots, and other common separators
    $cleanPhone = preg_replace('/[\s\-\(\)\.\+]+/', '', $phone);
    
    // Check if it contains only digits after cleaning
    if (!preg_match('/^\d+$/', $cleanPhone)) {
        return false;
    }
    
    // Check length - most phone numbers are 11 digits
    $length = strlen($cleanPhone);
    if ($length < 11 || $length > 11) {
        return false;
    }
    
    return true;
}

// Function to validate birth date
function validateBirthDate($birthDate) {
    // Check if the date is valid
    $date = DateTime::createFromFormat('Y-m-d', $birthDate);
    if (!$date || $date->format('Y-m-d') !== $birthDate) {
        return false;
    }
    
    // Check if the person is at least 12 years old
    $today = new DateTime();
    $age = $today->diff($date)->y;
    
    if ($age < 12) {
        return false;
    }
    
    // Check if the date is not in the future
    if ($date > $today) {
        return false;
    }
    
    return true;
}

// Function to sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// Function to format phone number for storage (flexible formatting)
function formatPhone($phone) {
    // Remove any spaces, dashes, parentheses, dots, and other separators
    $cleanPhone = preg_replace('/[\s\-\(\)\.\+]+/', '', $phone);
    
    // Keep only digits
    $digitsOnly = preg_replace('/[^\d]/', '', $cleanPhone);
    
    return $digitsOnly;
}

// Handle Registration
if (isset($_POST['register'])) {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $birthDate = sanitizeInput($_POST['birth_date']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $role = 'user'; // Automatically set to 'user' for all registrations

    // Store form data for repopulation on error (excluding passwords)
    $_SESSION['form_data'] = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'birth_date' => $birthDate
    ];
    $_SESSION['active_form'] = 'register';

    // Validation Array
    $errors = [];

    if (empty($name) || strlen($name) < 2) {
        $errors[] = 'Name must be at least 2 characters long.';
    }

    if (empty($email) || !validateEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($phone) || !validatePhone($phone)) {
        $errors[] = 'Please enter a valid phone number.';
    }

    if (empty($birthDate) || !validateBirthDate($birthDate)) {
        $errors[] = 'Please enter a valid birth date. You must be at least 12 years old.';
    }

    if (empty($password) || !validatePassword($password)) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if (empty($confirmPassword)) {
        $errors[] = 'Please confirm your password.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match. Please try again.';
    }

    // Handle Validation Errors
    if (!empty($errors)) {
        $errorMessage = implode(' ', $errors);
        $_SESSION['register_error'] = $errorMessage;
        $_SESSION['active_form'] = 'register';
        header("Location: registration.php");
        exit();
    }
    
    // Database Operations
    try {
        // Check if email is already registered using prepared statements
        $stmt = $conn->prepare("SELECT email FROM customer_accounts WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Email already registered
            $_SESSION['register_error'] = 'This email is already registered. Please use a different email or try logging in.';
            $_SESSION['active_form'] = 'register';
            $_SESSION['form_data'] = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'birth_date' => $birthDate
            ];
            header("Location: registration.php");
            exit();
        } else {
            // Check if phone number is already registered
            $formattedPhone = formatPhone($phone);
            $stmt = $conn->prepare("SELECT phone FROM customer_accounts WHERE phone = ?");
            $stmt->bind_param("s", $formattedPhone);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $_SESSION['register_error'] = 'This phone number is already registered. Please use a different phone number.';
                $_SESSION['active_form'] = 'register';
                $_SESSION['form_data'] = [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'birth_date' => $birthDate
                ];
                header("Location: registration.php");
                exit();
            }

            // Hash password securely
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user with all fields
            $stmt = $conn->prepare("INSERT INTO customer_accounts (name, email, phone, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $formattedPhone, $hashed_password);
            
            if ($stmt->execute()) {
                // Success - redirect to login form
                unset($_SESSION['form_data']);
                $_SESSION['active_form'] = 'login';
                $_SESSION['success_message'] = 'Your account has been created successfully. Please log in to continue.';
                $_SESSION['login_email'] = $email;
                header("Location: registration.php");
                exit();
            } else {
                throw new Exception('Database error occurred during registration.');
            }
        }
        
    } catch (Exception $e) {
        $_SESSION['register_error'] = 'Registration failed. Please try again later.';
        $_SESSION['active_form'] = 'register';
        // Maintain form data for repopulation (excluding passwords)
        $_SESSION['form_data'] = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'birth_date' => $birthDate
        ];
        header("Location: registration.php");
        exit();
    }
}

// Handle Login
if (isset($_POST['login'])) {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];

    // Validation
    $errors = [];

    if (empty($email) || !validateEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors[] = 'Please enter your password.';
    }

    if (!empty($errors)) {
        $errorMessage = implode(' ', $errors);
        $_SESSION['login_error'] = $errorMessage;
        $_SESSION['login_email'] = $email;
        $_SESSION['active_form'] = 'login';
        header("Location: registration.php");
        exit();
    }

    try {
        // Use prepared statement to get user data
        $stmt = $conn->prepare("SELECT id, name, email, phone, password FROM customer_accounts WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Verify password
            if (password_verify($password, $user["password"])) {
                // Set session variables
                $_SESSION["user_id"] = $user['id'];
                $_SESSION["name"] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['phone'] = $user['phone'];
                $_SESSION['login_time'] = time();

                // Clear any error messages
                unset($_SESSION['login_error']);
                unset($_SESSION['register_error']);

                // Redirect to main website
                $userName = urlencode($user['name']);
                header("Location: index.html?login_success=1&user_name=" . $userName);
                exit();
            }
        }

        // Login failed
        $_SESSION['login_error'] = 'Invalid email or password. Please check your credentials and try again.';
        $_SESSION['login_email'] = $email;
        $_SESSION['active_form'] = 'login';
        header("Location: registration.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['login_error'] = 'Login failed. Please try again later.';
        $_SESSION['login_email'] = $email;
        $_SESSION['active_form'] = 'login';
        header("Location: registration.php");
        exit();
    }
}

// If no POST data, redirect to main page
header("Location: registration.php");
exit();
?>