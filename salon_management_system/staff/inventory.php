<?php
/**
 * Staff Inventory View
 * Read-only access to inventory for staff with permission
 */

require_once '../config/auth.php';
requireLogin();

// Check permission
if (!hasPermission(getCurrentUserId(), 'inventory')) {
    header("Location: dashboard.php");
    exit();
}

$pdo = getPDOConnection();

// Get all products
$stmt = $pdo->query("
    SELECT p.*, c.category_name 
    FROM products p
    LEFT JOIN product_categories c ON p.category_id = c.category_id
    ORDER BY p.product_name
");
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Inventory - Salon Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../assets/images/icon.png">
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
                <?php if (hasPermission(getCurrentUserId(), 'pos')): ?>
                    <a href="dashboard.php"> 🏠 Dashboard </a>
                <?php endif; ?>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <h1>Product Inventory (View Only)</h1>
                <div class="user-info">
                    <a href="../logout.php" class="btn-logout">Logout</a>
                </div>
            </div>

            <div class="content-box">
                <div class="alert alert-info">
                    ℹ️ You have read-only access to inventory. Contact admin to update products.
                </div>
                
                <h2>Available Products</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Available Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                <td>
                                    <?php if ($product['quantity'] <= $product['low_stock_threshold']): ?>
                                        <span class="badge badge-warning"><?php echo $product['quantity']; ?> (Low Stock)</span>
                                    <?php else: ?>
                                        <?php echo $product['quantity']; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($product['status'] === 'available'): ?>
                                        <span class="badge badge-success">Available</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Out of Stock</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>