<?php
/**
 * Staff — My Sales Report
 * Same UI as admin Sales Reports but filtered to the logged-in staff member only.
 */

require_once '../config/auth.php';
requireLogin();

if (isAdmin()) {
    header("Location: /salon_management_system/admin/sales_reports.php");
    exit();
}

$perms = getStaffPermissions();
if (empty($perms['reports'])) {
    header("Location: dashboard.php");
    exit();
}

$pdo      = getPDOConnection();
$nav      = getStaffNavLinks();
$staff_id = getCurrentStaffId();

// ── FILTER PARAMS ──────────────────────────────────────────────────────────────
$start_date  = $_GET['start_date']  ?? date('Y-m-01');
$end_date    = $_GET['end_date']    ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'detailed';

// ── DETAILED REPORT ────────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        SELECT s.*,
               COALESCE(NULLIF(s.manual_staff_name, ''), st.full_name) as staff_name,
               COALESCE(p.product_name, s.service_name)                as item_name,
               s.customer_name,
               s.receipt_no
        FROM   sales s
        LEFT JOIN staff    st ON s.staff_id    = st.staff_id
        LEFT JOIN products p  ON s.product_id  = p.product_id
        WHERE  s.staff_id  = ?
          AND  s.sale_date BETWEEN ? AND ?
          AND  s.status    = 'approved'
        ORDER  BY s.approved_at DESC, s.sale_date DESC
    ");
    $stmt->execute([$staff_id, $start_date, $end_date]);
    $sales = $stmt->fetchAll();
} catch (Exception $e) {
    $stmt = $pdo->prepare("
        SELECT s.*,
               COALESCE(NULLIF(s.manual_staff_name, ''), st.full_name) as staff_name,
               COALESCE(p.product_name, s.service_name)                as item_name,
               NULL as customer_name,
               NULL as receipt_no
        FROM   sales s
        LEFT JOIN staff    st ON s.staff_id   = st.staff_id
        LEFT JOIN products p  ON s.product_id = p.product_id
        WHERE  s.staff_id  = ?
          AND  s.sale_date BETWEEN ? AND ?
          AND  s.status    = 'approved'
        ORDER  BY s.approved_at DESC, s.sale_date DESC
    ");
    $stmt->execute([$staff_id, $start_date, $end_date]);
    $sales = $stmt->fetchAll();
}

$total_sales        = array_sum(array_column($sales, 'total_amount'));
$total_transactions = count($sales);
$average_sale       = $total_transactions > 0 ? $total_sales / $total_transactions : 0;

// ── DAILY SUMMARY ──────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT
        sale_date,
        COUNT(*)          as transactions,
        SUM(total_amount) as daily_total
    FROM   sales
    WHERE  staff_id  = ?
      AND  sale_date BETWEEN ? AND ?
      AND  status    = 'approved'
    GROUP  BY sale_date
    ORDER  BY sale_date DESC
");
$stmt->execute([$staff_id, $start_date, $end_date]);
$daily_breakdown = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - Salon Staff</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../assets/images/icon.png">
    <style>
        .receipt-badge {
            font-size: 11px; font-weight: 700; color: #4a6cf7;
            font-family: monospace; background: #f0f4ff;
            padding: 2px 6px; border-radius: 4px; white-space: nowrap;
        }
        .customer-name { font-size: 13px; color: #333; }
        .walk-in       { font-size: 11px; color: #bbb; font-style: italic; }
        .items-col     { font-size: 12px; color: #444; max-width: 260px; word-break: break-word; line-height: 1.5; }
    </style>
</head>
<body>
<div class="dashboard-container">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>💇 Salon Staff</h2>
            <p><?php echo getCurrentUserName(); ?></p>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php">🏠 Dashboard</a>
            <?php foreach ($nav as $link): ?>
                <a href="<?php echo $link['href']; ?>"
                   class="<?php echo basename($link['href']) === 'staff_sales_report.php' ? 'active' : ''; ?>">
                    <?php echo $link['icon'] . ' ' . $link['label']; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- Main Content -->  
    <main class="main-content">
        <div class="top-bar">
            <h1>📈 Sales Reports</h1>
            <div class="user-info">
                <span style="margin-right:15px;">Welcome, <strong><?php echo getCurrentUserName(); ?></strong></span>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="content-box">
            <h2>Filter Reports</h2>
            <form method="GET" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control"
                               value="<?php echo $start_date; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control"
                               value="<?php echo $end_date; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Report Type</label>
                        <select name="report_type" class="form-control">
                            <option value="detailed" <?php echo $report_type === 'detailed' ? 'selected' : ''; ?>>Detailed</option>
                            <option value="summary"  <?php echo $report_type === 'summary'  ? 'selected' : ''; ?>>Daily Summary</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;">
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                    </div>
                </div>
            </form>

            <!-- Quick filters -->
            <div style="margin-top:15px;display:flex;gap:8px;flex-wrap:wrap;">
                <a href="?start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d'); ?>&report_type=<?php echo $report_type; ?>" class="btn btn-sm btn-primary">Today</a>
                <a href="?start_date=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>&report_type=<?php echo $report_type; ?>" class="btn btn-sm btn-primary">Last 7 Days</a>
                <a href="?start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-d'); ?>&report_type=<?php echo $report_type; ?>" class="btn btn-sm btn-primary">This Month</a>
                <a href="?start_date=<?php echo date('Y-m-01', strtotime('-1 month')); ?>&end_date=<?php echo date('Y-m-t', strtotime('-1 month')); ?>&report_type=<?php echo $report_type; ?>" class="btn btn-sm btn-primary">Last Month</a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="dashboard-cards">
            <div class="card">
                <div class="card-icon blue">💰</div>
                <h3>Total Sales</h3>
                <div class="card-value">₱<?php echo number_format($total_sales, 2); ?></div>
            </div>
            <div class="card">
                <div class="card-icon green">📊</div>
                <h3>Total Transactions</h3>
                <div class="card-value"><?php echo $total_transactions; ?></div>
            </div>
            <div class="card">
                <div class="card-icon orange">📈</div>
                <h3>Average Sale</h3>
                <div class="card-value">₱<?php echo number_format($average_sale, 2); ?></div>
            </div>
        </div>

        <!-- ── DETAILED REPORT ─────────────────────────────────────────────── -->
        <?php if ($report_type === 'detailed'): ?>
        <div class="content-box">
            <h2>
                Detailed Sales Report
                <span style="font-weight:400;font-size:.9rem;color:#666;margin-left:8px;">
                    <?php echo date('M d, Y', strtotime($start_date)); ?> &mdash; <?php echo date('M d, Y', strtotime($end_date)); ?>
                </span>
            </h2>

            <?php if (empty($sales)): ?>
                <p>No approved sales found for the selected date range.</p>
            <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Date &amp; Time</th>
                            <th>Customer</th>
                            <th>Items / Service</th>
                            <th>Qty</th>
                            <th>Total Amount</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td>
                                <span class="receipt-badge">
                                    <?php echo !empty($sale['receipt_no'])
                                        ? htmlspecialchars($sale['receipt_no'])
                                        : ('#' . $sale['sale_id']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo date('M d, Y', strtotime($sale['sale_date'])); ?>
                                <br>
                                <small style="color:#666;font-size:11px;">
                                    <?php echo (!empty($sale['approved_at']) && $sale['approved_at'] !== '0000-00-00 00:00:00')
                                        ? date('g:i A', strtotime($sale['approved_at']))
                                        : 'N/A'; ?>
                                </small>
                            </td>
                            <td>
                                <?php if (!empty($sale['customer_name'])): ?>
                                    <span class="customer-name"><?php echo htmlspecialchars($sale['customer_name']); ?></span>
                                <?php else: ?>
                                    <span class="walk-in">Walk-in</span>
                                <?php endif; ?>
                            </td>
                            <td><div class="items-col"><?php echo htmlspecialchars($sale['item_name'] ?? 'N/A'); ?></div></td>
                            <td><?php echo $sale['quantity']; ?></td>
                            <td><strong>₱<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                            <td><?php echo ucfirst($sale['payment_method']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background:#f0f4ff;font-weight:bold;">
                            <td colspan="5" style="text-align:right;">TOTAL:</td>
                            <td>₱<?php echo number_format($total_sales, 2); ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── DAILY SUMMARY ──────────────────────────────────────────────── -->
        <?php else: ?>
        <div class="content-box">
            <h2>
                Daily Summary Report
                <span style="font-weight:400;font-size:.9rem;color:#666;margin-left:8px;">
                    <?php echo date('M d, Y', strtotime($start_date)); ?> &mdash; <?php echo date('M d, Y', strtotime($end_date)); ?>
                </span>
            </h2>

            <?php if (empty($daily_breakdown)): ?>
                <p>No approved sales found for the selected date range.</p>
            <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Transactions</th>
                            <th>Daily Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_breakdown as $day): ?>
                        <tr>
                            <td><?php echo date('l, M d, Y', strtotime($day['sale_date'])); ?></td>
                            <td><?php echo $day['transactions']; ?></td>
                            <td><strong>₱<?php echo number_format($day['daily_total'], 2); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background:#f0f4ff;font-weight:bold;">
                            <td>TOTAL</td>
                            <td><?php echo $total_transactions; ?></td>
                            <td>₱<?php echo number_format($total_sales, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>