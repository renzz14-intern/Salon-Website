<?php
/**
 * Admin Dashboard
 * Overview of salon operations with key metrics
 */

require_once '../config/auth.php';
requireAdmin();

$pdo = getPDOConnection();

// Sales metrics (approved only)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) as total FROM sales WHERE sale_date = CURDATE() AND status = 'approved'");
$stmt->execute(); $daily_sales = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) as total FROM sales WHERE YEARWEEK(sale_date,1) = YEARWEEK(CURDATE(),1) AND status = 'approved'");
$stmt->execute(); $weekly_sales = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) as total FROM sales WHERE MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE()) AND status = 'approved'");
$stmt->execute(); $monthly_sales = $stmt->fetch()['total'];

// Active staff
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM staff WHERE status='active'");
$stmt->execute(); $total_staff = $stmt->fetch()['total'];

// Pending sales badge
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sales WHERE status='pending'");
$stmt->execute(); $pending_count = $stmt->fetch()['total'];

// ── Pending bookings (from website) ──────────────────────────────────────────
$pending_bookings = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings WHERE status='pending'");
    $stmt->execute();
    $pending_bookings = $stmt->fetch()['total'];
} catch (Exception $e) { /* bookings table may not exist yet */ }

// Low stock
$stmt = $pdo->query("SELECT * FROM low_stock_products LIMIT 5");
$low_stock_items = $stmt->fetchAll();

