<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}

$userName = $_SESSION['name'];
$userEmail = $_SESSION['email'];
$welcomeMessage = $_SESSION['welcome_message'] ?? '';
unset($_SESSION['welcome_message']);

// Get selected service from session if available
$selectedService = '';
if (isset($_SESSION['selected_service'])) {
    $selectedService = $_SESSION['selected_service'];
    unset($_SESSION['selected_service']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Serenity Spa - User Dashboard</title>
    <link rel="stylesheet" href="spa_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dashboard-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f3f0 0%, #e8ddd4 100%);
            padding: 20px;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #d4a574 0%, #c19660 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(212, 165, 116, 0.3);
        }

        .dashboard-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .dashboard-header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .dashboard-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
        }

        .dashboard-card h3 {
            color: #8b6f47;
            font-size: 1.5rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dashboard-card i {
            color: #d4a574;
            font-size: 1.3rem;
        }

        .service-booking {
            background: linear-gradient(135deg, #e8f5e8 0%, #d4f4d4 100%);
        }

        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: linear-gradient(135deg, #d4a574 0%, #c19660 100%);
            color: white;
            padding: 15px 20px;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .action-btn:hover {
            background: linear-gradient(135deg, #c19660 0%, #b08850 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 165, 116, 0.4);
        }

        .action-btn.secondary {
            background: linear-gradient(135deg, #8b6f47 0%, #7a5f3f 100%);
        }

        .action-btn.secondary:hover {
            background: linear-gradient(135deg, #7a5f3f 0%, #6b5437 100%);
        }

        .action-btn.danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .action-btn.danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
        }

        .selected-service {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 2px solid #d4a574;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .selected-service h4 {
            color: #8b6f47;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }

        .selected-service p {
            color: #6c5a3f;
            font-size: 1rem;
        }

        .user-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        }

        .user-info p {
            margin-bottom: 8px;
            font-size: 1rem;
            color: #5a4a3a;
        }

        .user-info strong {
            color: #8b6f47;
        }

        .welcome-alert {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 1px solid #c3e6cb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            color: #155724;
            font-size: 1.1rem;
            text-align: center;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header h1 {
                font-size: 2rem;
            }
            
            .dashboard-header p {
                font-size: 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php if (!empty($welcomeMessage)): ?>
            <div class="welcome-alert">
                <i class="fas fa-spa"></i> <?= htmlspecialchars($welcomeMessage); ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-header">
            <h1>Welcome, <?= htmlspecialchars($userName); ?>!</h1>
            <p>Your personal spa experience awaits</p>
        </div>

        <div class="dashboard-content">
            <!-- Service Booking Card -->
            <div class="dashboard-card service-booking">
                <h3><i class="fas fa-spa"></i> Book Your Service</h3>
                
                <?php if (!empty($selectedService)): ?>
                    <div class="selected-service">
                        <h4><i class="fas fa-check-circle"></i> Selected Service</h4>
                        <p><strong><?= htmlspecialchars($selectedService); ?></strong></p>
                        <p>Please proceed with booking or choose a different service below.</p>
                    </div>
                <?php endif; ?>

                <p>Choose from our relaxing spa treatments and book your appointment.</p>
                <div class="quick-actions">
                    <a href="booking.php" class="action-btn">
                        <i class="fas fa-calendar-plus"></i> Book Appointment
                    </a>
                    <a href="index.html" class="action-btn secondary">
                        <i class="fas fa-eye"></i> View All Services
                    </a>
                </div>
            </div>

            <!-- User Information Card -->
            <div class="dashboard-card user-info">
                <h3><i class="fas fa-user"></i> Your Information</h3>
                <p><strong>Name:</strong> <?= htmlspecialchars($userName); ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($userEmail); ?></p>
                <p><strong>Account Type:</strong> Client</p>
                <p><strong>Member Since:</strong> <?= date('F Y'); ?></p>
            </div>

            <!-- Quick Actions Card -->
            <div class="dashboard-card">
                <h3><i class="fas fa-lightning-bolt"></i> Quick Actions</h3>
                <div class="quick-actions">
                    <a href="appointments.php" class="action-btn">
                        <i class="fas fa-calendar"></i> My Appointments
                    </a>
                    <a href="profile.php" class="action-btn secondary">
                        <i class="fas fa-user-edit"></i> Edit Profile
                    </a>
                    <a href="index.html" class="action-btn secondary">
                        <i class="fas fa-home"></i> Back to Website
                    </a>
                </div>
            </div>

            <!-- Account Management Card -->
            <div class="dashboard-card">
                <h3><i class="fas fa-cogs"></i> Account Management</h3>
                <div class="quick-actions">
                    <a href="change_password.php" class="action-btn secondary">
                        <i class="fas fa-lock"></i> Change Password
                    </a>
                    <a href="logout.php" class="action-btn danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Check if there's a selected service from the main website
        const selectedService = sessionStorage.getItem('selectedService');
        if (selectedService && !<?= json_encode(!empty($selectedService)); ?>) {
            // Send selected service to PHP session via AJAX
            fetch('set_selected_service.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({service: selectedService})
            }).then(() => {
                // Clear from sessionStorage
                sessionStorage.removeItem('selectedService');
                // Refresh page to show selected service
                location.reload();
            });
        }
    </script>
</body>

</html>