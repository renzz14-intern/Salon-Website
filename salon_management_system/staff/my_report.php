<?php
/**
 * My Report — Excel-style staff logbook.
 *
 * Tables auto-created on first load:
 *
 * staff_reports         — completed/sent report entries
 * staff_report_drafts   — per-staff draft rows (the live spreadsheet)
 * staff_report_submissions — completed daily reports sent to admin
 * staff_notifications   — admin notification inbox
 */

require_once '../config/auth.php';
requireLogin();

if (isAdmin()) {
    header("Location: /salon_management_system/admin/dashboard.php");
    exit();
}

$perms = getStaffPermissions();
if (empty($perms['can_view_my_reports'])) {
    header("Location: dashboard.php");
    exit();
}

$pdo      = getPDOConnection();
$nav      = getStaffNavLinks();
$staff_id = getCurrentStaffId();
$success  = '';
$error    = '';

// Messages passed via redirect
if (($_GET['msg'] ?? '') === 'completed') {
    $success = 'Report completed! Your entries have been saved to the history below. The spreadsheet has been cleared for a new report.';
} elseif (($_GET['msg'] ?? '') === 'saved') {
    $success = 'Draft saved! Your work-in-progress is stored safely.';
}

// ── Auto-create tables ─────────────────────────────────────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS staff_report_drafts (
        draft_id     INT AUTO_INCREMENT PRIMARY KEY,
        staff_id     INT           NOT NULL,
        row_order    INT           NOT NULL DEFAULT 0,
        service_item  VARCHAR(255)  NOT NULL DEFAULT '',
        customer_name VARCHAR(150)  NOT NULL DEFAULT '',
        price         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        entry_date    DATE,
        entry_time    TIME,
        staff_name    VARCHAR(150)  NOT NULL DEFAULT '',
        notes        TEXT,
        updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_draft_staff (staff_id)
    )
");
try {
    $pdo->exec("ALTER TABLE staff_report_drafts ADD COLUMN staff_name VARCHAR(150) NOT NULL DEFAULT ''");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE staff_report_submissions MODIFY COLUMN status ENUM('completed','sent','seen') NOT NULL DEFAULT 'completed'");
} catch (Exception $e) {}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS staff_reports (
        report_id    INT AUTO_INCREMENT PRIMARY KEY,
        staff_id     INT           NOT NULL,
        staff_name   VARCHAR(150)  NOT NULL,
        service_item VARCHAR(255)  NOT NULL,
        price        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        entry_date   DATE          NOT NULL,
        entry_time   TIME          NOT NULL,
        notes        TEXT,
        submission_id INT,
        created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_staff_id (staff_id)
    )
");
try {
    $pdo->exec("ALTER TABLE staff_reports ADD COLUMN submission_id INT NULL");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE staff_report_drafts ADD COLUMN customer_name VARCHAR(150) NOT NULL DEFAULT ''");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE staff_reports ADD COLUMN customer_name VARCHAR(150) NOT NULL DEFAULT ''");
} catch (Exception $e) {}
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

// ── Staff info ─────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT full_name, job_title FROM staff WHERE staff_id = ?");
$stmt->execute([$staff_id]);
$staff_info = $stmt->fetch();
$staff_name = $staff_info['full_name'] ?? getCurrentUserName();

// ══════════════════════════════════════════════════════════════════════════════
// POST HANDLERS
// ══════════════════════════════════════════════════════════════════════════════

