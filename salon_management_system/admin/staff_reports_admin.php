<?php
/**
 * Admin — Staff Reports Inbox
 * View, edit, and delete reports sent by staff members.
 */

require_once '../config/auth.php';
requireAdmin();

$pdo     = getPDOConnection();
$success = '';
$error   = '';

// ── Auto-create tables ─────────────────────────────────────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS staff_report_submissions (
        submission_id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id      INT           NOT NULL,
        staff_name    VARCHAR(150)  NOT NULL,
        report_date   DATE          NOT NULL,
        total_amount  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total_entries INT           NOT NULL DEFAULT 0,
        status        ENUM('completed','sent','seen') NOT NULL DEFAULT 'completed',
        submitted_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sub_staff (staff_id)
    )
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS staff_reports (
        report_id     INT AUTO_INCREMENT PRIMARY KEY,
        staff_id      INT           NOT NULL,
        staff_name    VARCHAR(150)  NOT NULL,
        service_item  VARCHAR(255)  NOT NULL,
        customer_name VARCHAR(150)  NOT NULL DEFAULT '',
        price         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        entry_date    DATE          NOT NULL,
        entry_time    TIME          NOT NULL,
        notes         TEXT,
        submission_id INT,
        created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_staff_id (staff_id)
    )
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS staff_notifications (
        notif_id      INT AUTO_INCREMENT PRIMARY KEY,
        submission_id INT           NOT NULL,
        staff_id      INT           NOT NULL,
        staff_name    VARCHAR(150)  NOT NULL,
        message       TEXT          NOT NULL,
        is_read       TINYINT(1)    NOT NULL DEFAULT 0,
        created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_notif_read (is_read)
    )
");
// Patch: ensure customer_name column exists in staff_reports
try { $pdo->exec("ALTER TABLE staff_reports ADD COLUMN customer_name VARCHAR(150) NOT NULL DEFAULT ''"); } catch (Exception $e) {}

// ── MARK AS SEEN ───────────────────────────────────────────────────────────────
if (isset($_GET['mark_seen'])) {
    $sub_id = intval($_GET['mark_seen']);
    try {
        $pdo->prepare("UPDATE staff_report_submissions SET status = 'seen' WHERE submission_id = ?")->execute([$sub_id]);
        $pdo->prepare("UPDATE staff_notifications SET is_read = 1 WHERE submission_id = ?")->execute([$sub_id]);
    } catch (Exception $e) {}
    $from = $_GET['date_from'] ?? date('Y-m-01');
    $to   = $_GET['date_to']   ?? date('Y-m-d');
    header("Location: staff_reports_admin.php?date_from={$from}&date_to={$to}");
    exit();
}

// ── MARK ALL SEEN ──────────────────────────────────────────────────────────────
if (isset($_GET['mark_all_seen'])) {
    try {
        $pdo->exec("UPDATE staff_report_submissions SET status = 'seen' WHERE status = 'sent'");
        $pdo->exec("UPDATE staff_notifications SET is_read = 1 WHERE is_read = 0");
    } catch (Exception $e) {}
    header("Location: staff_reports_admin.php");
    exit();
}

// ── DELETE SUBMISSION ──────────────────────────────────────────────────────────
if (isset($_GET['delete_sub'])) {
    $sub_id = intval($_GET['delete_sub']);
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM staff_reports WHERE submission_id = ?")->execute([$sub_id]);
        $pdo->prepare("DELETE FROM staff_notifications WHERE submission_id = ?")->execute([$sub_id]);
        $pdo->prepare("DELETE FROM staff_report_submissions WHERE submission_id = ?")->execute([$sub_id]);
        $pdo->commit();
        $success = 'Report deleted successfully.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error deleting report: ' . $e->getMessage();
    }
}

