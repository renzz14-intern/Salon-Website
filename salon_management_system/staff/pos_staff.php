<?php
/**
 * Point of Sales (POS) - Staff
 * Tile-based cart — services & products synced with database
 * Staff can complete sales; admin approval is NOT required (recorded as approved)
 */

require_once '../config/auth.php';
requireStaff(); // Ensure only staff can access this page

$pdo      = getPDOConnection();
$success  = '';
$error    = '';
$staff_id = getCurrentUserId(); // logged-in staff's user/account ID

// Resolve staff_id from user_accounts → staff table
$stmt = $pdo->prepare("SELECT s.staff_id FROM staff s INNER JOIN user_accounts ua ON ua.staff_id = s.staff_id WHERE ua.account_id = ?");
$stmt->execute([$staff_id]);
$staffRow       = $stmt->fetch();
$real_staff_id  = $staffRow ? $staffRow['staff_id'] : null;

// ── COMPLETE SALE — one combined row ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete_sale') {
    $sale_date      = sanitizeInput($_POST['sale_date']);
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

            // Generate receipt number
            $receipt_no = 'RCP-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));

            // Save product_id if the cart contains exactly one product item
            $single_product_id = null;
            $product_items = array_filter($cart_items, function($i){ return $i['type'] === 'product'; });
            if (count($cart_items) === 1 && count($product_items) === 1) {
                $only = array_values($product_items)[0];
                $single_product_id = intval($only['id']);
            }

            try {
                $pdo->prepare("
                    INSERT INTO sales (sale_date, staff_id, product_id, service_name, quantity, unit_price, total_amount, payment_method, status, approved_by, approved_at, customer_name, receipt_no)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), ?, ?)
                ")->execute([$sale_date, $real_staff_id, $single_product_id, $label, count($cart_items), $grand_total, $grand_total, $payment_method, $real_staff_id, $customer_name ?: null, $receipt_no]);
            } catch (Exception $colErr) {
                $pdo->prepare("
                    INSERT INTO sales (sale_date, staff_id, product_id, service_name, quantity, unit_price, total_amount, payment_method, status, approved_by, approved_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW())
                ")->execute([$sale_date, $real_staff_id, $single_product_id, $label, count($cart_items), $grand_total, $grand_total, $payment_method, $real_staff_id]);
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
            $success = 'Sale recorded! Receipt: <strong>' . htmlspecialchars($receipt_no) . '</strong> — Total: &#x20B1;' . number_format($grand_total, 2);
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// ── DATA FETCHES ───────────────────────────────────────────────────────────────
$products = $pdo->query("SELECT product_id, product_name, price, quantity FROM products WHERE status='available' ORDER BY product_name")->fetchAll();

try {
    $salon_services = $pdo->query("SELECT service_id, service_name, price, category FROM salon_services WHERE status='active' ORDER BY service_name")->fetchAll();
} catch (Exception $e) {
    $salon_services = [];
}

// Fetch this staff member's recent sales
try {
    $recent_sales = $pdo->prepare("
        SELECT s.sale_id, s.sale_date, s.approved_at, s.quantity, s.total_amount,
               s.payment_method, s.service_name, s.unit_price,
               s.customer_name, s.receipt_no
        FROM   sales s
        WHERE  s.status = 'approved'
          AND  s.staff_id = ?
        ORDER  BY s.approved_at DESC, s.sale_id DESC
        LIMIT  15
    ");
    $recent_sales->execute([$real_staff_id]);
    $recent_sales = $recent_sales->fetchAll();
} catch (Exception $e) {
    $recent_sales = [];
}

$products_json = json_encode($products,       JSON_UNESCAPED_UNICODE);
$services_json = json_encode($salon_services, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Point of Sales – Staff</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="icon" href="../assets/images/icon.png">
<style>
.pos-wrap { display:flex; height:calc(100vh - 65px); overflow:hidden; }

/* ── LEFT ── */
.pos-left { flex:1; display:flex; flex-direction:column; overflow:hidden; background:#f8f9fa; border-right:1px solid #dee2e6; }
.pos-tabs { display:flex; border-bottom:2px solid #dee2e6; background:#fff; padding:0 16px; }
.pos-tab  { padding:12px 18px; font-size:13px; font-weight:600; cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px; color:#666; }
.pos-tab.active { color:#333; border-bottom-color:#4a6cf7; }

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

.no-svc-note { background:#fff3cd; color:#856404; border:1px solid #ffc107; border-radius:6px; padding:10px 12px; font-size:12px; margin-bottom:12px; }

/* ── RIGHT ── */
.pos-right { width:320px; display:flex; flex-direction:column; background:#fff; }
.cart-header { padding:12px 14px; border-bottom:1px solid #dee2e6; background:#f8f9fa; }
.cart-header h3 { margin:0 0 8px; font-size:14px; font-weight:700; }
.cart-meta { display:flex; flex-direction:column; gap:6px; }
.cart-meta input[type=date] { width:100%; padding:6px 10px; border:1px solid #ced4da; border-radius:6px; font-size:12px; outline:none; }
.cart-body { flex:1; overflow-y:auto; padding:6px 12px; }
.cart-empty { text-align:center; padding:30px 10px; color:#bbb; font-size:13px; }
.cart-row { display:flex; align-items:flex-start; gap:8px; padding:8px 0; border-bottom:1px solid #f0f0f0; }
.cart-row-info { flex:1; }
.cart-row-name { font-size:12px; font-weight:600; color:#333; margin-bottom:2px; }
.badge { display:inline-block; font-size:10px; padding:1px 6px; border-radius:10px; font-weight:600; text-transform:uppercase; margin-bottom:4px; }
.badge-service { background:#dbeafe; color:#1d4ed8; }
.badge-product { background:#fef3c7; color:#92400e; }
.qty-ctrl { display:flex; align-items:center; gap:4px; }
.qty-btn { width:22px; height:22px; border:1px solid #ced4da; border-radius:4px; background:#f8f9fa; cursor:pointer; font-size:14px; font-weight:700; padding:0; line-height:1; display:flex; align-items:center; justify-content:center; }
.qty-btn:hover { background:#e2e6ea; }
.qty-val { font-size:13px; font-weight:700; min-width:20px; text-align:center; }
.cart-row-right { display:flex; flex-direction:column; align-items:flex-end; gap:4px; padding-top:2px; }
.cart-subtotal { font-size:12px; font-weight:700; }
.btn-rm { background:none; border:none; color:#ccc; cursor:pointer; font-size:15px; padding:0; line-height:1; }
.btn-rm:hover { color:#dc3545; }
.cart-footer { padding:10px 14px; border-top:1px solid #dee2e6; background:#f8f9fa; }
.t-row { display:flex; justify-content:space-between; font-size:12px; color:#666; margin-bottom:4px; }
.t-grand { display:flex; justify-content:space-between; font-size:18px; font-weight:700; color:#333; margin:6px 0 10px; padding-top:6px; border-top:1px solid #dee2e6; }
.cart-footer select { width:100%; padding:7px 10px; border:1px solid #ced4da; border-radius:6px; font-size:13px; margin-bottom:8px; outline:none; }
.btn-complete { width:100%; padding:10px; background:#28a745; border:none; border-radius:6px; color:#fff; font-size:14px; font-weight:700; cursor:pointer; }
.btn-complete:hover { background:#218838; }
.btn-complete:disabled { background:#adb5bd; cursor:not-allowed; }
.btn-clear { width:100%; padding:7px; background:#fff; border:1px solid #dc3545; border-radius:6px; color:#dc3545; font-size:12px; font-weight:600; cursor:pointer; margin-top:5px; }
.btn-clear:hover { background:#fff5f5; }

/* ── Modals ── */
.mo { display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:1000; align-items:center; justify-content:center; }
.mo.open { display:flex; }
.mb { background:#fff; border-radius:10px; padding:22px 24px; width:100%; max-width:420px; box-shadow:0 4px 20px rgba(0,0,0,.18); position:relative; }
.mb h4 { margin:0 0 16px; font-size:15px; color:#333; }
.mc { position:absolute; top:12px; right:14px; background:none; border:none; font-size:18px; cursor:pointer; color:#888; }
.mc:hover { color:#333; }
.mg { margin-bottom:12px; }
.mg label { display:block; font-size:12px; font-weight:600; color:#555; margin-bottom:4px; text-transform:uppercase; letter-spacing:.3px; }
.mg input { width:100%; padding:8px 10px; border:1px solid #ced4da; border-radius:6px; font-size:13px; outline:none; box-sizing:border-box; }
.mg input:focus { border-color:#4a6cf7; }
.ma { display:flex; gap:8px; margin-top:16px; }
.ma button { flex:1; padding:10px; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; border:none; }
.ba { background:#4a6cf7; color:#fff; }
.ba:hover { background:#3558e0; }
.bc { background:#e9ecef; color:#555; }
.bc:hover { background:#dee2e6; }

.items-col { font-size:11px; color:#555; max-width:280px; word-break:break-word; line-height:1.5; }
</style>
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Staff Panel</h2>
            <p><?php echo getCurrentUserName(); ?></p>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="pos_staff.php" class="active">&#x1F4B0; Point of Sales</a>
            <?php
            // Show optional links based on permissions (guard them in a try-catch in case the table differs)
            try {
                $permStmt = $pdo->prepare("SELECT up.* FROM user_permissions up INNER JOIN user_accounts ua ON ua.account_id = up.account_id WHERE ua.account_id = ?");
                $permStmt->execute([$staff_id]);
                $perms = $permStmt->fetch();
            } catch (Exception $e) { $perms = null; }
             if (!empty($perms['can_access_inventory'])): ?>
                <a href="inventory.php">&#x1F4E6; View Inventory</a>
            <?php endif;
            if (!empty($perms['can_view_my_reports'])): ?>
                <a href="my_report.php">&#x1F4C8; My Report</a>
            <?php endif; ?>
        </nav>
    </aside>

    <main class="main-content" style="padding:0;overflow:hidden;display:flex;flex-direction:column;">

        <div class="top-bar" style="padding:12px 20px;flex-shrink:0;">
            <h1>&#x1F4B0; Point of Sales (POS)</h1>
            <div class="user-info">
                <span style="margin-right:15px;">Welcome, <strong><?php echo getCurrentUserName(); ?></strong></span>
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

                <div class="pos-search">
                    <input type="text" id="searchBox" placeholder="Search..." oninput="renderTiles()">
                </div>
                <div class="tiles-scroll" id="tilesArea"></div>
            </div>

            <!-- RIGHT: cart -->
            <div class="pos-right">
                <div class="cart-header">
                    <h3>Current Sale</h3>
                    <div class="cart-meta">
                        <input type="date" id="cartDate" value="<?php echo date('Y-m-d'); ?>">
                        <input type="text" id="cartCustomer" placeholder="Customer Name (optional)" style="width:100%;padding:6px 10px;border:1px solid #ced4da;border-radius:6px;font-size:12px;outline:none;">
                    </div>
                </div>

                <div class="cart-body" id="cartBody"></div>

                <div class="cart-footer">
                    <div class="t-row"><span>Items</span><span id="cartCount">0</span></div>
                    <div class="t-grand"><span>TOTAL</span><span id="cartTotal">&#x20B1;0.00</span></div>
                    <select id="cartPayment">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="online">Online Payment</option>
                    </select>
                    <button class="btn-complete" id="btnComplete" onclick="completeSale()" disabled>Complete Sale</button>
                    <button class="btn-clear" onclick="clearCart()">Clear Cart</button>
                </div>
            </div>
        </div>

        <!-- hidden submit form -->
        <form id="saleForm" method="POST" action="" style="display:none;">
            <input type="hidden" name="action" value="complete_sale">
            <input type="hidden" name="sale_date" id="fDate">
            <input type="hidden" name="payment_method" id="fPayment">
            <input type="hidden" name="customer_name"  id="fCustomer">
            <input type="hidden" name="cart_data" id="fCart">
        </form>

        <!-- Recent Sales (this staff member only) -->
        <div class="content-box" style="margin:14px 16px;overflow-y:auto;max-height:320px;">
            <h2>My Recent Sales</h2>
            <?php if (empty($recent_sales)): ?>
                <div style="background:#d1ecf1;color:#0c5460;padding:12px;border-radius:6px;font-size:13px;">No sales recorded yet.</div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Receipt #</th><th>Date &amp; Time</th><th>Customer</th>
                                <th>Items Sold</th><th>Total</th><th>Payment</th>
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
                                <td><div class="items-col"><?php echo htmlspecialchars($sale['service_name'] ?? 'N/A'); ?></div></td>
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

<!-- ══ CUSTOM SERVICE (one-off, not saved to DB) ════════════════════════════ -->
<div class="mo" id="cmModal">
    <div class="mb">
        <button class="mc" onclick="closeCM()">&#x2715;</button>
        <h4>Add Custom Service (this session only)</h4>
        <div class="mg">
            <label>Service Name *</label>
            <input type="text" id="cmName" placeholder="e.g., Deep Conditioning">
        </div>
        <div class="mg">
            <label>Price (&#x20B1;) *</label>
            <input type="number" id="cmPrice" step="0.01" min="0" placeholder="0.00">
        </div>
        <div class="ma">
            <button class="ba" onclick="submitCM()">Add to Cart</button>
            <button class="bc" onclick="closeCM()">Cancel</button>
        </div>
    </div>
</div>

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

function cKey(type, id) { return type + '||' + String(id); }

/* ── Cart ops ── */
function addToCart(type, id, name, price) {
    var key   = cKey(type, id);
    var found = cart.find(function(c) { return c.key === key; });
    if (found) { found.qty++; }
    else { cart.push({ key: key, type: type, id: id, name: name, price: parseFloat(price), qty: 1 }); }
    renderCart();
    renderTiles();
}
function removeRow(key) {
    cart = cart.filter(function(c) { return c.key !== key; });
    renderCart(); renderTiles();
}
function changeQty(key, delta) {
    var item = cart.find(function(c) { return c.key === key; });
    if (item) { item.qty = Math.max(1, item.qty + delta); renderCart(); }
}
function clearCart() {
    cart = [];
    document.getElementById('cartCustomer').value = '';
    renderCart();
    renderTiles();
}

/* ── Render cart ── */
function renderCart() {
    var body  = document.getElementById('cartBody');
    var count = document.getElementById('cartCount');
    var total = document.getElementById('cartTotal');
    var btn   = document.getElementById('btnComplete');

    if (cart.length === 0) {
        body.innerHTML = '<div class="cart-empty">No items yet.<br>Tap a service or product to add.</div>';
        count.textContent = '0';
        total.textContent = '\u20B10.00';
        btn.disabled = true;
        return;
    }

    var html = '', grand = 0, totalQty = 0;
    cart.forEach(function(item) {
        var sub  = item.price * item.qty;
        grand   += sub; totalQty += item.qty;
        var sk   = item.key.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
        html += '<div class="cart-row">'
             +   '<div class="cart-row-info">'
             +     '<div class="cart-row-name">' + esc(item.name) + '</div>'
             +     '<span class="badge badge-' + item.type + '">' + item.type + '</span>'
             +     '<div class="qty-ctrl">'
             +       '<button class="qty-btn" onclick="changeQty(\'' + sk + '\',-1)">&#8722;</button>'
             +       '<span class="qty-val">' + item.qty + '</span>'
             +       '<button class="qty-btn" onclick="changeQty(\'' + sk + '\',1)">&#43;</button>'
             +       '<span style="font-size:11px;color:#aaa;margin-left:4px;">&times; &#x20B1;' + item.price.toFixed(2) + '</span>'
             +     '</div>'
             +   '</div>'
             +   '<div class="cart-row-right">'
             +     '<button class="btn-rm" onclick="removeRow(\'' + sk + '\')">&times;</button>'
             +     '<span class="cart-subtotal">&#x20B1;' + sub.toFixed(2) + '</span>'
             +   '</div>'
             + '</div>';
    });

    body.innerHTML    = html;
    count.textContent = totalQty;
    total.textContent = '\u20B1' + grand.toFixed(2);
    btn.disabled      = false;
}

/* ── Render tiles ── */
function renderTiles() {
    var area   = document.getElementById('tilesArea');
    var q      = document.getElementById('searchBox').value.toLowerCase().trim();
    var inCart = {};
    cart.forEach(function(c) { inCart[c.key] = true; });
    var html   = '';

    if (activeTab === 'services') {
        if (SERVICES.length === 0) {
            html += '<div class="no-svc-note">No services available. Please contact your admin.</div>';
        }
        var list = SERVICES.filter(function(s) { return !q || s.name.toLowerCase().indexOf(q) >= 0; });
        if (list.length > 0) {
            html += '<div class="tile-label">Salon Services</div><div class="tiles-grid">';
            list.forEach(function(s) {
                var key   = cKey('service', s.id);
                var ic    = inCart[key] ? ' in-cart' : '';
                var click = 'addToCart(\'service\',' + s.id + ',\'' + escA(s.name) + '\',' + s.price + ')';
                html += '<div class="tile svc-tile' + ic + '" onclick="' + click + '">'
                      + '<div class="tile-name">' + esc(s.name) + '</div>'
                      + '<div class="tile-price">&#x20B1;' + s.price.toFixed(2) + '</div>'
                      + '</div>';
            });
            html += '</div>';
        }
        if (!q) {
            html += '<div class="tiles-grid">'
                  + '<div class="tile custom-tile" onclick="openCM()">'
                  + '<div style="font-size:26px;margin-bottom:4px;">+</div>'
                  + '<div class="tile-name">Custom Service</div>'
                  + '<div class="tile-price">Session only</div>'
                  + '</div></div>';
        }
    } else {
        var plist = PRODUCTS.filter(function(p) { return !q || p.name.toLowerCase().indexOf(q) >= 0; });
        html += '<div class="tile-label">Products</div><div class="tiles-grid">';
        plist.forEach(function(p) {
            var key   = cKey('product', p.id);
            var ic    = inCart[key] ? ' in-cart' : '';
            var click = 'addToCart(\'product\',' + p.id + ',\'' + escA(p.name) + '\',' + p.price + ')';
            html += '<div class="tile' + ic + '" onclick="' + click + '">'
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

/* ── Custom service (session only) ── */
function openCM() {
    document.getElementById('cmName').value  = '';
    document.getElementById('cmPrice').value = '';
    document.getElementById('cmModal').classList.add('open');
    setTimeout(function() { document.getElementById('cmName').focus(); }, 80);
}
function closeCM() { document.getElementById('cmModal').classList.remove('open'); }
function submitCM() {
    var name  = document.getElementById('cmName').value.trim();
    var price = parseFloat(document.getElementById('cmPrice').value);
    if (!name)                     { alert('Please enter a service name.'); return; }
    if (isNaN(price) || price < 0) { alert('Please enter a valid price.');  return; }
    addToCart('service', 'custom_' + Date.now(), name, price);
    closeCM();
}

/* ── Complete sale ── */
function completeSale() {
    if (!cart.length) return;
    document.getElementById('fDate').value     = document.getElementById('cartDate').value;
    document.getElementById('fPayment').value  = document.getElementById('cartPayment').value;
    document.getElementById('fCustomer').value = document.getElementById('cartCustomer').value;
    document.getElementById('fCart').value     = JSON.stringify(cart.map(function(c) {
        return { type: c.type, id: c.id, name: c.name, price: c.price, qty: c.qty };
    }));
    document.getElementById('saleForm').submit();
}

/* ── Helpers ── */
function esc(s)  { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escA(s) { return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }

/* ── Init ── */
renderTiles();
renderCart();
</script>
</body>
</html>