// Recent approved sales
try {
    $stmt = $pdo->query("
        SELECT s.sale_date, s.approved_at, s.quantity, s.total_amount,
               s.payment_method, s.sale_id, s.customer_name, s.receipt_no,
               COALESCE(NULLIF(s.manual_staff_name,''), st.full_name, u.full_name) as staff_name,
               COALESCE(p.product_name, s.service_name) as item_name
        FROM   sales s
        LEFT JOIN users u    ON s.staff_id = u.user_id
        LEFT JOIN staff st   ON s.staff_id = st.staff_id
        LEFT JOIN products p ON s.product_id = p.product_id
        WHERE  s.status = 'approved'
        ORDER  BY s.approved_at DESC LIMIT 10
    ");
    $recent_sales = $stmt->fetchAll();
} catch (Exception $e) { $recent_sales = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Salon Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../assets/images/icon.png">
    <style>
        .receipt-badge { font-size:11px;font-weight:700;color:#4a6cf7;font-family:monospace;background:#f0f4ff;padding:2px 6px;border-radius:4px;white-space:nowrap; }
        .walk-in { font-size:11px;color:#bbb;font-style:italic; }
        .items-col { font-size:12px;color:#444;max-width:240px;word-break:break-word;line-height:1.5; }
        .card-bookings { border-left: 3px solid #f19e99; }
    </style>
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
            <a href="dashboard.php" class="active">&#x1F4CA; Dashboard</a>
            <a href="appointments_admin.php">&#x1F4C5; Appointments<?php if ($pending_bookings > 0): ?> <span style="background:#f19e99;color:white;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px;"><?php echo $pending_bookings; ?></span><?php endif; ?></a>
            <a href="pos.php">&#x1F4B0; Point of Sales</a>
            <a href="sales_reports.php">&#x1F4C8; Sales Reports</a>
            <a href="staff_reports_admin.php">&#x1F4CB; Staff Reports</a>
            <a href="inventory.php">&#x1F4E6; Inventory</a>
            <a href="staff_duties.php">&#x1F4CB; Staff Duties</a>
            <a href="staff_management.php">&#x1F465; Staff Management</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <h1>Dashboard Overview</h1>
            <div class="user-info">
                <span style="margin-right:15px;">Welcome, <strong><?php echo getCurrentUserName(); ?></strong></span>
                <a href="settings.php" class="btn btn-primary">Settings</a>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($pending_count > 0): ?>
        <div style="background:#fff3cd;color:#856404;padding:12px 18px;border-radius:8px;margin-bottom:16px;border:1px solid #ffc107;">
            &#x23F3; <strong><?php echo $pending_count; ?> sale(s) pending approval.</strong>
            <a href="pending_sales.php" style="margin-left:10px;font-weight:bold;color:#856404;text-decoration:underline;">Review Now &rarr;</a>
        </div>
        <?php endif; ?>

        <?php if ($pending_bookings > 0): ?>
        <div style="background:#fff0ef;color:#c0615d;padding:12px 18px;border-radius:8px;margin-bottom:20px;border:1px solid #f19e99;">
            &#x1F4C5; <strong><?php echo $pending_bookings; ?> new booking(s) awaiting confirmation.</strong>
            <a href="appointments_admin.php?filter=pending" style="margin-left:10px;font-weight:bold;color:#c0615d;text-decoration:underline;">Review Appointments &rarr;</a>
        </div>
        <?php endif; ?>

        <!-- Dashboard Cards -->
        <div class="dashboard-cards">
            <div class="card">
                <div class="card-icon blue">&#x1F4B5;</div>
                <h3>Today's Sales</h3>
                <div class="card-value">&#x20B1;<?php echo number_format($daily_sales, 2); ?></div>
            </div>
            <div class="card">
                <div class="card-icon green">&#x1F4C5;</div>
                <h3>Weekly Sales</h3>
                <div class="card-value">&#x20B1;<?php echo number_format($weekly_sales, 2); ?></div>
            </div>
            <div class="card">
                <div class="card-icon orange">&#x1F4CA;</div>
                <h3>Monthly Sales</h3>
                <div class="card-value">&#x20B1;<?php echo number_format($monthly_sales, 2); ?></div>
            </div>
            <div class="card">
                <div class="card-icon red">&#x1F465;</div>
                <h3>Active Staff</h3>
                <div class="card-value"><?php echo $total_staff; ?></div>
            </div>
            <div class="card card-bookings">
                <div class="card-icon" style="background:#fef0ef;">&#x1F4C5;</div>
                <h3>Pending Bookings</h3>
                <div class="card-value" style="color:#f19e99;"><?php echo $pending_bookings; ?></div>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <?php if (!empty($low_stock_items)): ?>
        <div class="content-box">
            <div class="alert alert-warning">&#x26A0;&#xFE0F; <strong>Low Stock Alert!</strong> <?php echo count($low_stock_items); ?> product(s) running low.</div>
            <h2>Low Stock Products</h2>
            <div class="table-container">
                <table>
                    <thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Threshold</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($low_stock_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                            <td><span class="badge badge-danger"><?php echo $item['quantity']; ?></span></td>
                            <td><?php echo $item['low_stock_threshold']; ?></td>
                            <td><a href="inventory.php?id=<?php echo $item['product_id']; ?>" class="btn btn-primary btn-sm">Restock</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Sales -->
        <div class="content-box">
            <h2>Recent Sales Transactions</h2>
            <?php if (empty($recent_sales)): ?>
                <p>No approved sales recorded yet.</p>
            <?php else: ?>
            <div class="table-container">
                <table>
                    <thead><tr><th>Receipt #</th><th>Date &amp; Time</th><th>Customer</th><th>Staff</th><th>Service / Products</th><th>Qty</th><th>Total</th><th>Payment</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent_sales as $sale): ?>
                        <tr>
                            <td><span class="receipt-badge"><?php echo !empty($sale['receipt_no']) ? htmlspecialchars($sale['receipt_no']) : ('#'.$sale['sale_id']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?><br><small style="color:#666;font-size:11px;"><?php echo (!empty($sale['approved_at']) && $sale['approved_at'] !== '0000-00-00 00:00:00') ? date('g:i A', strtotime($sale['approved_at'])) : 'N/A'; ?></small></td>
                            <td><?php echo !empty($sale['customer_name']) ? htmlspecialchars($sale['customer_name']) : '<span class="walk-in">Walk-in</span>'; ?></td>
                            <td><?php echo htmlspecialchars($sale['staff_name'] ?? 'N/A'); ?></td>
                            <td><div class="items-col"><?php echo htmlspecialchars($sale['item_name'] ?? 'N/A'); ?></div></td>
                            <td><?php echo $sale['quantity']; ?></td>
                            <td><strong>&#x20B1;<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                            <td><?php echo ucfirst($sale['payment_method']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>
</body>
</html>