// ── EDIT SUBMISSION (update individual report rows) ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_submission') {
    $sub_id       = intval($_POST['submission_id']);
    $report_date  = sanitizeInput($_POST['report_date']);
    $row_ids      = $_POST['row_ids']       ?? [];
    $svc_items    = $_POST['svc_items']     ?? [];
    $cust_names   = $_POST['cust_names']    ?? [];
    $prices       = $_POST['prices']        ?? [];
    $entry_dates  = $_POST['entry_dates']   ?? [];
    $entry_times  = $_POST['entry_times']   ?? [];
    $notes_arr    = $_POST['notes_arr']     ?? [];
    $staff_names  = $_POST['staff_names']   ?? [];

    try {
        $pdo->beginTransaction();

        // Update each existing row
        $upd = $pdo->prepare("
            UPDATE staff_reports
            SET service_item=?, customer_name=?, price=?, entry_date=?, entry_time=?, notes=?, staff_name=?
            WHERE report_id=? AND submission_id=?
        ");
        $total = 0;
        foreach ($row_ids as $i => $rid) {
            $price = floatval($prices[$i] ?? 0);
            $total += $price;
            $upd->execute([
                sanitizeInput($svc_items[$i]   ?? ''),
                sanitizeInput($cust_names[$i]  ?? ''),
                $price,
                $entry_dates[$i] ?? date('Y-m-d'),
                $entry_times[$i] ?? '00:00',
                sanitizeInput($notes_arr[$i]   ?? ''),
                sanitizeInput($staff_names[$i] ?? ''),
                intval($rid),
                $sub_id
            ]);
        }

        // Recalculate totals on submission header
        $pdo->prepare("
            UPDATE staff_report_submissions
            SET report_date=?, total_amount=?, total_entries=?
            WHERE submission_id=?
        ")->execute([$report_date, $total, count($row_ids), $sub_id]);

        $pdo->commit();
        $success = 'Report updated successfully.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error updating report: ' . $e->getMessage();
    }
}

// ── FILTERS ────────────────────────────────────────────────────────────────────
$filter_date_from = $_GET['date_from'] ?? date('Y-m-01');
$filter_date_to   = $_GET['date_to']   ?? date('Y-m-d');
$filter_staff     = $_GET['staff_id']  ?? '';
$filter_status    = $_GET['status']    ?? '';

$staff_list = $pdo->query("SELECT staff_id, full_name FROM staff ORDER BY full_name ASC")->fetchAll();

// ── FETCH SUBMISSIONS ──────────────────────────────────────────────────────────
$where  = ["srs.report_date BETWEEN :dfrom AND :dto", "srs.status IN ('sent','seen')"];
$params = [':dfrom' => $filter_date_from, ':dto' => $filter_date_to];

if ($filter_staff !== '') { $where[] = "srs.staff_id = :sid"; $params[':sid'] = $filter_staff; }
if ($filter_status !== '') { $where[] = "srs.status = :sts"; $params[':sts'] = $filter_status; }

$stmt = $pdo->prepare("
    SELECT srs.*, s.job_title
    FROM   staff_report_submissions srs
    LEFT JOIN staff s ON srs.staff_id = s.staff_id
    WHERE  " . implode(' AND ', $where) . "
    ORDER  BY srs.submitted_at DESC
");
$stmt->execute($params);
$submissions = $stmt->fetchAll();

// ── FETCH DETAIL ROWS ──────────────────────────────────────────────────────────
$detail_rows = [];
if (!empty($submissions)) {
    $ids = array_column($submissions, 'submission_id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $dstmt = $pdo->prepare("SELECT * FROM staff_reports WHERE submission_id IN ({$ph}) ORDER BY entry_date ASC, entry_time ASC");
    $dstmt->execute($ids);
    foreach ($dstmt->fetchAll() as $row) {
        $detail_rows[$row['submission_id']][] = $row;
    }
}

// ── STATS ──────────────────────────────────────────────────────────────────────
$total_amount  = array_sum(array_column($submissions, 'total_amount'));
$total_entries = array_sum(array_column($submissions, 'total_entries'));
$unread_count  = count(array_filter($submissions, fn($s) => $s['status'] === 'sent'));
$pending_count = $pdo->query("SELECT COUNT(*) FROM sales WHERE status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Reports — Salon Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../assets/images/icon.png">
    <style>
        .summary-strip { display:flex; gap:16px; margin-bottom:22px; flex-wrap:wrap; }
        .strip-card { background:#fff; border-radius:10px; padding:18px 22px; box-shadow:0 2px 10px rgba(0,0,0,.07); flex:1; min-width:150px; border-left:4px solid #4a90e2; }
        .strip-card.green  { border-color:#28a745; }
        .strip-card.orange { border-color:#e05c00; }
        .strip-card.purple { border-color:#6f42c1; }
        .strip-card .sv { font-size:1.55rem; font-weight:700; color:#222; }
        .strip-card .sl { font-size:.76rem; color:#777; text-transform:uppercase; letter-spacing:.05em; margin-top:3px; }

        .filter-bar { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; background:#fff; padding:16px 20px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.06); margin-bottom:18px; }
        .filter-bar label { font-size:.86rem; font-weight:600; color:#555; display:block; margin-bottom:4px; }
        .filter-bar input[type="date"], .filter-bar select { padding:7px 11px; border:1px solid #ced4da; border-radius:6px; font-size:.9rem; background:#fff; }
        .filter-bar .quick-btns { display:flex; gap:6px; align-items:flex-end; flex-wrap:wrap; }

        .sub-table { width:100%; border-collapse:collapse; font-size:.9rem; }
        .sub-table th { background:#f1f4f8; padding:10px 14px; text-align:left; font-weight:700; color:#444; border-bottom:2px solid #dee2e6; white-space:nowrap; }
        .sub-table td { padding:10px 14px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
        .sub-table tr.unread td { background:#fffbf0; }
        .sub-table tr.unread td:first-child { border-left:3px solid #e05c00; }
        .sub-table tr:hover td { background:#f5f8ff; }

        .badge { display:inline-block; padding:3px 11px; border-radius:20px; font-size:.76rem; font-weight:700; white-space:nowrap; }
        .badge-new  { background:#fff3cd; color:#856404; border:1px solid #ffc107; }
        .badge-seen { background:#e2e3e5; color:#495057; }

        /* Action buttons */
        .btn-view { background:#e9f0ff; border:1px solid #b8d0f8; color:#1e6fc3; font-size:.80rem; font-weight:600; padding:4px 10px; border-radius:4px; cursor:pointer; transition:all .15s; }
        .btn-view:hover, .btn-view.open { background:#1e6fc3; color:#fff; }
        .btn-edit-sub { background:#fff8e1; border:1px solid #ffc107; color:#856404; font-size:.80rem; font-weight:600; padding:4px 10px; border-radius:4px; cursor:pointer; transition:all .15s; text-decoration:none; white-space:nowrap; }
        .btn-edit-sub:hover { background:#ffc107; color:#000; }
        .btn-delete-sub { background:#fdecea; border:1px solid #f5c6cb; color:#c0392b; font-size:.80rem; font-weight:600; padding:4px 10px; border-radius:4px; cursor:pointer; transition:all .15s; text-decoration:none; white-space:nowrap; }
        .btn-delete-sub:hover { background:#dc3545; color:#fff; }
        .btn-mark-seen { background:none; border:1px solid #28a745; color:#28a745; font-size:.80rem; font-weight:600; padding:4px 10px; border-radius:4px; text-decoration:none; transition:all .15s; white-space:nowrap; }
        .btn-mark-seen:hover { background:#28a745; color:#fff; }

        .detail-row { display:none; }
        .detail-inner { width:100%; border-collapse:collapse; font-size:.85rem; background:#f8f9ff; }
        .detail-inner th { background:#dde8ff; padding:8px 13px; text-align:left; font-weight:700; color:#1a3da0; border-bottom:1px solid #b8d0f8; }
        .detail-inner td { padding:7px 13px; border-bottom:1px solid #e8edf5; }
        .detail-inner tfoot td { background:#edf2ff; font-weight:700; }

        .unread-banner { background:#fff8e1; border:1px solid #ffc107; border-radius:8px; padding:12px 18px; margin-bottom:18px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
        .unread-banner span { font-size:.92rem; color:#856404; font-weight:600; }

        .staff-pill { font-weight:700; color:#1e6fc3; }
        .staff-role { font-size:.78rem; color:#888; }

        .empty-state { text-align:center; padding:50px 20px; color:#aaa; }

        /* ── Edit Modal ── */
        .mo { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9999; align-items:center; justify-content:center; }
        .mo.open { display:flex; }
        .mb-edit { background:#fff; border-radius:12px; padding:24px 28px; width:95%; max-width:900px; max-height:90vh; overflow-y:auto; box-shadow:0 8px 40px rgba(0,0,0,.22); position:relative; }
        .mb-edit h3 { margin:0 0 16px; font-size:1.1rem; }
        .mc { position:absolute; top:14px; right:16px; background:none; border:none; font-size:20px; cursor:pointer; color:#888; }
        .mc:hover { color:#333; }

        .edit-table { width:100%; border-collapse:collapse; font-size:.86rem; }
        .edit-table th { background:#f1f4f8; padding:8px 10px; text-align:left; font-weight:700; color:#444; border-bottom:2px solid #dee2e6; white-space:nowrap; }
        .edit-table td { padding:4px 6px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
        .edit-table input[type="text"],
        .edit-table input[type="number"],
        .edit-table input[type="date"],
        .edit-table input[type="time"] { width:100%; padding:6px 8px; border:1px solid #ced4da; border-radius:5px; font-size:.84rem; box-sizing:border-box; }
        .edit-table input:focus { border-color:#4a6cf7; outline:none; }
        .edit-footer { display:flex; gap:10px; justify-content:flex-end; margin-top:18px; padding-top:14px; border-top:1px solid #dee2e6; }
    </style>
</head>
<body>
<div class="dashboard-container">

    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Salon Admin</h2>
            <p><?php echo getCurrentUserName(); ?></p>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php">&#x1F4CA; Dashboard</a>
            <a href="appointments_admin.php">&#x1F4C5; Appointments</a>
            <a href="pos.php">&#x1F4B0; Point of Sales</a>
            <a href="sales_reports.php">&#x1F4C8; Sales Reports</a>
            <a href="staff_reports_admin.php" class="active">
                &#x1F4CB; Staff Reports
                <?php if ($unread_count > 0): ?>
                    <span style="background:#e05c00;color:#fff;border-radius:12px;padding:1px 7px;font-size:.74rem;margin-left:6px;font-weight:700;"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="inventory.php">&#x1F4E6; Inventory</a>
            <a href="staff_duties.php">&#x1F4CB; Staff Duties</a>
            <a href="staff_management.php">&#x1F465; Staff Management</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <h1>Staff Reports</h1>
            <div class="user-info">
                <span style="margin-right:15px;">Welcome, <strong><?php echo getCurrentUserName(); ?></strong></span>
                <a href="settings.php" class="btn btn-primary">&#x2699;&#xFE0F; Settings</a>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </div>

        <?php if ($success): ?><div class="success-message"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="error-message"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php if ($unread_count > 0): ?>
        <div class="unread-banner">
            <span>&#x1F4EC; <?php echo $unread_count; ?> new report<?php echo $unread_count > 1 ? 's' : ''; ?> sent by staff</span>
            <a href="?mark_all_seen=1" class="btn btn-sm btn-primary"
               onclick="return confirm('Mark all reports as seen?')">Mark All as Seen</a>
        </div>
        <?php endif; ?>

        <!-- Summary cards -->
        <div class="summary-strip">
            <div class="strip-card orange">
                <div class="sv"><?php echo $unread_count; ?></div>
                <div class="sl">Unread Reports</div>
            </div>
            <div class="strip-card green">
                <div class="sv">&#x20B1;<?php echo number_format($total_amount, 2); ?></div>
                <div class="sl">Total Amount</div>
            </div>
            <div class="strip-card purple">
                <div class="sv"><?php echo count($submissions); ?></div>
                <div class="sl">Reports Received</div>
            </div>
            <div class="strip-card">
                <div class="sv"><?php echo $total_entries; ?></div>
                <div class="sl">Total Entries</div>
            </div>
        </div>

        <!-- Filter -->
        <form method="GET" action="">
            <div class="filter-bar">
                <div><label>From</label><input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>"></div>
                <div><label>To</label><input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>"></div>
                <div>
                    <label>Staff</label>
                    <select name="staff_id">
                        <option value="">All Staff</option>
                        <?php foreach ($staff_list as $s): ?>
                            <option value="<?php echo $s['staff_id']; ?>" <?php echo $filter_staff == $s['staff_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="">All</option>
                        <option value="sent" <?php echo $filter_status === 'sent' ? 'selected' : ''; ?>>Unread</option>
                        <option value="seen" <?php echo $filter_status === 'seen' ? 'selected' : ''; ?>>Seen</option>
                    </select>
                </div>
                <div style="align-self:flex-end;"><button type="submit" class="btn btn-primary">Filter</button></div>
                <div class="quick-btns" style="align-self:flex-end;">
                    <a href="?date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-primary">Today</a>
                    <a href="?date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-primary">This Month</a>
                    <a href="?date_from=<?php echo date('Y-m-01', strtotime('-1 month')); ?>&date_to=<?php echo date('Y-m-t', strtotime('-1 month')); ?>" class="btn btn-sm btn-primary">Last Month</a>
                </div>
            </div>
        </form>

        <!-- Reports table -->
        <div class="content-box">
            <h2>Received Reports</h2>

            <?php if (empty($submissions)): ?>
                <div class="empty-state"><p>No staff reports received for this period.</p></div>
            <?php else: ?>
            <div class="table-container">
                <table class="sub-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Staff Member</th>
                            <th>Report Date</th>
                            <th>Submitted At</th>
                            <th>Total Amount</th>
                            <th>Entries</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $i => $sub): ?>
                        <?php
                            $is_unread = $sub['status'] === 'sent';
                            $rows      = $detail_rows[$sub['submission_id']] ?? [];
                            $sub_id    = $sub['submission_id'];
                        ?>
                        <!-- Summary row -->
                        <tr class="<?php echo $is_unread ? 'unread' : ''; ?>"
                            onclick="toggleDetails(<?php echo $sub_id; ?>)" style="cursor:pointer;">
                            <td><?php echo $i + 1; ?></td>
                            <td>
                                <span class="staff-pill"><?php echo htmlspecialchars($sub['staff_name']); ?></span>
                                <?php if (!empty($sub['job_title'])): ?>
                                    <br><span class="staff-role"><?php echo htmlspecialchars($sub['job_title']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($sub['report_date'])); ?></td>
                            <td>
                                <?php echo date('M d, Y', strtotime($sub['submitted_at'])); ?>
                                <br><small style="color:#888;"><?php echo date('g:i A', strtotime($sub['submitted_at'])); ?></small>
                            </td>
                            <td><strong>&#x20B1;<?php echo number_format($sub['total_amount'], 2); ?></strong></td>
                            <td><?php echo $sub['total_entries']; ?> item<?php echo $sub['total_entries'] != 1 ? 's' : ''; ?></td>
                            <td>
                                <?php if ($is_unread): ?>
                                    <span class="badge badge-new">New</span>
                                <?php else: ?>
                                    <span class="badge badge-seen">Seen</span>
                                <?php endif; ?>
                            </td>
                            <td onclick="event.stopPropagation()" style="white-space:nowrap;display:flex;gap:5px;align-items:center;flex-wrap:wrap;">
                                <!-- VIEW -->
                                <button class="btn-view" id="viewbtn-<?php echo $sub_id; ?>"
                                        onclick="toggleDetails(<?php echo $sub_id; ?>)">View</button>
                                <!-- EDIT -->
                                <button class="btn-edit-sub"
                                        onclick="openEditModal(<?php echo $sub_id; ?>)">Edit</button>
                                <!-- DELETE -->
                                <a href="?delete_sub=<?php echo $sub_id; ?>&date_from=<?php echo urlencode($filter_date_from); ?>&date_to=<?php echo urlencode($filter_date_to); ?>"
                                   class="btn-delete-sub"
                                   onclick="return confirm('Permanently delete this report and all its entries?')">Delete</a>
                                <!-- MARK SEEN -->
                                <?php if ($is_unread): ?>
                                <a href="?mark_seen=<?php echo $sub_id; ?>&date_from=<?php echo urlencode($filter_date_from); ?>&date_to=<?php echo urlencode($filter_date_to); ?>"
                                   class="btn-mark-seen">Mark Seen</a>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Expandable detail row (view) -->
                        <tr id="detail-<?php echo $sub_id; ?>" class="detail-row">
                            <td colspan="8" style="padding:0;">
                                <table class="detail-inner">
                                    <thead>
                                        <tr>
                                            <th>#</th><th>Staff Name</th><th>Service / Product</th>
                                            <th>Customer Name</th><th>Price</th>
                                            <th>Date</th><th>Time</th><th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $di => $row): ?>
                                        <tr>
                                            <td><?php echo $di + 1; ?></td>
                                            <td><?php echo htmlspecialchars($row['staff_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['service_item']); ?></td>
                                            <td><?php echo htmlspecialchars($row['customer_name'] ?? ''); ?></td>
                                            <td>&#x20B1;<?php echo number_format($row['price'], 2); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($row['entry_date'])); ?></td>
                                            <td><?php echo date('g:i A', strtotime($row['entry_time'])); ?></td>
                                            <td style="color:#888;font-style:italic;">
                                                <?php echo !empty($row['notes']) ? htmlspecialchars($row['notes']) : '&mdash;'; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" style="text-align:right;">TOTAL</td>
                                            <td>&#x20B1;<?php echo number_format($sub['total_amount'], 2); ?></td>
                                            <td colspan="4"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </td>
                        </tr>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<!-- ══ EDIT SUBMISSION MODAL ═════════════════════════════════════════════════ -->
<div class="mo" id="editModal">
    <div class="mb-edit">
        <button class="mc" onclick="closeEditModal()">&#x2715;</button>
        <h3>Edit Report <span id="editModalLabel" style="color:#4a6cf7;"></span></h3>

        <form method="POST" action="" id="editForm">
            <input type="hidden" name="action" value="edit_submission">
            <input type="hidden" name="submission_id" id="editSubId">

            <div style="display:flex;gap:16px;margin-bottom:14px;flex-wrap:wrap;">
                <div>
                    <label style="font-size:.85rem;font-weight:600;color:#555;display:block;margin-bottom:4px;">Report Date</label>
                    <input type="date" name="report_date" id="editReportDate"
                           style="padding:7px 10px;border:1px solid #ced4da;border-radius:6px;font-size:.9rem;">
                </div>
                <div style="display:flex;align-items:flex-end;">
                    <small style="color:#888;">Edit individual rows below. Total will be recalculated on save.</small>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table class="edit-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th style="min-width:120px;">Staff Name</th>
                            <th style="min-width:180px;">Service / Product</th>
                            <th style="min-width:130px;">Customer Name</th>
                            <th style="min-width:100px;">Price (₱)</th>
                            <th style="min-width:120px;">Date</th>
                            <th style="min-width:100px;">Time</th>
                            <th style="min-width:150px;">Notes</th>
                        </tr>
                    </thead>
                    <tbody id="editTableBody">
                        <!-- populated by JS -->
                    </tbody>
                </table>
            </div>

            <div class="edit-footer">
                <button type="button" class="btn btn-warning" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-success">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Embed all submission detail data as JSON for the edit modal -->
<script>
const ALL_DETAILS = <?php
    $js_data = [];
    foreach ($submissions as $sub) {
        $rows = $detail_rows[$sub['submission_id']] ?? [];
        $js_data[$sub['submission_id']] = [
            'report_date' => $sub['report_date'],
            'staff_name'  => $sub['staff_name'],
            'rows'        => array_map(fn($r) => [
                'report_id'     => $r['report_id'],
                'staff_name'    => $r['staff_name'],
                'service_item'  => $r['service_item'],
                'customer_name' => $r['customer_name'] ?? '',
                'price'         => $r['price'],
                'entry_date'    => $r['entry_date'],
                'entry_time'    => substr($r['entry_time'], 0, 5),
                'notes'         => $r['notes'] ?? '',
            ], $rows),
        ];
    }
    echo json_encode($js_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
?>;

function toggleDetails(subId) {
    const row = document.getElementById('detail-' + subId);
    const btn = document.getElementById('viewbtn-' + subId);
    if (!row) return;
    const open = row.style.display === 'table-row';
    row.style.display = open ? 'none' : 'table-row';
    if (btn) { btn.textContent = open ? 'View' : 'Hide'; btn.classList.toggle('open', !open); }
}

function openEditModal(subId) {
    const data = ALL_DETAILS[subId];
    if (!data) return;

    document.getElementById('editSubId').value      = subId;
    document.getElementById('editModalLabel').textContent = '— ' + data.staff_name;
    document.getElementById('editReportDate').value  = data.report_date;

    let html = '';
    data.rows.forEach((r, i) => {
        html += `<tr>
            <td style="text-align:center;color:#888;">${i+1}</td>
            <input type="hidden" name="row_ids[]" value="${r.report_id}">
            <td><input type="text" name="staff_names[]" value="${esc(r.staff_name)}"></td>
            <td><input type="text" name="svc_items[]" value="${esc(r.service_item)}"></td>
            <td><input type="text" name="cust_names[]" value="${esc(r.customer_name)}"></td>
            <td><input type="number" name="prices[]" value="${parseFloat(r.price).toFixed(2)}" step="0.01" min="0"></td>
            <td><input type="date" name="entry_dates[]" value="${r.entry_date}"></td>
            <td><input type="time" name="entry_times[]" value="${r.entry_time}"></td>
            <td><input type="text" name="notes_arr[]" value="${esc(r.notes)}"></td>
        </tr>`;
    });

    document.getElementById('editTableBody').innerHTML = html;
    document.getElementById('editModal').classList.add('open');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

function esc(str) {
    return String(str ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
</script>
</body>
</html>