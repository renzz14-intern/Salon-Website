<?php
/**
 * Authentication & Session Management
 * UPDATED: Handles both old (users) and new (staff + user_accounts) database structures
 *          + requireStaff(), getStaffPermissions(), getStaffNavLinks()
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

function authenticateUser($username, $password) {
    try {
        $pdo = getPDOConnection();

        // Try new system first — user_accounts (staff)
        $stmt = $pdo->prepare("
            SELECT ua.account_id, ua.staff_id, ua.username, ua.password, ua.role, ua.is_active,
                   s.full_name
            FROM user_accounts ua
            LEFT JOIN staff s ON ua.staff_id = s.staff_id
            WHERE ua.username = ?
            LIMIT 1
        ");
        $stmt->execute([$username]);
        $account = $stmt->fetch();

        if ($account && password_verify($password, $account['password'])) {
            if ($account['is_active'] != 1) return false;
            $account['auth_system'] = 'new';
            $account['user_id']     = $account['account_id'];
            $pdo->prepare("UPDATE user_accounts SET last_login = NOW() WHERE account_id = ?")->execute([$account['account_id']]);
            return $account;
        }

        // Fall back to old system — users table (admin)
        $stmt = $pdo->prepare("SELECT user_id, username, password, full_name, role, status FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active') return false;
        if (password_verify($password, $user['password'])) {
            $user['auth_system'] = 'old';
            return $user;
        }

        return false;
    } catch (PDOException $e) {
        error_log("Authentication error: " . $e->getMessage());
        return false;
    }
}

function createUserSession($user) {
    session_regenerate_id(true);
    $_SESSION['user_id']     = $user['user_id'];
    $_SESSION['username']    = $user['username'];
    $_SESSION['full_name']   = $user['full_name'];
    $_SESSION['role']        = $user['role'];
    $_SESSION['logged_in']   = true;
    $_SESSION['login_time']  = time();
    $_SESSION['auth_system'] = $user['auth_system'] ?? 'old';

    if ($user['auth_system'] === 'new') {
        $_SESSION['account_id'] = $user['account_id'];
        $_SESSION['staff_id']   = $user['staff_id'];
    }
}

function isLoggedIn()  { return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true; }
function isAdmin()     { return isLoggedIn() && $_SESSION['role'] === 'admin'; }
function isStaff()     { return isLoggedIn() && $_SESSION['role'] === 'staff'; }

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /login_register/salon_management_system/index.php");
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: /login_register/salon_management_system/staff/dashboard.php");
        exit();
    }
}

/**
 * Require staff login.
 * Optionally enforce a specific permission ('inventory', 'pos', 'my_reports').
 * If $permission is provided and the staff lacks it, they are sent to the staff dashboard.
 */
function requireStaff($permission = null) {
    requireLogin();
    if (!isStaff()) {
        header("Location: /salon_management_system/admin/dashboard.php");
        exit();
    }
    if ($permission !== null && !hasPermission(getCurrentAccountId(), $permission)) {
        header("Location: /salon_management_system/staff/dashboard.php");
        exit();
    }
}

/**
 * Return all permission flags for the current staff account.
 * Keys: inventory, pos, can_view_my_reports — each value is true/false.
 */
function getStaffPermissions() {
    if (isAdmin()) {
        return ['inventory' => true, 'pos' => true, 'can_view_my_reports' => true];
    }

    try {
        $pdo        = getPDOConnection();
        $account_id = getCurrentAccountId();

        $stmt = $pdo->prepare("
            SELECT can_access_inventory, can_access_pos, can_view_my_reports
            FROM user_permissions
            WHERE account_id = ?
            LIMIT 1
        ");
        $stmt->execute([$account_id]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['inventory' => false, 'pos' => false, 'can_view_my_reports' => false];
        }

        return [
            'inventory'           => (bool) $row['can_access_inventory'],
            'pos'                 => (bool) $row['can_access_pos'],
            'can_view_my_reports' => (bool) $row['can_view_my_reports'],
        ];
    } catch (PDOException $e) {
        error_log("getStaffPermissions error: " . $e->getMessage());
        return ['inventory' => false, 'pos' => false, 'can_view_my_reports' => false];
    }
}

/**
 * Build the sidebar nav links array for staff based on their permissions.
 * Returns array of ['label' => '...', 'href' => '...', 'icon' => '...']
 */
function getStaffNavLinks() {
    $perms = getStaffPermissions();
    $links = [];

    if ($perms['pos'])                 $links[] = ['icon' => '💰', 'label' => 'Point of Sales', 'href' => 'pos_staff.php'];
    if ($perms['inventory'])           $links[] = ['icon' => '📦', 'label' => 'View Inventory',  'href' => 'inventory.php'];
    if ($perms['can_view_my_reports']) $links[] = ['icon' => '📊', 'label' => 'My Report',        'href' => 'my_report.php'];

    return $links;
}

function hasPermission($user_id, $permission) {
    if (isAdmin()) return true;

    try {
        $pdo = getPDOConnection();

        $column_map = [
            'inventory'  => 'can_access_inventory',
            'pos'        => 'can_access_pos',
            'my_reports' => 'can_view_my_reports',
        ];

        if (!isset($column_map[$permission])) return false;

        $column      = $column_map[$permission];
        $auth_system = $_SESSION['auth_system'] ?? 'old';
        $id_column   = $auth_system === 'new' ? 'account_id' : 'user_id';

        $stmt = $pdo->prepare("SELECT $column FROM user_permissions WHERE $id_column = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();

        return $result && $result[$column] == 1;
    } catch (PDOException $e) {
        error_log("Permission check error: " . $e->getMessage());
        return false;
    }
}

function logout() {
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) setcookie(session_name(), '', time() - 3600, '/');
    session_destroy();
    header("Location: /login_register/salon_management_system/index.php");
    exit();
}

function getCurrentUserId()    { return $_SESSION['user_id']    ?? null; }
function getCurrentAccountId() { return $_SESSION['account_id'] ?? $_SESSION['user_id'] ?? null; }
function getCurrentStaffId()   { return $_SESSION['staff_id']   ?? null; }
function getCurrentUserName()  { return $_SESSION['full_name']  ?? 'User'; }
function getCurrentUserRole()  { return $_SESSION['role']       ?? null; }
function getUserRole()         { return $_SESSION['role']       ?? 'guest'; }

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}
?>