<?php
/**
 * Point of Sales (POS) - Admin
 * Tile-based cart — services & products synced with database
 */

require_once '../config/auth.php';
requireAdmin();

$pdo      = getPDOConnection();
$success  = '';
$error    = '';
$admin_id = getCurrentUserId();

// ── ADD SERVICE → inserts into salon_services ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_service') {
    $name     = sanitizeInput($_POST['svc_name']     ?? '');
    $price    = floatval($_POST['svc_price']          ?? 0);
    $category = sanitizeInput($_POST['svc_category'] ?? 'General');
    if ($name === '') {
        $error = 'Service name is required.';
    } elseif ($price < 0) {
        $error = 'Price cannot be negative.';
    } else {
        try {
            $pdo->prepare("
                INSERT INTO salon_services (service_name, price, category, status)
                VALUES (?, ?, ?, 'active')
                ON DUPLICATE KEY UPDATE price = VALUES(price), category = VALUES(category), status = 'active'
            ")->execute([$name, $price, $category ?: 'General']);
            $success = 'Service "' . htmlspecialchars($name) . '" saved to database.';
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// ── ADD PRODUCT → inserts into products ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_product') {
    $name  = sanitizeInput($_POST['prod_name']  ?? '');
    $price = floatval($_POST['prod_price']       ?? 0);
    $qty   = intval($_POST['prod_qty']           ?? 0);
    if ($name === '') {
        $error = 'Product name is required.';
    } elseif ($price < 0) {
        $error = 'Price cannot be negative.';
    } else {
        try {
            $status = $qty > 0 ? 'available' : 'out_of_stock';
            $pdo->prepare("
                INSERT INTO products (product_name, price, quantity, status)
                VALUES (?, ?, ?, ?)
            ")->execute([$name, $price, $qty, $status]);
            $success = 'Product "' . htmlspecialchars($name) . '" saved to database.';
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// ── REMOVE SERVICE → hard deletes from salon_services ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_service') {
    $id = intval($_POST['svc_id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM salon_services WHERE service_id = ?")->execute([$id]);
        $success = 'Service deleted from database.';
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// ── REMOVE PRODUCT → hard deletes from products ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_product') {
    $id = intval($_POST['prod_id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM products WHERE product_id = ?")->execute([$id]);
        $success = 'Product deleted from database.';
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// ── DELETE a sale ──────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare("SELECT product_id, quantity FROM sales WHERE sale_id = ? AND status = 'approved'");
        $s->execute([$del_id]);
        $row = $s->fetch();
        if ($row && $row['product_id']) {
            $pdo->prepare("UPDATE products SET quantity = quantity + ?, status = 'available' WHERE product_id = ?")->execute([$row['quantity'], $row['product_id']]);
        }
        $pdo->prepare("DELETE FROM sales WHERE sale_id = ?")->execute([$del_id]);
        $pdo->commit();
        $success = 'Transaction #' . $del_id . ' removed.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error: ' . $e->getMessage();
    }
}

// ── EDIT a sale ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_sale') {
    $edit_id        = intval($_POST['edit_sale_id']);
    $sale_date      = sanitizeInput($_POST['edit_sale_date']);
    $service_name   = sanitizeInput($_POST['edit_service_name'] ?? '');
    $quantity       = intval($_POST['edit_quantity']);
    $unit_price     = floatval($_POST['edit_unit_price']);
    $payment_method = sanitizeInput($_POST['edit_payment_method']);
    try {
        $pdo->prepare("UPDATE sales SET sale_date=?, service_name=?, quantity=?, unit_price=?, total_amount=?, payment_method=?, approved_at=NOW() WHERE sale_id=?")
            ->execute([$sale_date, $service_name, $quantity, $unit_price, $unit_price, $payment_method, $edit_id]);
        $success = 'Transaction #' . $edit_id . ' updated.';
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// ── COMPLETE SALE — one combined row ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete_sale') {
    $sale_date      = sanitizeInput($_POST['sale_date']);
    $staff_id       = (isset($_POST['staff_id']) && intval($_POST['staff_id']) > 0) ? intval($_POST['staff_id']) : null;
    $payment_method = sanitizeInput($_POST['payment_method']);
    $customer_name  = sanitizeInput($_POST['customer_name'] ?? '');
    $cart_items     = json_decode($_POST['cart_data'] ?? '[]', true);

    if (empty($cart_items)) {
        $error = 'Cart is empty.';
    } else {
        try {
            $pdo->beginTransaction();
            $names       = [];
            $grand_total = 0.0;
            foreach ($cart_items as $item) {
                $names[]      = $item['name'] . ' x' . $item['qty'];
                $grand_total += floatval($item['price']) * intval($item['qty']);
            }
            $label = implode(', ', $names);

            // Generate receipt number: RCP-YYYYMMDD-XXXXX (random 5-digit suffix)
            $receipt_no = 'RCP-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));

            // Save product_id if the cart contains exactly one product item
            $single_product_id = null;
            $product_items = array_filter($cart_items, function($i){ return $i['type'] === 'product'; });
            if (count($cart_items) === 1 && count($product_items) === 1) {
                $only = array_values($product_items)[0];
                $single_product_id = intval($only['id']);
            }

            // Try inserting with customer_name and receipt_no columns
            // Falls back gracefully if columns don't exist yet
            try {
                $pdo->prepare("
                    INSERT INTO sales (sale_date, staff_id, product_id, service_name, quantity, unit_price, total_amount, payment_method, status, approved_by, approved_at, customer_name, receipt_no)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), ?, ?)
                ")->execute([$sale_date, $staff_id, $single_product_id, $label, count($cart_items), $grand_total, $grand_total, $payment_method, $admin_id, $customer_name ?: null, $receipt_no]);
            } catch (Exception $colErr) {
                // Columns may not exist yet — insert without them
                $pdo->prepare("
                    INSERT INTO sales (sale_date, staff_id, product_id, service_name, quantity, unit_price, total_amount, payment_method, status, approved_by, approved_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW())
                ")->execute([$sale_date, $staff_id, $single_product_id, $label, count($cart_items), $grand_total, $grand_total, $payment_method, $admin_id]);
                $receipt_no = '#' . $pdo->lastInsertId();
            }

            foreach ($cart_items as $item) {
                if ($item['type'] === 'product') {
                    $pid = intval($item['id']);
                    $qty = intval($item['qty']);
                    $pdo->prepare("UPDATE products SET quantity = quantity - ?, status = CASE WHEN (quantity - ?) <= 0 THEN 'out_of_stock' ELSE 'available' END WHERE product_id = ?")->execute([$qty, $qty, $pid]);
                }
            }
            $pdo->commit();
            $txn = $pdo->lastInsertId();
            $success = 'Sale recorded! Receipt: <strong>' . htmlspecialchars($receipt_no) . '</strong> — Total: &#x20B1;' . number_format($grand_total, 2);
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// ── DATA FETCHES ───────────────────────────────────────────────────────────────
$staff_list = $pdo->query("SELECT staff_id, full_name FROM staff WHERE status='active' ORDER BY full_name")->fetchAll();
$products   = $pdo->query("SELECT product_id, product_name, price, quantity FROM products WHERE status='available' ORDER BY product_name")->fetchAll();

try {
    $salon_services = $pdo->query("SELECT service_id, service_name, price, category FROM salon_services WHERE status='active' ORDER BY service_name")->fetchAll();
} catch (Exception $e) {
    $salon_services = [];
}

// Fetch recent sales — try with customer_name/receipt_no, fall back if columns missing
try {
    $recent_sales = $pdo->query("
        SELECT s.sale_id, s.sale_date, s.approved_at, s.quantity, s.total_amount,
               s.payment_method, s.service_name, s.unit_price,
               COALESCE(NULLIF(s.manual_staff_name,''), st.full_name, u.full_name) AS staff_name,
               s.customer_name, s.receipt_no
        FROM   sales s
        LEFT JOIN staff st ON s.staff_id = st.staff_id
        LEFT JOIN users u  ON s.staff_id = u.user_id
        WHERE  s.status = 'approved'
        ORDER  BY s.approved_at DESC, s.sale_id DESC
        LIMIT  15
    ")->fetchAll();
} catch (Exception $e) {
    $recent_sales = $pdo->query("
        SELECT s.sale_id, s.sale_date, s.approved_at, s.quantity, s.total_amount,
               s.payment_method, s.service_name, s.unit_price,
               COALESCE(NULLIF(s.manual_staff_name,''), st.full_name, u.full_name) AS staff_name,
               NULL AS customer_name, NULL AS receipt_no
        FROM   sales s
        LEFT JOIN staff st ON s.staff_id = st.staff_id
        LEFT JOIN users u  ON s.staff_id = u.user_id
        WHERE  s.status = 'approved'
        ORDER  BY s.approved_at DESC, s.sale_id DESC
        LIMIT  15
    ")->fetchAll();
}

$products_json = json_encode($products,       JSON_UNESCAPED_UNICODE);
$services_json = json_encode($salon_services, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Point of Sales – Salon Management</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="icon" href="../assets/images/icon.png">
<style>
.pos-wrap { display:flex; overflow:hidden; min-height:300px; }

/* ── LEFT ── */
.pos-left { flex:1; display:flex; flex-direction:column; overflow:hidden; background:#f8f9fa; }
.pos-tabs { display:flex; border-bottom:2px solid #dee2e6; background:#fff; padding:0 16px; }
.pos-tab  { padding:12px 18px; font-size:13px; font-weight:600; cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px; color:#666; }
.pos-tab.active { color:#333; border-bottom-color:#4a6cf7; }

/* toolbar */
.tile-toolbar { display:flex; align-items:center; gap:6px; padding:8px 14px; background:#fff; border-bottom:1px solid #dee2e6; flex-wrap:wrap; }
.tile-toolbar > span { font-size:12px; font-weight:600; color:#555; }
.tbtn { padding:5px 12px; font-size:12px; font-weight:600; border-radius:5px; cursor:pointer; border:1px solid; transition:background .15s; }
.tbtn-add { background:#e8f5e9; color:#2e7d32; border-color:#a5d6a7; }
.tbtn-add:hover  { background:#c8e6c9; }
.tbtn-del { background:#fce8e8; color:#c62828; border-color:#ef9a9a; }
.tbtn-del:hover  { background:#ffcdd2; }
.tbtn-del.active { background:#dc3545; color:#fff; border-color:#dc3545; }
.del-hint { font-size:11px; color:#c62828; background:#fce8e8; border:1px solid #ef9a9a; border-radius:5px; padding:3px 8px; display:none; }
.del-hint.visible { display:inline-block; }

.pos-search { padding:10px 14px; background:#fff; border-bottom:1px solid #dee2e6; }
.pos-search input { width:100%; padding:7px 12px; border:1px solid #ced4da; border-radius:6px; font-size:13px; outline:none; }
.pos-search input:focus { border-color:#4a6cf7; }

.tiles-scroll { flex:1; overflow-y:auto; padding:12px 14px; }
.tile-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#999; margin-bottom:8px; }
.tiles-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(120px,1fr)); gap:8px; margin-bottom:14px; }

.tile { background:#fff; border:1px solid #dee2e6; border-radius:8px; padding:16px 10px; cursor:pointer; text-align:center; transition:border-color .15s; user-select:none; position:relative; }
.tile:hover { border-color:#4a6cf7; }
.tile.in-cart { border-color:#28a745; background:#f0fff4; }
.tile.in-cart::after { content:'✓'; position:absolute; top:5px; right:7px; font-size:10px; color:#28a745; font-weight:700; }
.tile-name  { font-size:12px; font-weight:600; color:#333; line-height:1.4; margin-bottom:5px; }
.tile-price { font-size:13px; font-weight:700; color:#4a6cf7; }
.tile.svc-tile { background:#f0f4ff; border-color:#c7d7fd; }
.tile.svc-tile .tile-price { color:#2d4cc7; }
.tile.custom-tile { border-style:dashed; border-color:#adb5bd; background:#fafafa; }
.tile.custom-tile .tile-name { color:#6c757d; }
.tile.custom-tile .tile-price { color:#aaa; }

/* del mode */
.tile.del-mode { cursor:default; }
.tile.del-mode:hover { border-color:#dee2e6; }
.tile.svc-tile.del-mode:hover { border-color:#c7d7fd; }
.del-x { display:none; position:absolute; top:-7px; right:-7px; width:20px; height:20px; background:#dc3545; color:#fff; border-radius:50%; font-size:12px; font-weight:700; align-items:center; justify-content:center; cursor:pointer; z-index:10; border:2px solid #fff; box-shadow:0 1px 4px rgba(0,0,0,.2); }
.tile.del-mode .del-x { display:flex; }

.no-svc-note { background:#fff3cd; color:#856404; border:1px solid #ffc107; border-radius:6px; padding:10px 12px; font-size:12px; margin-bottom:12px; }

/* ── Edit modal service list ── */
.em-svc-list { max-height:220px; overflow-y:auto; border:1px solid #dee2e6; border-radius:6px; margin-bottom:12px; }
.em-svc-item { display:flex; align-items:center; gap:10px; padding:9px 12px; border-bottom:1px solid #f0f0f0; cursor:pointer; }
.em-svc-item:last-child { border-bottom:none; }
.em-svc-item:hover { background:#f8f9fa; }
.em-svc-item input[type=checkbox] { width:16px; height:16px; accent-color:#4a6cf7; cursor:pointer; flex-shrink:0; }
.em-svc-label { flex:1; font-size:13px; font-weight:600; color:#333; }
.em-svc-price { font-size:13px; font-weight:700; color:#4a6cf7; white-space:nowrap; }
.em-selected-list { background:#fff8f0; border:1px solid #fde8cc; border-radius:6px; padding:10px 12px; margin-bottom:12px; min-height:48px; }
.em-selected-item { display:flex; justify-content:space-between; font-size:12px; color:#555; padding:3px 0; }
.em-selected-item strong { color:#333; }
.em-selected-total { display:flex; justify-content:space-between; font-size:13px; font-weight:700; color:#c0392b; margin-top:8px; padding-top:6px; border-top:1px solid #fde8cc; }
.em-empty-note { color:#bbb; font-size:12px; text-align:center; padding:6px 0; }

/* ── Modals ── */
.mo { display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:1000; align-items:center; justify-content:center; }
.mo.open { display:flex; }
.mb { background:#fff; border-radius:10px; padding:22px 24px; width:100%; max-width:420px; box-shadow:0 4px 20px rgba(0,0,0,.18); position:relative; }
.mb.wide { max-width:520px; }
.mb h4 { margin:0 0 16px; font-size:15px; color:#333; }
.mc { position:absolute; top:12px; right:14px; background:none; border:none; font-size:18px; cursor:pointer; color:#888; }
.mc:hover { color:#333; }
.mg { margin-bottom:12px; }
.mg label { display:block; font-size:12px; font-weight:600; color:#555; margin-bottom:4px; text-transform:uppercase; letter-spacing:.3px; }
.mg input, .mg select { width:100%; padding:8px 10px; border:1px solid #ced4da; border-radius:6px; font-size:13px; outline:none; box-sizing:border-box; }
.mg input:focus { border-color:#4a6cf7; }
.mr { display:flex; gap:10px; flex-wrap:wrap; }
.mr .mg { flex:1; min-width:140px; }
.ma { display:flex; gap:8px; margin-top:16px; }
.ma button { flex:1; padding:10px; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; border:none; }
.ba { background:#4a6cf7; color:#fff; }
.ba:hover { background:#3558e0; }
.bc { background:#e9ecef; color:#555; }
.bc:hover { background:#dee2e6; }
.ro { background:#f8f9fa; font-weight:700; }

.items-col { font-size:11px; color:#555; max-width:280px; word-break:break-word; line-height:1.5; }

/* tab indicator for type switcher */
.type-tabs { display:flex; gap:0; margin-bottom:16px; border:1px solid #dee2e6; border-radius:7px; overflow:hidden; }
.type-tab { flex:1; padding:8px; text-align:center; font-size:13px; font-weight:600; cursor:pointer; background:#f8f9fa; color:#666; transition:background .15s; }
.type-tab.active { background:#4a6cf7; color:#fff; }
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
            <a href="pos.php" class="active">&#x1F4B0; Point of Sales</a>
            <a href="sales_reports.php">&#x1F4C8; Sales Reports</a>
            <a href="staff_reports_admin.php">&#x1F4CB; Staff Reports</a>
            <a href="inventory.php">&#x1F4E6; Inventory</a>
            <a href="staff_duties.php">&#x1F4CB; Staff Duties</a>
            <a href="staff_management.php">&#x1F465; Staff Management</a>
        </nav>
    </aside>

    <main class="main-content" style="padding:0;overflow-y:auto;display:flex;flex-direction:column;">

        <div class="top-bar" style="padding:12px 20px;flex-shrink:0;">
            <h1>Point of Sales (POS)</h1>
            <div class="user-info">
                <span style="margin-right:15px;">Welcome, <strong><?php echo getCurrentUserName(); ?></strong></span>
                <a href="settings.php" class="btn btn-primary">Settings</a>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="success-message" style="margin:0 16px 6px;"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message" style="margin:0 16px 6px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="pos-wrap" style="flex:1;">

            <!-- LEFT: tiles -->
            <div class="pos-left">
                <div class="pos-tabs">
                    <div class="pos-tab active" id="tabSvc"  onclick="switchTab('services')">Services</div>
                    <div class="pos-tab"         id="tabProd" onclick="switchTab('products')">Products</div>
                </div>

                <!-- Manage toolbar -->
                <div class="tile-toolbar">
                    <span>Manage:</span>
                    <button class="tbtn tbtn-add" onclick="openAddModal()">+ Add Service / Product</button>
                    <button class="tbtn tbtn-del" id="btnDelMode" onclick="toggleDelMode()">&#x2715; Remove Item</button>
                    <span class="del-hint" id="delHint">Click the &#x2715; on a tile to delete it from the database</span>
                </div>

                <div class="pos-search">
                    <input type="text" id="searchBox" placeholder="Search..." oninput="renderTiles()">
                </div>
                <div class="tiles-scroll" id="tilesArea"></div>
            </div>

        </div>

        <!-- Recent Sales -->
        <div class="content-box" style="margin:14px 16px;overflow-y:auto;max-height:320px;">
            <h2>Recent Sales Transactions</h2>
            <?php if (empty($recent_sales)): ?>
                <div style="background:#d1ecf1;color:#0c5460;padding:12px;border-radius:6px;font-size:13px;">No sales recorded yet.</div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Receipt #</th><th>Date &amp; Time</th><th>Customer</th><th>Staff</th>
                                <th>Services / Products</th><th>Total</th><th>Payment</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent_sales as $sale): ?>
                            <tr>
                                <td>
                                    <span style="font-size:11px;font-weight:700;color:#4a6cf7;font-family:monospace;">
                                        <?php echo !empty($sale['receipt_no']) ? htmlspecialchars($sale['receipt_no']) : ('#' . $sale['sale_id']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($sale['sale_date'])); ?><br>
                                    <small style="color:#888;"><?php echo (!empty($sale['approved_at']) && $sale['approved_at'] !== '0000-00-00 00:00:00') ? date('g:i A', strtotime($sale['approved_at'])) : 'N/A'; ?></small>
                                </td>
                                <td>
                                    <?php echo !empty($sale['customer_name']) ? htmlspecialchars($sale['customer_name']) : '<span style="color:#bbb;font-size:11px;">Walk-in</span>'; ?>
                                </td>
                                <td><?php echo htmlspecialchars($sale['staff_name'] ?? 'N/A'); ?></td>
                                <td><div class="items-col"><?php echo htmlspecialchars($sale['service_name'] ?? 'N/A'); ?></div></td>
                                <td><strong>&#x20B1;<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                                <td><?php echo ucfirst($sale['payment_method']); ?></td>
                                <td style="white-space:nowrap;">
                                    <button type="button" class="btn btn-primary btn-sm"
                                        onclick="openEM(<?php echo $sale['sale_id']; ?>,'<?php echo $sale['sale_date']; ?>','<?php echo htmlspecialchars(addslashes($sale['service_name'] ?? ''), ENT_QUOTES); ?>',<?php echo intval($sale['quantity']); ?>,<?php echo floatval($sale['unit_price']); ?>,'<?php echo $sale['payment_method']; ?>')">Edit</button>
                                    <a href="?delete=<?php echo $sale['sale_id']; ?>" class="btn btn-danger btn-sm"
                                       onclick="return confirm('Remove Transaction #<?php echo $sale['sale_id']; ?>?')">Remove</a>
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

<!-- ══ ADD SERVICE / PRODUCT MODAL ══════════════════════════════════════════ -->
<div class="mo" id="addModal">
    <div class="mb wide">
        <button class="mc" onclick="closeAddModal()">&#x2715;</button>
        <h4 id="addModalTitle">Add New Service</h4>

        <!-- type switcher -->
        <div class="type-tabs">
            <div class="type-tab active" id="typeTabSvc"  onclick="switchAddType('service')">Service</div>
            <div class="type-tab"        id="typeTabProd" onclick="switchAddType('product')">Product</div>
        </div>

        <!-- SERVICE form -->
        <div id="addSvcFields">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_service">
                <div class="mr">
                    <div class="mg">
                        <label>Service Name *</label>
                        <input type="text" name="svc_name" placeholder="e.g., Hair Botox" required>
                    </div>
                    <div class="mg">
                        <label>Price (&#x20B1;) *</label>
                        <input type="number" name="svc_price" step="0.01" min="0" placeholder="0.00" required>
                    </div>
                </div>
                <div class="mg">
                    <label>Category</label>
                    <input type="text" name="svc_category" placeholder="e.g., Hair, Nails, Skin">
                </div>
                <div class="ma">
                    <button type="submit" class="ba">Save Service to Database</button>
                    <button type="button" class="bc" onclick="closeAddModal()">Cancel</button>
                </div>
            </form>
        </div>

        <!-- PRODUCT form -->
        <div id="addProdFields" style="display:none;">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_product">
                <div class="mr">
                    <div class="mg">
                        <label>Product Name *</label>
                        <input type="text" name="prod_name" placeholder="e.g., Shampoo 250ml" required>
                    </div>
                    <div class="mg">
                        <label>Price (&#x20B1;) *</label>
                        <input type="number" name="prod_price" step="0.01" min="0" placeholder="0.00" required>
                    </div>
                </div>
                <div class="mr">
                    <div class="mg">
                        <label>Stock Quantity *</label>
                        <input type="number" name="prod_qty" min="0" placeholder="0" required>
                    </div>

                </div>
                <div class="ma">
                    <button type="submit" class="ba">Save Product to Database</button>
                    <button type="button" class="bc" onclick="closeAddModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ══ EDIT SALE MODAL ══════════════════════════════════════════════════════ -->
<div class="mo" id="emModal">
    <div class="mb wide">
        <button class="mc" onclick="closeEM()">&#x2715;</button>
        <h4>&#x2702; Edit Transaction <span id="emLabel"></span></h4>
        <form method="POST" action="" id="emForm">
            <input type="hidden" name="action" value="edit_sale">
            <input type="hidden" name="edit_sale_id" id="emId">
            <input type="hidden" name="edit_service_name" id="emSvcName">
            <input type="hidden" name="edit_quantity" id="emQty">
            <input type="hidden" name="edit_unit_price" id="emPrice">

            <div class="mr">
                <div class="mg">
                    <label>Sale Date *</label>
                    <input type="date" name="edit_sale_date" id="emDate" required>
                </div>
                <div class="mg">
                    <label>Payment *</label>
                    <select name="edit_payment_method" id="emPayment">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="online">Online Payment</option>
                    </select>
                </div>
            </div>

            <div class="mg">
                <label>Select Services &amp; Products</label>
                <div class="em-svc-list" id="emSvcList">
                    <!-- populated by JS -->
                </div>
            </div>

            <div class="mg">
                <label>Selected Items</label>
                <div class="em-selected-list" id="emSelectedList">
                    <div class="em-empty-note">No items selected.</div>
                </div>
            </div>

            <div class="ma">
                <button type="submit" class="btn btn-success" id="emSaveBtn">Save Changes</button>
                <button type="button" class="btn btn-warning" onclick="closeEM()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- hidden remove-service form -->
<form id="rmSvcForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action" value="remove_service">
    <input type="hidden" name="svc_id" id="rmSvcId">
</form>

<!-- hidden remove-product form -->
<form id="rmProdForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action" value="remove_product">
    <input type="hidden" name="prod_id" id="rmProdId">
</form>

<script>
/* ── Data from PHP ── */
var PRODUCTS = <?php echo $products_json; ?>.map(function(p) {
    return { id: p.product_id, name: p.product_name, price: parseFloat(p.price), stock: parseInt(p.quantity), type: 'product' };
});

var SERVICES = <?php echo $services_json; ?>.map(function(s) {
    return { id: s.service_id, name: s.service_name, price: parseFloat(s.price), category: s.category || '', type: 'service' };
});

/* ── State ── */
var cart      = [];
var activeTab = 'services';
var delMode   = false;

function cKey(type, id) { return type + '||' + String(id); }

/* ── Render tiles ── */
function renderTiles() {
    var area   = document.getElementById('tilesArea');
    var q      = document.getElementById('searchBox').value.toLowerCase().trim();
    var html   = '';

    if (activeTab === 'services') {
        if (SERVICES.length === 0) {
            html += '<div class="no-svc-note">No services in database yet. Click <strong>+ Add Service / Product</strong> above to add your first service.</div>';
        }
        var list = SERVICES.filter(function(s) { return !q || s.name.toLowerCase().indexOf(q) >= 0; });
        if (list.length > 0) {
            html += '<div class="tile-label">Salon Services</div><div class="tiles-grid">';
            list.forEach(function(s) {
                var dm     = delMode ? ' del-mode' : '';
                var click  = delMode ? '' : '';
                var delBtn = delMode ? '<span class="del-x" onclick="confirmRemoveSvc(' + s.id + ',\'' + escA(s.name) + '\',event)">&#x2715;</span>' : '';
                html += '<div class="tile svc-tile' + dm + '">'
                      + delBtn
                      + '<div class="tile-name">' + esc(s.name) + '</div>'
                      + '<div class="tile-price">&#x20B1;' + s.price.toFixed(2) + '</div>'
                      + '</div>';
            });
            html += '</div>';
        }
        if (!q) {
            html += '<div class="tiles-grid">'
                  + '<div class="tile custom-tile">'
                  + '<div style="font-size:26px;margin-bottom:4px;">+</div>'
                  + '<div class="tile-name">Custom Service</div>'
                  + '<div class="tile-price">Session only</div>'
                  + '</div></div>';
        }
    } else {
        var plist = PRODUCTS.filter(function(p) { return !q || p.name.toLowerCase().indexOf(q) >= 0; });
        html += '<div class="tile-label">Products</div><div class="tiles-grid">';
        plist.forEach(function(p) {
            var dm     = delMode ? ' del-mode' : '';
            var delBtn = delMode ? '<span class="del-x" onclick="confirmRemoveProd(' + p.id + ',\'' + escA(p.name) + '\',event)">&#x2715;</span>' : '';
            html += '<div class="tile' + dm + '">'
                  + delBtn
                  + '<div class="tile-name">' + esc(p.name) + '</div>'
                  + '<div class="tile-price">&#x20B1;' + p.price.toFixed(2) + '</div>'
                  + '<div style="font-size:10px;color:#aaa;margin-top:2px;">Stock: ' + p.stock + '</div>'
                  + '</div>';
        });
        if (!plist.length) html += '<div style="color:#aaa;font-size:13px;grid-column:1/-1;padding:14px 0;">No products found.</div>';
        html += '</div>';
    }
    area.innerHTML = html;
}

function switchTab(tab) {
    activeTab = tab;
    document.getElementById('tabSvc').classList.toggle('active',  tab === 'services');
    document.getElementById('tabProd').classList.toggle('active', tab === 'products');
    document.getElementById('searchBox').value = '';
    renderTiles();
}

/* ── Delete mode ── */
function toggleDelMode() {
    delMode = !delMode;
    var btn  = document.getElementById('btnDelMode');
    var hint = document.getElementById('delHint');
    btn.classList.toggle('active', delMode);
    hint.classList.toggle('visible', delMode);
    renderTiles();
}

function confirmRemoveSvc(id, name, event) {
    event.stopPropagation();
    if (!confirm('Permanently delete service "' + name + '" from the database?')) return;
    document.getElementById('rmSvcId').value = id;
    document.getElementById('rmSvcForm').submit();
}

function confirmRemoveProd(id, name, event) {
    event.stopPropagation();
    if (!confirm('Permanently delete product "' + name + '" from the database?')) return;
    document.getElementById('rmProdId').value = id;
    document.getElementById('rmProdForm').submit();
}

/* ── Add modal ── */
var addModalType = 'service';
function openAddModal() {
    switchAddType(activeTab === 'products' ? 'product' : 'service');
    document.getElementById('addModal').classList.add('open');
}
function closeAddModal() { document.getElementById('addModal').classList.remove('open'); }
function switchAddType(type) {
    addModalType = type;
    document.getElementById('addSvcFields').style.display  = type === 'service' ? 'block' : 'none';
    document.getElementById('addProdFields').style.display = type === 'product' ? 'block' : 'none';
    document.getElementById('typeTabSvc').classList.toggle('active',  type === 'service');
    document.getElementById('typeTabProd').classList.toggle('active', type === 'product');
    document.getElementById('addModalTitle').textContent   = type === 'service' ? 'Add New Service' : 'Add New Product';
}
document.getElementById('addModal').addEventListener('click', function(e) {
    if (e.target === document.getElementById('addModal')) closeAddModal();
});

/* ── Edit modal — checkbox-based service/product picker ── */
var emChecked = {}; // key -> { name, price }

function openEM(id, date, items, qty, price, payment) {
    emChecked = {};

    // Pre-check items that were in the original transaction
    // items format: "Service A x1, Service B x1" — parse names
    var parts = items.split(', ');
    parts.forEach(function(part) {
        var m = part.match(/^(.+?)\s+x\d+$/);
        var name = m ? m[1].trim() : part.trim();
        // Find matching service
        var svc = SERVICES.find(function(s) { return s.name === name; });
        if (svc) { emChecked['service||' + svc.id] = { name: svc.name, price: svc.price }; return; }
        // Find matching product
        var prod = PRODUCTS.find(function(p) { return p.name === name; });
        if (prod) { emChecked['product||' + prod.id] = { name: prod.name, price: prod.price }; }
    });

    document.getElementById('emLabel').textContent = '#' + id;
    document.getElementById('emId').value   = id;
    document.getElementById('emDate').value = date;
    var payEl = document.getElementById('emPayment');
    for (var i = 0; i < payEl.options.length; i++) {
        if (payEl.options[i].value === payment) { payEl.selectedIndex = i; break; }
    }

    renderEMList();
    renderEMSelected();
    document.getElementById('emModal').classList.add('open');
}

function closeEM() { document.getElementById('emModal').classList.remove('open'); }
document.getElementById('emModal').addEventListener('click', function(e) {
    if (e.target === document.getElementById('emModal')) closeEM();
});

function renderEMList() {
    var html = '';
    SERVICES.forEach(function(s) {
        var k = 'service||' + s.id;
        var chk = emChecked[k] ? 'checked' : '';
        html += '<label class="em-svc-item">'
              + '<input type="checkbox" ' + chk + ' onchange="emToggle(\'service\',' + s.id + ',\'' + escA(s.name) + '\',' + s.price + ',this)">'
              + '<span class="em-svc-label">' + esc(s.name) + '</span>'
              + '<span class="em-svc-price">&#x20B1;' + s.price.toFixed(2) + '</span>'
              + '</label>';
    });
    PRODUCTS.forEach(function(p) {
        var k = 'product||' + p.id;
        var chk = emChecked[k] ? 'checked' : '';
        html += '<label class="em-svc-item">'
              + '<input type="checkbox" ' + chk + ' onchange="emToggle(\'product\',' + p.id + ',\'' + escA(p.name) + '\',' + p.price + ',this)">'
              + '<span class="em-svc-label">' + esc(p.name) + ' <span style="font-size:10px;color:#aaa;">[Product]</span></span>'
              + '<span class="em-svc-price">&#x20B1;' + p.price.toFixed(2) + '</span>'
              + '</label>';
    });
    document.getElementById('emSvcList').innerHTML = html || '<div class="em-empty-note">No services or products found.</div>';
}

function emToggle(type, id, name, price, cb) {
    var k = type + '||' + id;
    if (cb.checked) { emChecked[k] = { name: name, price: price }; }
    else            { delete emChecked[k]; }
    renderEMSelected();
}

function renderEMSelected() {
    var keys  = Object.keys(emChecked);
    var total = 0, names = [], html = '';
    keys.forEach(function(k) {
        var item = emChecked[k];
        total += item.price;
        names.push(item.name + ' x1');
        html += '<div class="em-selected-item"><span>' + esc(item.name) + '</span><strong>&#x20B1;' + item.price.toFixed(2) + '</strong></div>';
    });
    if (!keys.length) {
        html = '<div class="em-empty-note">No items selected.</div>';
    } else {
        html += '<div class="em-selected-total"><span>' + keys.length + ' item(s)</span><span>Total: &#x20B1;' + total.toFixed(2) + '</span></div>';
    }
    document.getElementById('emSelectedList').innerHTML = html;

    // Update hidden fields before submit
    document.getElementById('emSvcName').value = names.join(', ');
    document.getElementById('emQty').value     = keys.length;
    document.getElementById('emPrice').value   = total.toFixed(2);
}

document.getElementById('emForm').addEventListener('submit', function(e) {
    if (Object.keys(emChecked).length === 0) {
        e.preventDefault();
        alert('Please select at least one service or product.');
    }
});

/* ── Helpers ── */
function esc(s)  { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escA(s) { return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }

/* ── Init ── */
renderTiles();

</script>
</body>
</html>