// ── SAVE DRAFT ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_draft') {
    try {
        $pdo->prepare("DELETE FROM staff_report_drafts WHERE staff_id = ?")->execute([$staff_id]);

        $rows           = $_POST['rows']           ?? [];
        $prices         = $_POST['prices']         ?? [];
        $dates          = $_POST['dates']          ?? [];
        $times          = $_POST['times']          ?? [];
        $notes_arr      = $_POST['notes_arr']      ?? [];
        $staff_names    = $_POST['staff_names']    ?? [];
        $customer_names = $_POST['customer_names'] ?? [];

        $ins = $pdo->prepare("
            INSERT INTO staff_report_drafts (staff_id, row_order, service_item, customer_name, price, entry_date, entry_time, notes, staff_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($rows as $i => $svc) {
            $svc   = trim($svc);
            $price = floatval($prices[$i] ?? 0);
            $date  = !empty($dates[$i])  ? $dates[$i]  : null;
            $time  = !empty($times[$i])  ? $times[$i]  : null;
            $note  = trim($notes_arr[$i] ?? '');
            $sname = trim($staff_names[$i] ?? $staff_name);
            $cname = trim($customer_names[$i] ?? '');
            if ($svc !== '') {
                $ins->execute([$staff_id, $i, $svc, $cname, $price, $date, $time, $note, $sname]);
            }
        }
        $from = date('Y-m-01');
        $to   = date('Y-m-d');
        header("Location: my_report.php?date_from={$from}&date_to={$to}&msg=saved");
        exit();
    } catch (Exception $e) {
        $error = 'Error saving draft: ' . $e->getMessage();
    }
}

// ── COMPLETE REPORT ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete') {
    try {
        $pdo->prepare("DELETE FROM staff_report_drafts WHERE staff_id = ?")->execute([$staff_id]);

        $rows           = $_POST['rows']           ?? [];
        $prices         = $_POST['prices']         ?? [];
        $dates          = $_POST['dates']          ?? [];
        $times          = $_POST['times']          ?? [];
        $notes_arr      = $_POST['notes_arr']      ?? [];
        $staff_names    = $_POST['staff_names']    ?? [];
        $customer_names = $_POST['customer_names'] ?? [];

        $valid = [];
        foreach ($rows as $i => $svc) {
            $svc   = trim($svc);
            $price = floatval($prices[$i] ?? 0);
            $date  = !empty($dates[$i])  ? $dates[$i]  : date('Y-m-d');
            $time  = !empty($times[$i])  ? $times[$i]  : date('H:i:s');
            $note  = trim($notes_arr[$i] ?? '');
            $sname = trim($staff_names[$i] ?? $staff_name);
            $cname = trim($customer_names[$i] ?? '');
            if ($svc !== '') {
                $valid[] = compact('svc', 'price', 'date', 'time', 'note', 'sname', 'cname');
            }
        }

        if (empty($valid)) {
            $error = 'Cannot complete an empty report. Please add at least one entry.';
        } else {
            $pdo->beginTransaction();
            $total_amount = array_sum(array_column($valid, 'price'));

            $pdo->prepare("
                INSERT INTO staff_report_submissions (staff_id, staff_name, report_date, total_amount, total_entries, status)
                VALUES (?, ?, ?, ?, ?, 'completed')
            ")->execute([$staff_id, $staff_name, date('Y-m-d'), $total_amount, count($valid)]);
            $submission_id = $pdo->lastInsertId();

            $ins = $pdo->prepare("
                INSERT INTO staff_reports (staff_id, staff_name, service_item, customer_name, price, entry_date, entry_time, notes, submission_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($valid as $row) {
                $ins->execute([$staff_id, $row['sname'], $row['svc'], $row['cname'], $row['price'], $row['date'], $row['time'], $row['note'], $submission_id]);
            }
            $pdo->commit();

            $from = date('Y-m-01');
            $to   = date('Y-m-d');
            header("Location: my_report.php?date_from={$from}&date_to={$to}&msg=completed");
            exit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error completing report: ' . $e->getMessage();
    }
}

// ── SEND TO ADMIN ──────────────────────────────────────────────────────────────
if (isset($_GET['send_sub'])) {
    $sub_id = intval($_GET['send_sub']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM staff_report_submissions WHERE submission_id = ? AND staff_id = ?");
        $stmt->execute([$sub_id, $staff_id]);
        $sub = $stmt->fetch();

        if ($sub && $sub['status'] === 'completed') {
            $dstmt = $pdo->prepare("SELECT * FROM staff_reports WHERE submission_id = ?");
            $dstmt->execute([$sub_id]);
            $detail_items = $dstmt->fetchAll();

            $item_lines = implode('; ', array_map(fn($r) => $r['service_item'] . ' (₱' . number_format($r['price'], 2) . ')', $detail_items));
            $message = "{$staff_name} sent a completed report (dated " . date('M d, Y', strtotime($sub['report_date'])) . "). "
                     . count($detail_items) . " entries totalling ₱" . number_format($sub['total_amount'], 2) . ". "
                     . "Items: " . $item_lines;

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE staff_report_submissions SET status = 'sent' WHERE submission_id = ?")->execute([$sub_id]);
            $pdo->prepare("
                INSERT INTO staff_notifications (submission_id, staff_id, staff_name, message)
                VALUES (?, ?, ?, ?)
            ")->execute([$sub_id, $staff_id, $staff_name, $message]);
            $pdo->commit();
            $success = 'Report sent to admin successfully!';
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = 'Error sending report: ' . $e->getMessage();
    }
}

// ── LOAD DRAFT ────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM staff_report_drafts WHERE staff_id = ? ORDER BY row_order ASC");
$stmt->execute([$staff_id]);
$draft_rows = $stmt->fetchAll();

// ── LOAD HISTORY ──────────────────────────────────────────────────────────────
$filter_date_from = $_GET['date_from'] ?? date('Y-m-01');
$filter_date_to   = $_GET['date_to']   ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT * FROM staff_report_submissions
    WHERE  staff_id    = ?
      AND  report_date BETWEEN ? AND ?
    ORDER  BY submitted_at DESC
");
$stmt->execute([$staff_id, $filter_date_from, $filter_date_to]);
$submissions = $stmt->fetchAll();

$submission_ids = array_column($submissions, 'submission_id');
$detail_rows = [];
if (!empty($submission_ids)) {
    $placeholders = implode(',', array_fill(0, count($submission_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM staff_reports WHERE submission_id IN ($placeholders) ORDER BY entry_date ASC, entry_time ASC");
    $stmt->execute($submission_ids);
    foreach ($stmt->fetchAll() as $row) {
        $detail_rows[$row['submission_id']][] = $row;
    }
}

$hist_total = array_sum(array_column($submissions, 'total_amount'));
$hist_count = count($submissions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Report — Salon Staff</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../assets/images/icon.png">
    <style>
        .xl-shell {
            background: #fff; border-radius: 10px;
            box-shadow: 0 2px 16px rgba(0,0,0,.10); margin-bottom: 26px;
            overflow: hidden; border: 1px solid #d0d7de;
        }
        .xl-toolbar {
            background: #f3f3f3; border-bottom: 1px solid #d0d7de;
            padding: 8px 14px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        }
        .xl-toolbar-title { font-size:.82rem; font-weight:700; color:#444; letter-spacing:.03em; margin-right:auto; }

        .btn-save     { background:#1e6fc3; color:#fff; border:none; padding:7px 18px; border-radius:5px; font-size:.85rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:6px; transition:background .15s; }
        .btn-save:hover     { background:#155aa0; }
        .btn-complete { background:#28a745; color:#fff; border:none; padding:7px 18px; border-radius:5px; font-size:.85rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:6px; transition:background .15s; }
        .btn-complete:hover { background:#1e8035; }

        .btn-addrow { background:none; border:1px dashed #aaa; color:#666; padding:5px 14px; border-radius:4px; font-size:.82rem; cursor:pointer; transition:all .15s; }
        .btn-addrow:hover { background:#f0f4ff; border-color:#4a90e2; color:#4a90e2; }

        .xl-wrap { overflow-x:auto; }
        .xl-table { width:100%; border-collapse:collapse; font-size:.88rem; min-width:800px; }
        .xl-table .col-row-num { width:42px; background:#f3f3f3; border-right:1px solid #d0d7de; text-align:center; color:#888; font-size:.78rem; font-weight:600; user-select:none; }
        .xl-table thead tr th { background:#e9ecef; border:1px solid #c8cdd3; padding:7px 10px; text-align:left; font-weight:700; font-size:.82rem; color:#333; white-space:nowrap; position:sticky; top:0; z-index:2; }
        .xl-table thead tr th.col-row-num { background:#dde0e4; }
        .xl-table tbody tr { background:#fff; }
        .xl-table tbody tr:nth-child(even) { background:#fafbfc; }
        .xl-table tbody tr:hover { background:#edf4ff; }
        .xl-table tbody td { border:1px solid #d8dde3; padding:0; vertical-align:middle; }
        .xl-table tbody td.col-row-num { padding:6px 0; text-align:center; background:#f3f3f3; border-right:2px solid #c8cdd3; color:#888; font-size:.78rem; }
        .xl-table tbody td input[type="text"],
        .xl-table tbody td input[type="number"],
        .xl-table tbody td input[type="date"],
        .xl-table tbody td input[type="time"] { width:100%; border:none; outline:none; background:transparent; padding:8px 10px; font-size:.88rem; color:#222; font-family:inherit; box-sizing:border-box; }
        .xl-table tbody td:focus-within { outline:2px solid #1e6fc3; outline-offset:-2px; background:#fff; z-index:1; position:relative; }

        .btn-row-del { background:none; border:none; cursor:pointer; color:#bbb; font-size:1rem; padding:6px 10px; transition:color .15s; }
        .btn-row-del:hover { color:#dc3545; }

        .xl-total-bar { background:#e9ecef; border-top:2px solid #c8cdd3; padding:8px 14px; display:flex; gap:28px; font-size:.85rem; font-weight:700; color:#333; }
        .xl-total-bar span { color:#1e6fc3; }

        .xl-tabs { background:#e4e4e4; border-top:1px solid #c8cdd3; padding:0 14px; display:flex; gap:2px; }
        .xl-tab { padding:5px 18px; font-size:.8rem; font-weight:600; color:#555; border:1px solid transparent; border-bottom:none; border-radius:4px 4px 0 0; cursor:default; background:#c8cdd3; margin-top:4px; }
        .xl-tab.active { background:#fff; border-color:#c8cdd3; color:#1e6fc3; }

        .staff-banner { background:#e9f0ff; border-radius:10px; padding:11px 18px; margin-bottom:18px; border-left:5px solid #4a90e2; display:flex; gap:20px; flex-wrap:wrap; align-items:center; font-size:.93rem; }

        .filter-bar { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; background:#fff; padding:14px 18px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.06); margin-bottom:16px; }
        .filter-bar label { font-size:.87rem; font-weight:600; color:#555; display:block; margin-bottom:4px; }
        .filter-bar input[type="date"] { padding:7px 12px; border:1px solid #ced4da; border-radius:6px; font-size:.9rem; }

        .hist-table { width:100%; border-collapse:collapse; font-size:.9rem; }
        .hist-table th { background:#f1f4f8; padding:10px 13px; text-align:left; font-weight:700; color:#444; border-bottom:2px solid #dee2e6; }
        .hist-table td { padding:9px 13px; border-bottom:1px solid #f0f0f0; color:#333; vertical-align:middle; }
        .hist-table tr:hover td { background:#f8f9fa; }

        .badge-sent { background:#d4edda; color:#155724; padding:3px 10px; border-radius:20px; font-size:.78rem; font-weight:600; }
        .badge-completed { background:#cce5ff; color:#004085; padding:3px 10px; border-radius:20px; font-size:.78rem; font-weight:600; }

        .btn-send-admin { background:#e05c00; color:#fff; text-decoration:none; font-size:.80rem; font-weight:600; padding:4px 10px; border-radius:4px; transition:background .15s; white-space:nowrap; }
        .btn-send-admin:hover { background:#c05000; color:#fff; }

        /* readonly notice replacing delete */
        .readonly-note { font-size:.76rem; color:#aaa; font-style:italic; }

        .sub-summary-row { transition:background .15s; }
        .sub-summary-row:hover td { background:#edf4ff !important; }
        .sub-detail-row td { padding:0 !important; }

        .btn-expand { background:#e9f0ff; border:1px solid #b8d0f8; color:#1e6fc3; font-size:.82rem; font-weight:600; padding:4px 12px; border-radius:4px; cursor:pointer; transition:all .15s; margin-right:6px; }
        .btn-expand:hover { background:#1e6fc3; color:#fff; }
        .btn-expand.open  { background:#1e6fc3; color:#fff; }

        .detail-inner-table { width:100%; border-collapse:collapse; font-size:.85rem; }
        .detail-inner-table th { background:#dde8ff; padding:8px 12px; text-align:left; font-weight:700; color:#1a3da0; border-bottom:1px solid #b8d0f8; }
        .detail-inner-table td { padding:7px 12px; border-bottom:1px solid #e8edf5; }
        .detail-inner-table tr:last-child td { border-bottom:none; }
        .detail-inner-table tfoot td { background:#edf2ff; font-weight:700; }

        .summary-strip { display:flex; gap:14px; margin-bottom:18px; flex-wrap:wrap; }
        .strip-card { background:#fff; border-radius:9px; padding:16px 20px; box-shadow:0 2px 10px rgba(0,0,0,.06); flex:1; min-width:150px; border-left:4px solid #4a90e2; }
        .strip-card.green  { border-color:#28a745; }
        .strip-card.purple { border-color:#6f42c1; }
        .strip-card .sv { font-size:1.45rem; font-weight:700; color:#222; }
        .strip-card .sl { font-size:.76rem; color:#777; text-transform:uppercase; letter-spacing:.04em; margin-top:2px; }

        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9999; align-items:center; justify-content:center; }
        .modal-overlay.show { display:flex; }
        .modal-box { background:#fff; border-radius:14px; padding:32px 36px; max-width:560px; width:90%; box-shadow:0 8px 40px rgba(0,0,0,.22); max-height:80vh; overflow-y:auto; }
        .modal-box h3 { margin:0 0 12px; font-size:1.15rem; }
        .modal-box .modal-preview { background:#f8f9fa; border:1px solid #dee2e6; border-radius:8px; padding:14px 16px; margin:14px 0; font-size:.87rem; max-height:260px; overflow-y:auto; }
        .modal-preview table { width:100%; border-collapse:collapse; font-size:.85rem; }
        .modal-preview th { background:#e9ecef; padding:6px 10px; text-align:left; font-weight:700; }
        .modal-preview td { padding:6px 10px; border-bottom:1px solid #eee; }
        .modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
    </style>
</head>
<body>
<div class="dashboard-container">

    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>💇 Salon Staff</h2>
            <p><?php echo getCurrentUserName(); ?></p>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php">🏠 Dashboard</a>
            <?php foreach ($nav as $link): ?>
                <a href="<?php echo $link['href']; ?>"
                   class="<?php echo basename($link['href']) === 'my_report.php' ? 'active' : ''; ?>">
                    <?php echo $link['icon'] . ' ' . $link['label']; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <h1>My Report</h1>
            <div class="user-info">
                <span style="margin-right:15px;">Welcome, <strong><?php echo getCurrentUserName(); ?></strong></span>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="staff-banner">
            <span><strong><?php echo htmlspecialchars($staff_name); ?></strong></span>
            <?php if (!empty($staff_info['job_title'])): ?>
                <span><?php echo htmlspecialchars($staff_info['job_title']); ?></span>
            <?php endif; ?>
            <span style="margin-left:auto;font-size:.82rem;color:#666;"><?php echo date('l, F d, Y'); ?></span>
        </div>

        <!-- SPREADSHEET -->
        <form id="reportForm" method="POST" action="">
            <input type="hidden" name="action" id="formAction" value="save_draft">

            <div class="xl-shell">
                <div class="xl-toolbar">
                    <span class="xl-toolbar-title">Daily Report Sheet</span>
                    <button type="button" class="btn-addrow" onclick="addRow()">+ Add Row</button>
                    <button type="button" class="btn-save" onclick="saveDraft()">Save</button>
                    <button type="button" class="btn-complete" onclick="openModal('complete')">Complete Report</button>
                </div>

                <div class="xl-wrap">
                    <table class="xl-table" id="xlTable">
                        <thead>
                            <tr>
                                <th class="col-row-num">#</th>
                                <th style="min-width:150px;">Staff Name</th>
                                <th style="min-width:200px;">Service / Product</th>
                                <th style="min-width:110px;">Customer Name</th>
                                <th style="min-width:130px;">Price (₱)</th>
                                <th style="min-width:110px;">Date</th>
                                <th style="min-width:110px;">Time</th>
                                <th style="width:180px;">Note</th>
                            </tr>
                        </thead>
                        <tbody id="xlBody">
                            <?php
                            $render_rows = !empty($draft_rows) ? $draft_rows : array_fill(0, 10, null);
                            foreach ($render_rows as $idx => $dr):
                                $svc   = htmlspecialchars($dr['service_item'] ?? '');
                                $cust  = htmlspecialchars($dr['customer_name'] ?? '');
                                $price = $dr ? number_format((float)$dr['price'], 2, '.', '') : '';
                                $date  = htmlspecialchars($dr['entry_date'] ?? date('Y-m-d'));
                                $time  = htmlspecialchars($dr['entry_time'] ?? date('H:i'));
                                $note  = htmlspecialchars($dr['notes'] ?? '');
                            ?>
                            <tr>
                                <td class="col-row-num"><?php echo $idx + 1; ?></td>
                                <td><input type="text" name="staff_names[]" value="<?php echo htmlspecialchars($dr['staff_name'] ?? ''); ?>" placeholder="Staff name..."></td>
                                <td><input type="text" name="rows[]" value="<?php echo $svc; ?>" placeholder="Type service or product..." autocomplete="off"></td>
                                <td><input type="text" name="customer_names[]" value="<?php echo $cust; ?>" placeholder="Customer name..."></td>
                                <td><input type="number" name="prices[]" value="<?php echo $price !== '0.00' ? $price : ''; ?>" placeholder="0.00" step="0.01" min="0" oninput="updateTotal()"></td>
                                <td><input type="date" name="dates[]" value="<?php echo $date; ?>"></td>
                                <td><input type="time" name="times[]" value="<?php echo $time; ?>"></td>
                                <td><input type="text" name="notes_arr[]" value="<?php echo $note; ?>" placeholder="Optional notes..."></td>
                                <td><button type="button" class="btn-row-del" onclick="removeRow(this)" title="Remove row">✕</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="xl-total-bar">
                    <div>Rows: <span id="rowCount"><?php echo count($render_rows); ?></span></div>
                    <div>Total: ₱<span id="runningTotal">0.00</span></div>
                    <?php if (!empty($draft_rows)): ?>
                        <div style="color:#28a745;font-weight:600;">Draft loaded from last save</div>
                    <?php endif; ?>
                </div>

                <div class="xl-tabs">
                    <div class="xl-tab active">Sheet1</div>
                </div>
            </div>
        </form>

        <!-- HISTORY -->
        <div class="content-box">
            <h2>Report History</h2>

            <form method="GET" action="">
                <div class="filter-bar">
                    <div><label>From</label><input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>"></div>
                    <div><label>To</label><input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>"></div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="?date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-primary">Today</a>
                    <a href="?date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-primary">This Month</a>
                    <a href="?date_from=<?php echo date('Y-m-01', strtotime('-1 month')); ?>&date_to=<?php echo date('Y-m-t', strtotime('-1 month')); ?>" class="btn btn-sm btn-primary">Last Month</a>
                </div>
            </form>

            <div class="summary-strip">
                <div class="strip-card green">
                    <div class="sv">&#x20B1;<?php echo number_format($hist_total, 2); ?></div>
                    <div class="sl">Total Amount</div>
                </div>
                <div class="strip-card purple">
                    <div class="sv"><?php echo $hist_count; ?></div>
                    <div class="sl">Reports Submitted</div>
                </div>
                <div class="strip-card">
                    <div class="sv"><?php echo array_sum(array_column($submissions, 'total_entries')); ?></div>
                    <div class="sl">Total Entries</div>
                </div>
            </div>

            <?php if (empty($submissions)): ?>
                <div style="text-align:center;padding:36px 20px;color:#999;">
                    <p>No completed reports for this period yet.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="hist-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date Submitted</th>
                                <th>Total Amount</th>
                                <th>Entries</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submissions as $i => $sub): ?>
                            <?php $sub_details = $detail_rows[$sub['submission_id']] ?? []; ?>
                            <tr class="sub-summary-row" onclick="toggleDetails(<?php echo $sub['submission_id']; ?>)" style="cursor:pointer;">
                                <td><?php echo $i + 1; ?></td>
                                <td>
                                    <strong><?php echo date('M d, Y', strtotime($sub['report_date'])); ?></strong>
                                    <br><small style="color:#888;"><?php echo date('g:i A', strtotime($sub['submitted_at'])); ?></small>
                                </td>
                                <td><strong>&#x20B1;<?php echo number_format($sub['total_amount'], 2); ?></strong></td>
                                <td><?php echo $sub['total_entries']; ?> item<?php echo $sub['total_entries'] != 1 ? 's' : ''; ?></td>
                                <td>
                                    <?php if ($sub['status'] === 'sent' || $sub['status'] === 'seen'): ?>
                                        <span class="badge-sent">Sent to Admin</span>
                                    <?php else: ?>
                                        <span class="badge-completed">Completed</span>
                                    <?php endif; ?>
                                </td>
                                <td onclick="event.stopPropagation()" style="white-space:nowrap;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                    <button class="btn-expand" id="expbtn-<?php echo $sub['submission_id']; ?>"
                                            onclick="toggleDetails(<?php echo $sub['submission_id']; ?>)">View</button>
                                    <?php if ($sub['status'] === 'completed'): ?>
                                        <a href="?send_sub=<?php echo $sub['submission_id']; ?>&date_from=<?php echo urlencode($filter_date_from); ?>&date_to=<?php echo urlencode($filter_date_to); ?>"
                                           class="btn-send-admin"
                                           onclick="return confirm('Send this report to the admin?')">Send to Admin</a>
                                    <?php else: ?>
                                        <span class="readonly-note">Sent — contact admin to edit</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <!-- Expandable detail rows -->
                            <tr id="details-<?php echo $sub['submission_id']; ?>" class="sub-detail-row" style="display:none;">
                                <td colspan="6" style="padding:0;background:#f8f9fa;">
                                    <table class="detail-inner-table">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Staff Name</th>
                                                <th>Service / Product</th>
                                                <th>Customer Name</th>
                                                <th>Price</th>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sub_details as $di => $row): ?>
                                            <tr>
                                                <td><?php echo $di + 1; ?></td>
                                                <td><?php echo htmlspecialchars($row['staff_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['service_item']); ?></td>
                                                <td><?php echo htmlspecialchars($row['customer_name'] ?? ''); ?></td>
                                                <td>&#x20B1;<?php echo number_format($row['price'], 2); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($row['entry_date'])); ?></td>
                                                <td><?php echo date('g:i A', strtotime($row['entry_time'])); ?></td>
                                                <td style="color:#888;font-style:italic;"><?php echo !empty($row['notes']) ? htmlspecialchars($row['notes']) : '&#x2014;'; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="3" style="text-align:right;font-weight:700;">TOTAL</td>
                                                <td style="font-weight:700;">&#x20B1;<?php echo number_format($sub['total_amount'], 2); ?></td>
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

<!-- CONFIRM MODAL -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <h3 id="modalTitle">Confirm Action</h3>
        <p id="modalDesc" style="color:#555;font-size:.92rem;margin:0 0 4px;"></p>
        <div class="modal-preview" id="modalPreview"></div>
        <div class="modal-actions">
            <button type="button" class="btn btn-warning" onclick="closeModal()">Cancel</button>
            <button type="button" id="modalConfirmBtn" class="btn btn-success" onclick="confirmAction()">Confirm</button>
        </div>
    </div>
</div>

<script>
function addRow() {
    const tbody = document.getElementById('xlBody');
    const today = new Date().toISOString().split('T')[0];
    const now   = new Date().toTimeString().slice(0,5);
    const tr    = document.createElement('tr');
    tr.innerHTML = `
        <td class="col-row-num"></td>
        <td><input type="text" name="staff_names[]" placeholder="Staff name..."></td>
        <td><input type="text" name="rows[]" placeholder="Type service or product..." autocomplete="off"></td>
        <td><input type="text" name="customer_names[]" placeholder="Customer name..."></td>
        <td><input type="number" name="prices[]" placeholder="0.00" step="0.01" min="0" oninput="updateTotal()"></td>
        <td><input type="date" name="dates[]" value="${today}"></td>
        <td><input type="time" name="times[]" value="${now}"></td>
        <td><input type="text" name="notes_arr[]" placeholder="Optional notes..."></td>
        <td><button type="button" class="btn-row-del" onclick="removeRow(this)" title="Remove row">&#x2715;</button></td>
    `;
    tbody.appendChild(tr);
    renumberRows();
    updateTotal();
    tr.querySelector('input[name="rows[]"]').focus();
}

function removeRow(btn) {
    btn.closest('tr').remove();
    renumberRows();
    updateTotal();
}

function renumberRows() {
    document.querySelectorAll('#xlBody tr').forEach((r, i) => {
        const n = r.querySelector('.col-row-num');
        if (n) n.textContent = i + 1;
    });
    document.getElementById('rowCount').textContent =
        document.querySelectorAll('#xlBody tr').length;
}

function updateTotal() {
    let total = 0;
    document.querySelectorAll('#xlBody input[name="prices[]"]')
        .forEach(inp => { total += parseFloat(inp.value || 0); });
    document.getElementById('runningTotal').textContent = total.toFixed(2);
}
updateTotal();

function saveDraft() {
    document.getElementById('formAction').value = 'save_draft';
    document.getElementById('reportForm').submit();
}

function openModal(action) {
    let hasEntry = false;
    document.querySelectorAll('#xlBody input[name="rows[]"]').forEach(inp => {
        if (inp.value.trim() !== '') hasEntry = true;
    });
    if (!hasEntry) {
        alert('Please add at least one service or product entry before proceeding.');
        return;
    }

    let rows = document.querySelectorAll('#xlBody tr');
    let tableHTML = `<table>
        <thead><tr><th>#</th><th>Staff Name</th><th>Service / Product</th><th>Customer</th><th>Price</th><th>Date</th><th>Time</th></tr></thead>
        <tbody>`;
    let total = 0, count = 0;
    rows.forEach(row => {
        const svc   = row.querySelector('input[name="rows[]"]')?.value.trim() || '';
        const sname = row.querySelector('input[name="staff_names[]"]')?.value.trim() || '';
        const price = parseFloat(row.querySelector('input[name="prices[]"]')?.value || 0);
        const date  = row.querySelector('input[name="dates[]"]')?.value || '';
        const time  = row.querySelector('input[name="times[]"]')?.value || '';
        if (svc === '') return;
        count++; total += price;
        tableHTML += `<tr>
            <td>${count}</td><td>${escHtml(sname)||'—'}</td><td>${escHtml(svc)}</td>
            <td>&#x20B1;${price.toFixed(2)}</td><td>${date}</td><td>${time}</td>
        </tr>`;
    });
    tableHTML += `</tbody>
        <tfoot><tr>
            <td colspan="3" style="text-align:right;font-weight:700;">TOTAL</td>
            <td style="font-weight:700;color:#1e6fc3;">&#x20B1;${total.toFixed(2)}</td>
            <td colspan="2"></td>
        </tr></tfoot></table>`;

    document.getElementById('modalPreview').innerHTML = tableHTML;
    document.getElementById('modalTitle').textContent = 'Complete Report';
    document.getElementById('modalDesc').textContent  =
        'These entries will be saved to your report history below. The sheet will be cleared after.';
    document.getElementById('modalConfirmBtn').textContent = 'Yes, Complete Report';
    document.getElementById('modalConfirmBtn').className   = 'btn btn-complete';
    document.getElementById('modalConfirmBtn').setAttribute('data-action', action);
    document.getElementById('confirmModal').classList.add('show');
}

function closeModal() { document.getElementById('confirmModal').classList.remove('show'); }

function confirmAction() {
    const action = document.getElementById('modalConfirmBtn').getAttribute('data-action');
    document.getElementById('formAction').value = action;
    document.getElementById('confirmModal').classList.remove('show');
    document.getElementById('reportForm').submit();
}

document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}

function toggleDetails(subId) {
    const detailRow = document.getElementById('details-' + subId);
    const btn       = document.getElementById('expbtn-' + subId);
    if (!detailRow) return;
    const isOpen = detailRow.style.display !== 'none';
    detailRow.style.display = isOpen ? 'none' : 'table-row';
    if (btn) {
        btn.textContent = isOpen ? 'View' : 'Hide';
        btn.classList.toggle('open', !isOpen);
    }
}
</script>
</body>
</html>