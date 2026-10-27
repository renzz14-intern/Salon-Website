<?php
/**
 * Staff Management
 * Add, edit, and manage staff accounts with permissions
 */

require_once '../config/auth.php';
requireAdmin();

$pdo     = getPDOConnection();
$success = '';
$error   = '';

// Pending count for sidebar badge
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sales WHERE status = 'pending'");
$stmt->execute();
$pending_count = $stmt->fetch()['total'];

// ── DEACTIVATE ─────────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $staff_id = intval($_GET['delete']);
    try {
        $pdo->prepare("UPDATE staff SET status = 'inactive' WHERE staff_id = ?")->execute([$staff_id]);
        $pdo->prepare("UPDATE user_accounts SET is_active = 0 WHERE staff_id = ?")->execute([$staff_id]);
        $success = 'Staff member deactivated successfully!';
    } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
}

// ── ACTIVATE ───────────────────────────────────────────────────────────────────
if (isset($_GET['activate'])) {
    $staff_id = intval($_GET['activate']);
    try {
        $pdo->prepare("UPDATE staff SET status = 'active' WHERE staff_id = ?")->execute([$staff_id]);
        $pdo->prepare("UPDATE user_accounts SET is_active = 1 WHERE staff_id = ?")->execute([$staff_id]);
        $success = 'Staff member activated successfully!';
    } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
}

// ── PERMANENT REMOVE ───────────────────────────────────────────────────────────
if (isset($_GET['remove'])) {
    $staff_id = intval($_GET['remove']);
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT account_id FROM user_accounts WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        $account = $stmt->fetch();
        if ($account) {
            $pdo->prepare("DELETE FROM user_permissions WHERE account_id = ?")->execute([$account['account_id']]);
            $pdo->prepare("DELETE FROM user_accounts WHERE account_id = ?")->execute([$account['account_id']]);
        }
        $pdo->prepare("DELETE FROM staff WHERE staff_id = ?")->execute([$staff_id]);
        $pdo->commit();
        $success = 'Staff member permanently removed from system!';
    } catch (Exception $e) { $pdo->rollBack(); $error = 'Error: ' . $e->getMessage(); }
}

// ── ADD / EDIT STAFF INFO ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['create_account'])) {
    $staff_id      = $_POST['staff_id'] ?? null;
    $first_name    = sanitizeInput($_POST['first_name']);
    $last_name     = sanitizeInput($_POST['last_name']);
    $full_name     = $first_name . ' ' . $last_name;
    $contact       = sanitizeInput($_POST['contact_number']);
    $address       = sanitizeInput($_POST['address']);
    $job_title     = sanitizeInput($_POST['job_title']);
    $rate          = floatval($_POST['rate']);

    try {
        if ($staff_id) {
            $pdo->prepare("UPDATE staff SET full_name=?, contact_number=?, address=?, job_title=?, rate=? WHERE staff_id=?")
                ->execute([$full_name, $contact, $address, $job_title, $rate, $staff_id]);
            $success = 'Staff information updated successfully!';
        } else {
            $pdo->prepare("INSERT INTO staff (full_name, contact_number, address, job_title, rate, status) VALUES (?,?,?,?,?,'active')")
                ->execute([$full_name, $contact, $address, $job_title, $rate]);
            $success = 'Staff added successfully! You can create an account for them if needed.';
        }
    } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
}

