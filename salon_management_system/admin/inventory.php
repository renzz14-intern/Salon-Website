<?php
/**
 * Inventory Management
 * CRUD operations for products
 */

require_once '../config/auth.php';
requireAdmin();

$pdo = getPDOConnection();
$success = '';
$error = '';

// Handle delete
if (isset($_GET['delete'])) {
    $product_id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $success = 'Product deleted successfully!';
    } catch (Exception $e) {
        $error = 'Error deleting product: ' . $e->getMessage();
    }
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? null;
    $product_name = sanitizeInput($_POST['product_name']);
    $category_id = intval($_POST['category_id']);
    $price = floatval($_POST['price']);
    $quantity = intval($_POST['quantity']);
    $low_stock_threshold = intval($_POST['low_stock_threshold']);
    $status = $quantity > 0 ? 'available' : 'out_of_stock';
    
    try {
        if ($product_id) {
            // Update existing product
            $stmt = $pdo->prepare("
                UPDATE products 
                SET product_name = ?, category_id = ?, price = ?, quantity = ?, 
                    low_stock_threshold = ?, status = ?
                WHERE product_id = ?
            ");
            $stmt->execute([$product_name, $category_id, $price, $quantity, $low_stock_threshold, $status, $product_id]);
            $success = 'Product updated successfully!';
        } else {
            // Add new product
            $stmt = $pdo->prepare("
                INSERT INTO products (product_name, category_id, price, quantity, low_stock_threshold, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$product_name, $category_id, $price, $quantity, $low_stock_threshold, $status]);
            $success = 'Product added successfully!';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get product for editing
$edit_product = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit_product = $stmt->fetch();
}

// Get all products
$stmt = $pdo->query("
    SELECT p.*, c.category_name 
    FROM products p
    LEFT JOIN product_categories c ON p.category_id = c.category_id
    ORDER BY p.product_name
");
$products = $stmt->fetchAll();

// Get categories
$stmt = $pdo->query("SELECT * FROM product_categories ORDER BY category_name");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Salon Management</title>
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
                <a href="dashboard.php" class="active">&#x1F4CA; Dashboard</a>
                <a href="appointments_admin.php">&#x1F4C5; Appointments</a>
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
                <h1>Inventory Management</h1>
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

            <!-- Add/Edit Product Form -->
            <div class="content-box">
                <h2><?php echo $edit_product ? 'Edit Product' : 'Add New Product'; ?></h2>
                <form method="POST" action="">
                    <?php if ($edit_product): ?>
                        <input type="hidden" name="product_id" value="<?php echo $edit_product['product_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Product Name *</label>
                            <input type="text" name="product_name" class="form-control" 
                                   value="<?php echo $edit_product['product_name'] ?? ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>"
                                            <?php echo ($edit_product && $edit_product['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Price (₱) *</label>
                            <input type="number" name="price" class="form-control" step="0.01" min="0"
                                   value="<?php echo $edit_product['price'] ?? ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Quantity *</label>
                            <input type="number" name="quantity" class="form-control" min="0"
                                   value="<?php echo $edit_product['quantity'] ?? ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Low Stock Alert Threshold *</label>
                            <input type="number" name="low_stock_threshold" class="form-control" min="1"
                                   value="<?php echo $edit_product['low_stock_threshold'] ?? 10; ?>" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <?php echo $edit_product ? 'Update Product' : 'Add Product'; ?>
                    </button>
                    <?php if ($edit_product): ?>
                        <a href="inventory.php" class="btn btn-warning">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Products List -->
            <div class="content-box">
                <h2>All Products</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo $product['product_id']; ?></td>
                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                <td>
                                    <?php if ($product['quantity'] <= $product['low_stock_threshold']): ?>
                                        <span class="badge badge-danger"><?php echo $product['quantity']; ?></span>
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
                                <td>
                                    <a href="?edit=<?php echo $product['product_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                    <a href="?delete=<?php echo $product['product_id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Are you sure you want to delete this product?')">Delete</a>
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