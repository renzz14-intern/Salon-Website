<?php
/**
 * Staff Duties Tracking
 * Manual entry of staff performance data
 * UPDATED FOR NEW DATABASE STRUCTURE (staff + user_accounts tables)
 */

require_once '../config/auth.php';
requireAdmin();

$pdo = getPDOConnection();
$success = '';
$error = '';

// Handle delete
if (isset($_GET['delete'])) {
    $duty_id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM staff_duties WHERE duty_id = ?");
        $stmt->execute([$duty_id]);
        $success = 'Duty record deleted successfully!';
    } catch (Exception $e) {
        $error = 'Error deleting record: ' . $e->getMessage();
    }
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $duty_id = $_POST['duty_id'] ?? null;
    $duty_date = sanitizeInput($_POST['duty_date']);
    $staff_id = intval($_POST['staff_id']);
    $customers_treated = intval($_POST['customers_treated']);
    $additional_earnings = floatval($_POST['additional_earnings']);
    $notes = sanitizeInput($_POST['notes']);
    
    try {
        if ($duty_id) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE staff_duties 
                SET duty_date = ?, staff_id = ?, customers_treated = ?, 
                    additional_earnings = ?, notes = ?
                WHERE duty_id = ?
            ");
            $stmt->execute([$duty_date, $staff_id, $customers_treated, $additional_earnings, $notes, $duty_id]);
            $success = 'Duty record updated successfully!';
        } else {
            // Add new record
            $stmt = $pdo->prepare("
                INSERT INTO staff_duties (duty_date, staff_id, customers_treated, additional_earnings, notes)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$duty_date, $staff_id, $customers_treated, $additional_earnings, $notes]);
            $success = 'Duty record added successfully!';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get duty record for editing
$edit_duty = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM staff_duties WHERE duty_id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit_duty = $stmt->fetch();
}

// Get all duty records
$stmt = $pdo->query("
    SELECT sd.*, s.full_name as staff_name 
    FROM staff_duties sd
    JOIN staff s ON sd.staff_id = s.staff_id
    ORDER BY sd.duty_date DESC, sd.created_at DESC
");
$duties = $stmt->fetchAll();

// Get active staff
$stmt = $pdo->query("SELECT staff_id, full_name FROM staff WHERE status = 'active' ORDER BY full_name");
$staff_list = $stmt->fetchAll();

// Get staff performance summary
$stmt = $pdo->query("SELECT * FROM staff_performance");
$performance_summary = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Duties - Salon Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../assets/images/icon.png">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Salon Admin</h2>
                <p><?php echo getCurrentUserName(); ?></p>
            </div>
            <nav class="sidebar-menu">
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="appointments_admin.php">&#x1F4C5; Appointments</a>
                <a href="pos.php">💰 Point of Sales</a>
                <a href="sales_reports.php">📈 Sales Reports</a>
                <a href="staff_reports_admin.php">&#x1F4CB; Staff Reports</a>
                <a href="inventory.php">📦 Inventory</a>
                <a href="staff_duties.php" class="active">📋 Staff Duties</a>
                <a href="staff_management.php">👥 Staff Management</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <h1>Staff Duty Tracking</h1>
                <div class="user-info">
                    <span style="margin-right: 15px;">Welcome, <strong><?php echo getCurrentUserName(); ?></strong></span>
                    <a href="settings.php" class="btn btn-primary">⚙️ Settings</a>
                    <a href="../logout.php" class="btn-logout">Logout</a>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Add/Edit Duty Record Form -->
            <div class="content-box">
                <h2><?php echo $edit_duty ? 'Edit Duty Record' : 'Record Staff Duty'; ?></h2>
                <form method="POST" action="">
                    <?php if ($edit_duty): ?>
                        <input type="hidden" name="duty_id" value="<?php echo $edit_duty['duty_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date *</label>
                            <input type="date" name="duty_date" class="form-control" 
                                   value="<?php echo $edit_duty['duty_date'] ?? date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Staff Member *</label>
                            <select name="staff_id" class="form-control" required>
                                <option value="">-- Select Staff --</option>
                                <?php foreach ($staff_list as $staff): ?>
                                    <option value="<?php echo $staff['staff_id']; ?>"
                                            <?php echo ($edit_duty && $edit_duty['staff_id'] == $staff['staff_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($staff['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Customers Treated *</label>
                            <input type="number" name="customers_treated" class="form-control" min="0"
                                   value="<?php echo $edit_duty['customers_treated'] ?? 0; ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Additional Earnings / Commission (₱)</label>
                            <input type="number" name="additional_earnings" class="form-control" step="0.01" min="0"
                                   value="<?php echo $edit_duty['additional_earnings'] ?? 0; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Additional notes or comments about this duty record"><?php echo $edit_duty['notes'] ?? ''; ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <?php echo $edit_duty ? 'Update Record' : 'Add Record'; ?>
                    </button>
                    <?php if ($edit_duty): ?>
                        <a href="staff_duties.php" class="btn btn-warning">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Staff Performance Summary -->
            <div class="content-box">
                <h2>Staff Performance Summary</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Staff Name</th>
                                <th>Job Title</th>
                                <th>Days Worked</th>
                                <th>Total Customers</th>
                                <th>Additional Earnings</th>
                                <th>Sales Transactions</th>
                                <th>Sales Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($performance_summary)): ?>
                                <tr><td colspan="7" style="text-align: center;">No performance data available.</td></tr>
                            <?php else: ?>
                                <?php foreach ($performance_summary as $perf): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($perf['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($perf['job_title']); ?></td>
                                    <td><?php echo $perf['days_worked'] ?? 0; ?></td>
                                    <td><?php echo $perf['total_customers'] ?? 0; ?></td>
                                    <td>₱<?php echo number_format($perf['total_additional_earnings'] ?? 0, 2); ?></td>
                                    <td><?php echo $perf['total_sales_transactions'] ?? 0; ?></td>
                                    <td>₱<?php echo number_format($perf['total_sales_amount'] ?? 0, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Duty Records List -->
            <div class="content-box">
                <h2>Duty Records</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Staff Name</th>
                                <th>Customers Treated</th>
                                <th>Additional Earnings</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($duties)): ?>
                                <tr><td colspan="6" style="text-align: center;">No duty records found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($duties as $duty): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($duty['duty_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($duty['staff_name']); ?></td>
                                    <td><?php echo $duty['customers_treated']; ?></td>
                                    <td>₱<?php echo number_format($duty['additional_earnings'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($duty['notes']); ?></td>
                                    <td>
                                        <a href="?edit=<?php echo $duty['duty_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                        <a href="?delete=<?php echo $duty['duty_id']; ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Are you sure you want to delete this record?')">Delete</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>