// ── CREATE / UPDATE ACCOUNT & PERMISSIONS ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_account'])) {
    $staff_id      = intval($_POST['staff_id']);
    $username      = sanitizeInput($_POST['username']);
    $password      = $_POST['password'];
    $is_new        = isset($_POST['is_new_account']) && $_POST['is_new_account'] === '1';

    $can_inventory  = isset($_POST['can_access_inventory']) ? 1 : 0;
    $can_pos        = isset($_POST['can_access_pos'])       ? 1 : 0;  // Staff POS permission
    $can_my_reports = isset($_POST['can_view_my_reports'])  ? 1 : 0;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT staff_id FROM staff WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        if (!$stmt->fetch()) throw new Exception('Staff member not found.');

        $stmt = $pdo->prepare("SELECT account_id FROM user_accounts WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        $existing = $stmt->fetch();

        if ($is_new) {
            if ($existing) throw new Exception('Account already exists for this staff member.');

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_accounts WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) throw new Exception('Username already taken.');

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO user_accounts (staff_id, username, password, role, is_active) VALUES (?,?,?,'staff',1)")
                ->execute([$staff_id, $username, $hash]);
            $account_id = $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO user_permissions (account_id, can_access_inventory, can_access_pos, can_view_my_reports) VALUES (?,?,?,?)")
                ->execute([$account_id, $can_inventory, $can_pos, $can_my_reports]);

            $success = 'Account created successfully!';
        } else {
            if (!$existing) throw new Exception('No account found for this staff member.');
            $account_id = $existing['account_id'];

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_accounts WHERE username = ? AND account_id != ?");
            $stmt->execute([$username, $account_id]);
            if ($stmt->fetchColumn() > 0) throw new Exception('Username already taken.');

            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE user_accounts SET username=?, password=? WHERE account_id=?")
                    ->execute([$username, $hash, $account_id]);
            } else {
                $pdo->prepare("UPDATE user_accounts SET username=? WHERE account_id=?")
                    ->execute([$username, $account_id]);
            }

            // Upsert permissions
            $stmt = $pdo->prepare("SELECT permission_id FROM user_permissions WHERE account_id = ?");
            $stmt->execute([$account_id]);
            if ($stmt->fetch()) {
                $pdo->prepare("UPDATE user_permissions SET can_access_inventory=?, can_access_pos=?, can_view_my_reports=? WHERE account_id=?")
                    ->execute([$can_inventory, $can_pos, $can_my_reports, $account_id]);
            } else {
                $pdo->prepare("INSERT INTO user_permissions (account_id, can_access_inventory, can_access_pos, can_view_my_reports) VALUES (?,?,?,?)")
                    ->execute([$account_id, $can_inventory, $can_pos, $can_my_reports]);
            }

            $success = 'Account updated successfully!';
        }

        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); $error = 'Error: ' . $e->getMessage(); }
}

// ── FETCH FOR EDIT / ACCOUNT MANAGEMENT ───────────────────────────────────────
$edit_staff          = null;
$account_staff       = null;
$account_permissions = null;
$is_new_account      = false;

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE staff_id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit_staff = $stmt->fetch();
}

if (isset($_GET['manage_account']) || isset($_GET['create_account'])) {
    $sid = intval($_GET['manage_account'] ?? $_GET['create_account']);
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE staff_id = ?");
    $stmt->execute([$sid]);
    $account_staff = $stmt->fetch();

    if ($account_staff) {
        $stmt = $pdo->prepare("SELECT ua.*, up.* FROM user_accounts ua LEFT JOIN user_permissions up ON ua.account_id = up.account_id WHERE ua.staff_id = ?");
        $stmt->execute([$sid]);
        $existing = $stmt->fetch();
        if ($existing) { $account_permissions = $existing; $is_new_account = false; }
        else            { $is_new_account = true; }
    }
}

