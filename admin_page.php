<?php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$adminName = $_SESSION['name'];
$adminEmail = $_SESSION['email'];
$welcomeMessage = $_SESSION['welcome_message'] ?? '';
unset($_SESSION['welcome_message']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serenity Spa - Admin Dashboard</title>
    <link rel="stylesheet" href="spa_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dashboard-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f3f0 0%, #e8ddd4 100%);
            padding: 20px;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #8b6f47 0%, #7a5f3f 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(139, 111, 71, 0.3);
            position: relative;
            overflow: hidden;
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            transform: rotate(45deg);
        }

        .dashboard-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .dashboard-header p {
            font-size: 1.2rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .admin-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-top: 10px;
            position: relative;
            z-index: 1;
        }

        .dashboard-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #d4a574 0%, #c19660 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 6px 20px rgba(212, 165, 116, 0.3);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(212, 165, 116, 0.4);
        }

        .stat-card .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            font-size: 1rem;
            opacity: 0.9;
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
            background: linear-gradient(135deg, #8b6f47 0%, #7a5f3f 100%);
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
            background: linear-gradient(135deg, #7a5f3f 0%, #6b5437 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 111, 71, 0.4);
        }

        .action-btn.primary {
            background: linear-gradient(135deg, #d4a574 0%, #c19660 100%);
        }

        .action-btn.primary:hover {
            background: linear-gradient(135deg, #c19660 0%, #b08850 100%);
        }

        .action-btn.success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .action-btn.success:hover {
            background: linear-gradient(135deg, #20c997 0%, #1e7e34 100%);
        }

        .action-btn.danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .action-btn.danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
        }

        .admin-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        }

        .admin-info p {
            margin-bottom: 8px;
            font-size: 1rem;
            color: #5a4a3a;
        }

        .admin-info strong {
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

        .management-card {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        }

        .system-card {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
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
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dashboard-header h1 {
                font-size: 2rem;
            }
            
            .dashboard-header p {
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php if (!empty($welcomeMessage)): ?>
            <div class="welcome-alert">
                <i class="fas fa-crown"></i> <?= htmlspecialchars($welcomeMessage); ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-header">
            <h1>Admin Dashboard</h1>
            <p>Welcome back, <?= htmlspecialchars($adminName); ?>!</p>
            <span class="admin-badge">
                <i class="fas fa-shield-alt"></i> Administrator
            </span>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">127</div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">45</div>
                <div class="stat-label">Active Clients</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">12</div>
                <div class="stat-label">Today's Appointments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">₱15,750</div>
                <div class="stat-label">Today's Revenue</div>
            </div>
        </div>

        <div class="dashboard-content">
            <!-- Booking Management Card -->
            <div class="dashboard-card management-card">
                <h3><i class="fas fa-calendar-check"></i> Booking Management</h3>
                <p>Manage customer appointments and spa service bookings.</p>
                <div class="quick-actions">
                    <a href="manage_bookings.php" class="action-btn primary">
                        <i class="fas fa-calendar"></i> View All Bookings
                    </a>
                    <a href="add_booking.php" class="action-btn success">
                        <i class="fas fa-plus"></i> Add New Booking
                    </a>
                    <a href="calendar.php" class="action-btn primary">
                        <i class="fas fa-calendar-alt"></i> Calendar View
                    </a>
                </div>
            </div>

            <!-- Customer Management Card -->
            <div class="dashboard-card">
                <h3><i class="fas fa-users"></i> Customer Management</h3>
                <p>Manage customer accounts and view customer information.</p>
                <div class="quick-actions">
                    <a href="manage_customers.php" class="action-btn primary">
                        <i class="fas fa-list"></i> All Customers
                    </a>
                    <a href="add_customer.php" class="action-btn success">
                        <i class="fas fa-user-plus"></i> Add Customer
                    </a>
                    <a href="customer_reports.php" class="action-btn primary">
                        <i class="fas fa-chart-bar"></i> Customer Reports
                    </a>
                </div>
            </div>

            <!-- Service Management Card -->
            <div class="dashboard-card">
                <h3><i class="fas fa-spa"></i> Service Management</h3>
                <p>Manage spa services, pricing, and availability.</p>
                <div class="quick-actions">
                    <a href="manage_services.php" class="action-btn primary">
                        <i class="fas fa-list-alt"></i> All Services
                    </a>
                    <a href="add_service.php" class="action-btn success">
                        <i class="fas fa-plus-circle"></i> Add Service
                    </a>
                    <a href="service_analytics.php" class="action-btn primary">
                        <i class="fas fa-analytics"></i> Service Analytics
                    </a>
                </div>
            </div>

            <!-- Admin Information Card -->
            <div class="dashboard-card admin-info">
                <h3><i class="fas fa-user-shield"></i> Admin Information</h3>
                <p><strong>Name:</strong> <?= htmlspecialchars($adminName); ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($adminEmail); ?></p>
                <p><strong>Role:</strong> Administrator</p>
                <p><strong>Last Login:</strong> <?= date('F j, Y g:i A'); ?></p>
            </div>

            <!-- Reports & Analytics Card -->
            <div class="dashboard-card">
                <h3><i class="fas fa-chart-line"></i> Reports & Analytics</h3>
                <p>View detailed reports and business analytics.</p>
                <div class="quick-actions">
                    <a href="revenue_report.php" class="action-btn primary">
                        <i class="fas fa-money-bill-wave"></i> Revenue Reports
                    </a>
                    <a href="booking_analytics.php" class="action-btn primary">
                        <i class="fas fa-chart-pie"></i> Booking Analytics
                    </a>
                    <a href="export_data.php" class="action-btn success">
                        <i class="fas fa-download"></i> Export Data
                    </a>
                </div>
            </div>

            <!-- System Management Card -->
            <div class="dashboard-card system-card">
                <h3><i class="fas fa-cogs"></i> System Management</h3>
                <p>System settings and administrative tools.</p>
                <div class="quick-actions">
                    <a href="system_settings.php" class="action-btn">
                        <i class="fas fa-sliders-h"></i> System Settings
                    </a>
                    <a href="backup.php" class="action-btn">
                        <i class="fas fa-database"></i> Backup Data
                    </a>
                    <a href="index.html" class="action-btn primary">
                        <i class="fas fa-home"></i> View Website
                    </a>
                    <a href="logout.php" class="action-btn danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>