// ── ALL STAFF ──────────────────────────────────────────────────────────────────
$stmt = $pdo->query("
    SELECT s.*,
           ua.account_id, ua.username, ua.role, ua.is_active as account_active,
           CASE WHEN ua.account_id IS NOT NULL THEN 'Yes' ELSE 'No' END as has_account
    FROM staff s
    LEFT JOIN user_accounts ua ON s.staff_id = ua.staff_id
    ORDER BY s.full_name
");
$staff_members = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Salon Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../assets/images/icon.png">
    <style>
        /* Permission checkboxes */
        .perm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
            margin-bottom: 22px;
        }
        .perm-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 14px 16px;
            cursor: pointer;
            transition: border-color .15s, background .15s;
        }
        .perm-item:hover { background: #e9f0ff; border-color: #4a90e2; }
        .perm-item input[type="checkbox"] {
            width: 18px; height: 18px;
            margin-top: 2px; flex-shrink: 0;
            accent-color: #4a90e2; cursor: pointer;
        }
        .perm-item input[type="checkbox"]:checked + .perm-text { color: #1a56db; }
        .perm-item.checked { background: #e9f0ff; border-color: #4a90e2; }
        .perm-text strong { display: block; font-size: .95rem; margin-bottom: 2px; }
        .perm-text small  { color: #666; font-size: .82rem; }

        /* No-permission warning */
        .perm-warning {
            display: none;
            background: #fff3cd; color: #856404;
            border: 1px solid #ffc107; border-radius: 8px;
            padding: 12px 16px; margin-bottom: 16px; font-size: .9rem;
        }
        .perm-warning.visible { display: block; }
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
            <a href="dashboard.php">📊 Dashboard</a>
                <a href="appointments_admin.php">&#x1F4C5; Appointments</a>
            <a href="pos.php">💰 Point of Sales</a>
            <a href="sales_reports.php">📈 Sales Reports</a>
            <a href="staff_reports_admin.php">&#x1F4CB; Staff Reports</a>
            <a href="inventory.php">📦 Inventory</a>
            <a href="staff_duties.php">📋 Staff Duties</a>
            <a href="staff_management.php" class="active">👥 Staff Management</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <h1>Staff Management</h1>
            <div class="user-info">
                <span style="margin-right:15px;">Welcome, <strong><?php echo getCurrentUserName(); ?></strong></span>
                <a href="settings.php" class="btn btn-primary">⚙️ Settings</a>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </div>

        <?php if ($success): ?><div class="success-message"><?php echo $success; ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="error-message"><?php echo $error; ?></div><?php endif; ?>

        <!-- ── ACCOUNT MANAGEMENT FORM ─────────────────────────────────── -->
        <?php if ($account_staff): ?>
        <div class="content-box">
            <h2>
                <?php echo $is_new_account ? 'Create Account' : 'Manage Account'; ?>
                for <?php echo htmlspecialchars($account_staff['full_name']); ?>
            </h2>

            <?php if (!$is_new_account): ?>
                <div style="background:#e7f3ff;padding:15px;border-radius:8px;margin-bottom:20px;border-left:4px solid #2196F3;">
                    <p style="margin:0;"><strong>Current Username:</strong> <?php echo htmlspecialchars($account_permissions['username']); ?></p>
                    <p style="margin:5px 0 0;"><strong>Account Status:</strong>
                        <?php if ($account_permissions['is_active']): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Inactive</span>
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div style="background:#fff3cd;padding:15px;border-radius:8px;margin-bottom:20px;border-left:4px solid #ffc107;">
                    <p style="margin:0;">ℹ<strong>Note:</strong> This staff member does not have a login account yet. Create one below to give them system access.</p>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="accountForm">
                <input type="hidden" name="create_account" value="1">
                <input type="hidden" name="staff_id" value="<?php echo $account_staff['staff_id']; ?>">
                <input type="hidden" name="is_new_account" value="<?php echo $is_new_account ? '1' : '0'; ?>">

                <h3>Login Credentials</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" class="form-control"
                               value="<?php echo htmlspecialchars($account_permissions['username'] ?? ''); ?>"
                               placeholder="Enter username" required>
                    </div>
                    <div class="form-group">
                        <label>Password <?php echo $is_new_account ? '*' : '(leave blank to keep current)'; ?></label>
                        <input type="password" name="password" class="form-control"
                               placeholder="<?php echo $is_new_account ? 'Enter password' : 'Enter new password (or leave blank)'; ?>"
                               <?php echo $is_new_account ? 'required' : ''; ?>>
                        <?php if (!$is_new_account): ?>
                            <small style="color:#666;">Leave blank to keep the current password</small>
                        <?php endif; ?>
                    </div>
                </div>

                <h3>System Permissions</h3>
                <p style="color:#666;margin-bottom:14px;">
                    Check the sections this staff member is allowed to access.
                    <strong>If nothing is checked, the staff will see a "contact admin" notice after logging in.</strong>
                </p>

                <!-- No-permission warning shown in real time -->
                <div class="perm-warning" id="permWarning">
                    No permissions selected — this staff member will not be able to access any part of the system until you grant at least one permission.
                </div>

                <div class="perm-grid" id="permGrid">
                    <?php
                    $perms_def = [
                        ['name' => 'can_access_inventory', 'key' => 'can_access_inventory', 'label' => 'View Inventory',  'desc' => 'Browse product stock levels'],
                        ['name' => 'can_access_pos',       'key' => 'can_access_pos',       'label' => 'Point of Sales',  'desc' => 'Process sales transactions via the POS system'],
                        ['name' => 'can_view_my_reports',  'key' => 'can_view_my_reports',  'label' => 'My Report',       'desc' => 'View personal performance and sales reports'],
                    ];
                    foreach ($perms_def as $p):
                        $checked = $account_permissions && !empty($account_permissions[$p['key']]);
                    ?>
                    <label class="perm-item <?php echo $checked ? 'checked' : ''; ?>">
                        <input type="checkbox" name="<?php echo $p['name']; ?>" value="1"
                               <?php echo $checked ? 'checked' : ''; ?>
                               onchange="updatePermState()">
                        <span class="perm-text">
                            <strong><?php echo $p['label']; ?></strong>
                            <small><?php echo $p['desc']; ?></small>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top:8px;">
                    <button type="submit" class="btn btn-success">
                        <?php echo $is_new_account ? 'Create Account' : 'Update Account'; ?>
                    </button>
                    <a href="staff_management.php" class="btn btn-warning">Cancel</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- ── ADD / EDIT STAFF FORM ───────────────────────────────────── -->
        <div class="content-box">
            <h2><?php echo $edit_staff ? 'Edit Staff Information' : 'Add New Staff Member'; ?></h2>
            <form method="POST" action="">
                <?php if ($edit_staff): ?>
                    <input type="hidden" name="staff_id" value="<?php echo $edit_staff['staff_id']; ?>">
                <?php endif; ?>

                <h3>Personal Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" class="form-control"
                               value="<?php echo $edit_staff ? explode(' ', $edit_staff['full_name'])[0] : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" class="form-control"
                               value="<?php echo $edit_staff ? implode(' ', array_slice(explode(' ', $edit_staff['full_name']), 1)) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Number *</label>
                        <input type="text" name="contact_number" class="form-control"
                               value="<?php echo $edit_staff['contact_number'] ?? ''; ?>"
                               placeholder="e.g., 09123456789" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex:1 1 100%;">
                        <label>Address *</label>
                        <input type="text" name="address" class="form-control"
                               value="<?php echo $edit_staff['address'] ?? ''; ?>"
                               placeholder="Complete Address" required>
                    </div>
                </div>

                <h3>Job Details</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Job Title *</label>
                        <input type="text" name="job_title" class="form-control"
                               value="<?php echo $edit_staff['job_title'] ?? ''; ?>"
                               placeholder="e.g., Hair Stylist, Nail Technician" required>
                    </div>
                    <div class="form-group">
                        <label>Rate (₱/hour) *</label>
                        <input type="number" name="rate" class="form-control" step="0.01" min="0"
                               value="<?php echo $edit_staff['rate'] ?? 0; ?>" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-success">
                    <?php echo $edit_staff ? 'Update Staff Information' : 'Add Staff'; ?>
                </button>
                <?php if ($edit_staff): ?>
                    <a href="staff_management.php" class="btn btn-warning">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- ── STAFF LIST ──────────────────────────────────────────────── -->
        <div class="content-box">
            <h2>All Staff Members</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Job Title</th>
                            <th>Rate/Hour</th>
                            <th>Account Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staff_members)): ?>
                            <tr><td colspan="6" style="text-align:center;">No staff members found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($staff_members as $staff): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($staff['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($staff['contact_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($staff['job_title']); ?></td>
                                <td>₱<?php echo number_format($staff['rate'], 2); ?></td>
                                <td>
                                    <?php if ($staff['has_account'] === 'Yes'): ?>
                                        <?php if ($staff['status'] === 'active' && $staff['account_active']): ?>
                                            <span class="badge badge-success">Active Account</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive Account</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-warning">No Account</span>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space:nowrap;">
                                    <a href="?edit=<?php echo $staff['staff_id']; ?>" class="btn btn-primary btn-sm">Edit Info</a>

                                    <?php if ($staff['has_account'] === 'No'): ?>
                                        <a href="?create_account=<?php echo $staff['staff_id']; ?>" class="btn btn-success btn-sm">Create Account</a>
                                        <a href="?remove=<?php echo $staff['staff_id']; ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Permanently delete this staff member and all their records?')">Remove</a>
                                    <?php else: ?>
                                        <a href="?manage_account=<?php echo $staff['staff_id']; ?>" class="btn btn-warning btn-sm">Manage Account</a>

                                        <?php if ($staff['status'] === 'active'): ?>
                                            <a href="?delete=<?php echo $staff['staff_id']; ?>"
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Deactivate this staff member and their account?')">Deactivate</a>
                                        <?php else: ?>
                                            <a href="?activate=<?php echo $staff['staff_id']; ?>" class="btn btn-success btn-sm">Activate</a>
                                        <?php endif; ?>

                                        <a href="?remove=<?php echo $staff['staff_id']; ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Permanently delete this staff member, their account, and all records?')">Remove</a>
                                    <?php endif; ?>
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

<script>
    // Highlight checked perm cards and show/hide warning
    function updatePermState() {
        const checkboxes = document.querySelectorAll('#permGrid input[type="checkbox"]');
        let anyChecked = false;
        checkboxes.forEach(cb => {
            const item = cb.closest('.perm-item');
            if (cb.checked) { item.classList.add('checked'); anyChecked = true; }
            else              item.classList.remove('checked');
        });
        const warning = document.getElementById('permWarning');
        if (warning) warning.classList.toggle('visible', !anyChecked);
    }

    // Run on page load to set initial state
    updatePermState();
</script>
</body>
</html>