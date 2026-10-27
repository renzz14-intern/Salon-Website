<?php
/**
 * Appointments Admin — Levy Branzuela Salon
 * Manage customer bookings from the website.
 * Integrated into the salon management system layout.
 */

require_once '../config/auth.php';
requireAdmin();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// Adjust path to vendor relative to this file's location in admin/
$vendorPath = dirname(__DIR__) . '/../../vendor/autoload.php';
if (file_exists($vendorPath)) require_once $vendorPath;

$pdo     = getPDOConnection();
$success = '';
$error   = '';

// ── Pending bookings count (for badge) ───────────────────────────────────────
$pending_bookings = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM bookings WHERE status='pending'");
    $pending_bookings = $stmt->fetch()['total'];
} catch (Exception $e) {}

// ── Assign stylist ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_stylist') {
    $bid = intval($_POST['booking_id']);
    $sid = trim($_POST['staff_id']);
    try {
        $stmt = $pdo->prepare("UPDATE bookings SET therapist_id = ? WHERE id = ?");
        $stmt->execute([$sid, $bid]);
        $success = 'Stylist assigned successfully.';
    } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
}

// ── Edit services ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_services') {
    $bid = intval($_POST['booking_id']);
    $newServicesJson = trim($_POST['services_json'] ?? '');

    try {
        $decoded = json_decode($newServicesJson, true);
        if (!is_array($decoded) || empty($decoded)) {
            throw new Exception('No valid services provided.');
        }

        $totalPrice    = 0;
        $totalDuration = 0;
        $serviceNames  = [];
        foreach ($decoded as &$svc) {
            $svc['price']    = floatval($svc['price']    ?? 0);
            $svc['duration'] = intval($svc['duration']   ?? 0);
            $totalPrice    += $svc['price'];
            $totalDuration += $svc['duration'];
            $serviceNames[] = $svc['name'];
        }
        unset($svc);

        $primaryService = $serviceNames[0] ?? 'Salon Service';

        $pdo->prepare("
            UPDATE bookings
            SET services_json    = ?,
                service_name     = ?,
                total_price      = ?,
                total_duration   = ?,
                updated_at       = NOW()
            WHERE id = ?
        ")->execute([$newServicesJson, $primaryService, $totalPrice, $totalDuration, $bid]);

        $success = 'Services updated successfully.';
    } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
}

// ── Remove booking ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_booking') {
    $bid = intval($_POST['booking_id']);
    try {
        $pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$bid]);
        $success = 'Appointment #' . $bid . ' has been removed from the system.';
    } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
}

// ── Status update ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $bid       = intval($_POST['booking_id']);
    $newStatus = $_POST['status'];
    $allowed   = ['pending','confirmed','cancelled','completed'];

    if (!in_array($newStatus, $allowed)) {
        $error = 'Invalid status.';
    } else {
        try {
            // Fetch booking for email
            $stmt = $pdo->prepare("SELECT b.*, s.full_name AS stylist_name FROM bookings b LEFT JOIN staff s ON b.therapist_id = CAST(s.staff_id AS CHAR) WHERE b.id = ?");
            $stmt->execute([$bid]);
            $booking = $stmt->fetch();

            if (!$booking) throw new Exception('Booking not found.');

            $pdo->prepare("UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $bid]);
            $success = 'Booking status updated to ' . ucfirst($newStatus) . '.';

            // ── AUTO-RECORD SALE when booking is completed ────────────────────
            if ($newStatus === 'completed') {
                try {
                    // Build a readable label of the services rendered
                    $svcsData = !empty($booking['services_json']) ? json_decode($booking['services_json'], true) : null;
                    if ($svcsData && is_array($svcsData)) {
                        $svcNames     = array_map(fn($s) => $s['name'], $svcsData);
                        $serviceLabel = implode(', ', $svcNames);
                    } else {
                        $serviceLabel = $booking['service_name'] ?? 'Salon Service';
                    }

                    // Use booking reference as unique receipt number to prevent duplicates
                    $receiptNo = 'BK-' . $booking['booking_reference'];
                    $saleDate  = $booking['booking_date'];

                    // Validate therapist_id actually exists in staff table to avoid FK violation
                    $staffId = null;
                    if (!empty($booking['therapist_id'])) {
                        $chkStaff = $pdo->prepare("SELECT staff_id FROM staff WHERE staff_id = ? LIMIT 1");
                        $chkStaff->execute([intval($booking['therapist_id'])]);
                        if ($chkStaff->fetch()) {
                            $staffId = intval($booking['therapist_id']);
                        }
                    }
                    $totalAmount  = floatval($booking['total_price']);
                    $customerName = $booking['full_name'];
                    $adminId      = function_exists('getCurrentUserId') ? getCurrentUserId() : null;

                    // Prevent duplicate sales entry for the same booking
                    $existCheck = $pdo->prepare("SELECT sale_id FROM sales WHERE receipt_no = ? LIMIT 1");
                    $existCheck->execute([$receiptNo]);

                    if (!$existCheck->fetch()) {
                        try {
                            // Insert with customer_name and receipt_no columns
                            $pdo->prepare("
                                INSERT INTO sales
                                    (sale_date, staff_id, service_name, quantity, unit_price,
                                     total_amount, payment_method, status, approved_by,
                                     approved_at, customer_name, receipt_no)
                                VALUES (?, ?, ?, 1, ?, ?, 'cash', 'approved', ?, NOW(), ?, ?)
                            ")->execute([
                                $saleDate, $staffId, $serviceLabel,
                                $totalAmount, $totalAmount, $adminId,
                                $customerName, $receiptNo
                            ]);
                        } catch (Exception $colErr) {
                            // Fallback: columns customer_name / receipt_no may not exist yet
                            $pdo->prepare("
                                INSERT INTO sales
                                    (sale_date, staff_id, service_name, quantity, unit_price,
                                     total_amount, payment_method, status, approved_by, approved_at)
                                VALUES (?, ?, ?, 1, ?, ?, 'cash', 'approved', ?, NOW())
                            ")->execute([
                                $saleDate, $staffId, $serviceLabel,
                                $totalAmount, $totalAmount, $adminId
                            ]);
                        }
                        $success .= ' Sale automatically recorded in the system.';
                    }
                } catch (Exception $saleErr) {
                    // Non-fatal — log but don't block the status update
                    error_log('Failed to auto-record sale for booking #' . $bid . ': ' . $saleErr->getMessage());
                    $success .= ' (Note: sale could not be auto-recorded — ' . $saleErr->getMessage() . ')';
                }
            }

            // Send email
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $emailSent = false;
                if ($newStatus === 'confirmed') {
                    $emailSent = sendStatusEmail($booking, 'confirmed');
                    if ($emailSent) {
                        $pdo->prepare("UPDATE bookings SET email_sent = 1 WHERE id = ?")->execute([$bid]);
                        $success .= ' Confirmation email sent.';
                    }
                } elseif ($newStatus === 'cancelled') {
                    $emailSent = sendStatusEmail($booking, 'cancelled');
                    if ($emailSent) $success .= ' Cancellation email sent.';
                } elseif ($newStatus === 'completed') {
                    $emailSent = sendStatusEmail($booking, 'completed');
                    if ($emailSent) $success .= ' Thank-you email sent.';
                }
            }
        } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filter     = $_GET['filter']     ?? 'all';
$search     = trim($_GET['search']     ?? '');
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date']   ?? '';

$where  = [];
$params = [];

if ($filter !== 'all') {
    $where[]  = "b.status = ?";
    $params[] = $filter;
}
if ($search) {
    $where[]  = "(b.full_name LIKE ? OR b.email LIKE ? OR b.booking_reference LIKE ?)";
    $like     = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($start_date) { $where[] = "b.booking_date >= ?"; $params[] = $start_date; }
if ($end_date)   { $where[] = "b.booking_date <= ?"; $params[] = $end_date;   }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT b.*, s.full_name AS stylist_name
    FROM   bookings b
    LEFT JOIN staff s ON b.therapist_id = CAST(s.staff_id AS CHAR)
    $whereSql
    ORDER  BY b.created_at DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Stats for current filter
$totalBookings  = count($bookings);
$totalRevenue   = array_sum(array_column($bookings, 'total_price'));

// Staff list for stylist assignment dropdown
$staff_list = [];
try {
    $staff_list = $pdo->query("SELECT staff_id, full_name FROM staff WHERE status='active' ORDER BY full_name")->fetchAll();
} catch (Exception $e) {}

// Available services for the Edit modal
$services_catalog = [];
try {
    $services_catalog = $pdo->query("SELECT service_id as id, service_name as name, price, duration FROM salon_services WHERE status='active' ORDER BY service_name")->fetchAll();
} catch (\Throwable $e) {
    $services_catalog = [];
}

// ── Email helpers ─────────────────────────────────────────────────────────────
function mailer() {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'serenityspamassage25@gmail.com'; // ← update
    $mail->Password   = 'axsu ktfl xpig rcgd';            // ← update
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    return $mail;
}

function sendStatusEmail($b, $type) {
    try {
        $mail = mailer();
        $mail->setFrom('serenityspamassage25@gmail.com', 'Levy Branzuela Salon');
        $mail->addAddress($b['email'], $b['full_name']);
        $mail->isHTML(true);

        $date    = date('l, F j, Y', strtotime($b['booking_date']));
        $time    = date('g:i A',     strtotime($b['booking_time']));
        $ref     = $b['booking_reference'];
        $name    = htmlspecialchars($b['full_name']);
        $stylist = !empty($b['stylist_name']) ? htmlspecialchars($b['stylist_name']) : 'Will be assigned by our team';
        $total   = number_format($b['total_price'], 2);
        $totalDuration = (int)($b['total_duration'] ?? $b['service_duration'] ?? 0);

        // Build service rows (matching process_booking style)
        $serviceRowsHTML = '';
        $serviceRowsTXT  = '';
        $svcsData = !empty($b['services_json']) ? json_decode($b['services_json'], true) : null;
        if ($svcsData) {
            foreach ($svcsData as $s) {
                $serviceRowsHTML .= "<tr>
                    <td style='padding:8px 0;border-bottom:1px solid #f0f0f0;color:#444;'>" . htmlspecialchars($s['name']) . "</td>
                    <td style='padding:8px 0;border-bottom:1px solid #f0f0f0;text-align:right;'>" . (int)$s['duration'] . " mins</td>
                    <td style='padding:8px 0;border-bottom:1px solid #f0f0f0;text-align:right;'>&#8369;" . number_format($s['price'], 2) . "</td>
                </tr>";
                $serviceRowsTXT .= "  - " . $s['name'] . " (" . (int)$s['duration'] . " mins) — ₱" . number_format($s['price'], 2) . "\n";
            }
        } else {
            $serviceRowsHTML = "<tr>
                <td style='padding:8px 0;border-bottom:1px solid #f0f0f0;color:#444;'>" . htmlspecialchars($b['service_name']) . "</td>
                <td style='padding:8px 0;border-bottom:1px solid #f0f0f0;text-align:right;'>—</td>
                <td style='padding:8px 0;border-bottom:1px solid #f0f0f0;text-align:right;'>&#8369;$total</td>
            </tr>";
            $serviceRowsTXT = "  - " . $b['service_name'] . " — ₱$total\n";
        }

        // Shared outer wrapper (same as process_booking.php)
        $outerOpen = "
        <!DOCTYPE html>
        <html><head><meta charset='UTF-8'></head>
        <body style='font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:20px;background:#f5f5f5;'>
        <div style='max-width:600px;margin:0 auto;background:white;border-radius:8px;overflow:hidden;'>";

        $outerClose = "
            <div style='background:#fef5f5;padding:15px 25px;text-align:center;font-size:12px;color:#888;'>
                Levy Branzuela Salon &nbsp;|&nbsp; (02) 8829-9411 &nbsp;|&nbsp; serenityspamassage25@gmail.com
            </div>
        </div>
        </body></html>";

        $serviceTable = "
                <h3 style='color:#f19e99;border-bottom:2px solid #f19e99;padding-bottom:6px;font-size:15px;'>Appointment Details</h3>
                <table style='width:100%;border-collapse:collapse;margin-bottom:20px;'>
                    <tr style='background:#fef5f5;'>
                        <th style='padding:8px 0;text-align:left;color:#666;font-weight:normal;'>Service</th>
                        <th style='padding:8px 0;text-align:right;color:#666;font-weight:normal;'>Duration</th>
                        <th style='padding:8px 0;text-align:right;color:#666;font-weight:normal;'>Price</th>
                    </tr>
                    $serviceRowsHTML
                    <tr style='background:#fef5f5;font-weight:bold;'>
                        <td style='padding:10px 0;'>Total</td>
                        <td style='padding:10px 0;text-align:right;'>$totalDuration mins</td>
                        <td style='padding:10px 0;text-align:right;'>&#8369;$total</td>
                    </tr>
                </table>
                <table style='width:100%;border-collapse:collapse;margin-bottom:25px;'>
                    <tr><td style='padding:8px 0;border-bottom:1px solid #eee;color:#666;'>Date:</td>
                        <td style='padding:8px 0;border-bottom:1px solid #eee;text-align:right;'>$date</td></tr>
                    <tr><td style='padding:8px 0;border-bottom:1px solid #eee;color:#666;'>Time:</td>
                        <td style='padding:8px 0;border-bottom:1px solid #eee;text-align:right;'>$time</td></tr>
                    <tr><td style='padding:8px 0;border-bottom:1px solid #eee;color:#666;'>Stylist:</td>
                        <td style='padding:8px 0;border-bottom:1px solid #eee;text-align:right;'>$stylist</td></tr>
                    <tr><td style='padding:8px 0;color:#666;'>Payment:</td>
                        <td style='padding:8px 0;text-align:right;'>Pay at Salon on the day of your appointment</td></tr>
                </table>";

        // ── CONFIRMED ──────────────────────────────────────────────────────────
        if ($type === 'confirmed') {
            $mail->Subject = "Appointment Confirmed - $ref - Levy Branzuela Salon";
            $mail->Body = $outerOpen . "
            <div style='background:#f19e99;padding:30px 25px;'>
                <h1 style='color:white;margin:0;font-size:22px;'>Levy Branzuela Salon</h1>
                <h2 style='color:white;margin:8px 0 0;font-size:16px;font-weight:normal;'>Appointment Confirmed ✅</h2>
            </div>
            <div style='padding:30px 25px;'>
                <p style='margin:0 0 15px;'>Dear $name,</p>
                <p style='margin:0 0 20px;'>Great news! Your appointment has been <strong>confirmed</strong> by our team. We look forward to seeing you!</p>

                <div style='background:#fff8f7;border-left:4px solid #f19e99;padding:15px;margin:0 0 25px;border-radius:0 6px 6px 0;'>
                    <strong style='color:#c0615d;'>Booking Reference:</strong>
                    <span style='font-size:1.2em;font-weight:bold;color:#333;margin-left:8px;'>$ref</span>
                </div>

                $serviceTable

                <h3 style='color:#f19e99;border-bottom:2px solid #f19e99;padding-bottom:6px;font-size:15px;'>Important Reminders</h3>
                <ul style='padding-left:20px;margin:10px 0 25px;color:#555;'>
                    <li style='margin-bottom:6px;'>Please arrive <strong>10 minutes early</strong></li>
                    <li style='margin-bottom:6px;'>Bring a valid ID for verification</li>
                    <li style='margin-bottom:6px;'>For changes or cancellations, contact us at least 24 hours in advance</li>
                    <li>Our cancellation policy applies to no-shows and same-day cancellations</li>
                </ul>

                <p>We look forward to serving you!</p>
                <p style='margin-top:20px;'>Warm regards,<br><strong>The Levy Branzuela Salon Team</strong></p>
            </div>" . $outerClose;

            $mail->AltBody =
                "LEVY BRANZUELA SALON — APPOINTMENT CONFIRMED\n" .
                "==========================================\n\n" .
                "Dear $name,\n\n" .
                "Your appointment has been confirmed! Reference: $ref\n\n" .
                "SERVICES:\n$serviceRowsTXT\n" .
                "Total Duration : $totalDuration mins\n" .
                "Total Price    : ₱$total\n\n" .
                "Date    : $date\n" .
                "Time    : $time\n" .
                "Stylist : $stylist\n" .
                "Payment : Pay at Salon on the day of your appointment\n\n" .
                "Please arrive 10 minutes early. Bring a valid ID.\n\n" .
                "Contact: (02) 8829-9411\n\n" .
                "Levy Branzuela Salon";

        // ── CANCELLED ──────────────────────────────────────────────────────────
        } elseif ($type === 'cancelled') {
            $mail->Subject = "Booking Cancelled - $ref - Levy Branzuela Salon";
            $mail->Body = $outerOpen . "
            <div style='background:#f19e99;padding:30px 25px;'>
                <h1 style='color:white;margin:0;font-size:22px;'>Levy Branzuela Salon</h1>
                <h2 style='color:white;margin:8px 0 0;font-size:16px;font-weight:normal;'>Booking Cancelled</h2>
            </div>
            <div style='padding:30px 25px;'>
                <p style='margin:0 0 15px;'>Dear $name,</p>
                <p style='margin:0 0 20px;'>We regret to inform you that your appointment has been <strong>cancelled</strong>.</p>

                <div style='background:#fff8f7;border-left:4px solid #f19e99;padding:15px;margin:0 0 25px;border-radius:0 6px 6px 0;'>
                    <strong style='color:#c0615d;'>Booking Reference:</strong>
                    <span style='font-size:1.2em;font-weight:bold;color:#333;margin-left:8px;'>$ref</span>
                </div>

                $serviceTable

                <p style='margin:0 0 15px;'>If you have any questions or would like to reschedule, please don't hesitate to contact us.</p>
                <p style='margin:0 0 25px;'>We hope to have the opportunity to serve you again soon.</p>

                <p>Warm regards,<br><strong>The Levy Branzuela Salon Team</strong></p>
            </div>" . $outerClose;

            $mail->AltBody =
                "LEVY BRANZUELA SALON — BOOKING CANCELLED\n" .
                "==========================================\n\n" .
                "Dear $name,\n\n" .
                "Your appointment has been cancelled. Reference: $ref\n\n" .
                "SERVICES:\n$serviceRowsTXT\n" .
                "Date    : $date\n" .
                "Time    : $time\n\n" .
                "For questions or to reschedule, contact us at (02) 8829-9411.\n\n" .
                "Levy Branzuela Salon";

        // ── COMPLETED ──────────────────────────────────────────────────────────
        } elseif ($type === 'completed') {
            $mail->Subject = "Thank You for Visiting Us - Levy Branzuela Salon";
            $mail->Body = $outerOpen . "
            <div style='background:#f19e99;padding:30px 25px;'>
                <h1 style='color:white;margin:0;font-size:22px;'>Levy Branzuela Salon</h1>
                <h2 style='color:white;margin:8px 0 0;font-size:16px;font-weight:normal;'>Thank You for Your Visit! ⭐</h2>
            </div>
            <div style='padding:30px 25px;'>
                <p style='margin:0 0 15px;'>Dear $name,</p>
                <p style='margin:0 0 20px;'>Thank you for visiting <strong>Levy Branzuela Salon</strong>! We truly hope you enjoyed your experience with us.</p>

                <div style='background:#fff8f7;border-left:4px solid #f19e99;padding:15px;margin:0 0 25px;border-radius:0 6px 6px 0;'>
                    <strong style='color:#c0615d;'>Booking Reference:</strong>
                    <span style='font-size:1.2em;font-weight:bold;color:#333;margin-left:8px;'>$ref</span>
                </div>

                $serviceTable

                <h3 style='color:#f19e99;border-bottom:2px solid #f19e99;padding-bottom:6px;font-size:15px;'>We'd Love to See You Again!</h3>
                <p style='margin:10px 0 25px;color:#555;'>Book your next appointment anytime — we're always here to make you look and feel your best.</p>

                <p>With gratitude,<br><strong>The Levy Branzuela Salon Team</strong></p>
            </div>" . $outerClose;

            $mail->AltBody =
                "LEVY BRANZUELA SALON — THANK YOU FOR VISITING!\n" .
                "==========================================\n\n" .
                "Dear $name,\n\n" .
                "Thank you for visiting us! We hope you enjoyed your experience.\n\n" .
                "SERVICES RECEIVED:\n$serviceRowsTXT\n" .
                "Total Duration : $totalDuration mins\n" .
                "Total Price    : ₱$total\n\n" .
                "Date    : $date\n" .
                "Time    : $time\n\n" .
                "We look forward to seeing you again!\n\n" .
                "Contact: (02) 8829-9411\n\n" .
                "Levy Branzuela Salon";
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments — Salon Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../assets/images/icon.png">
    <style>
        .status-badge { padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600; }
        .status-pending   { background:#fef3c7;color:#92400e; }
        .status-confirmed { background:#d1fae5;color:#065f46; }
        .status-completed { background:#dbeafe;color:#1e40af; }
        .status-cancelled { background:#fee2e2;color:#991b1b; }
        .ref-badge { font-family:monospace;background:#fef0ef;color:#c0615d;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:700; }
        .services-mini { font-size:12px;color:#444;line-height:1.6;max-width:220px; }
        .services-mini .svc-item { display:flex;justify-content:space-between;gap:8px; }
        .services-mini .svc-price { color:#f19e99;font-weight:600;white-space:nowrap; }
        .assign-form { display:flex;gap:6px;align-items:center; }
        .assign-form select { padding:4px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;min-width:130px; }
        .assign-form button { padding:4px 10px;font-size:12px; }
        .stylist-assigned { font-size:13px;color:#065f46;font-weight:500; }
        .filter-bar { display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin-bottom:20px; }
        .filter-bar label { font-size:13px;color:#555;font-weight:500; }
        .filter-bar input, .filter-bar select { padding:7px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px; }
        .quick-filters { display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px; }
        .quick-filters a { padding:5px 12px;background:#f5f5f5;border:1px solid #ddd;border-radius:4px;font-size:12px;text-decoration:none;color:#333; }
        .quick-filters a:hover, .quick-filters a.active { background:#f19e99;color:white;border-color:#f19e99; }
        .stats-row { display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap; }
        .stat-pill { background:white;border:1px solid #e0e0e0;border-radius:8px;padding:10px 18px;font-size:13px; }
        .stat-pill strong { display:block;font-size:18px;color:#f19e99; }
        /* Action buttons */
        .action-btns { display:flex;flex-direction:column;gap:5px;align-items:stretch; }
        .btn-edit   { background:#4f86f7;color:white;border:none;border-radius:5px;padding:5px 10px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap; }
        .btn-edit:hover { background:#3a6fd8; }
        .btn-remove { background:#ef4444;color:white;border:none;border-radius:5px;padding:5px 10px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap; }
        .btn-remove:hover { background:#c53030; }
        /* Shared overlay modal */
        .admin-modal-overlay { display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.55);z-index:1100;overflow-y:auto; }
        .admin-modal-box { position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:2rem;border-radius:12px;max-width:600px;width:93%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.25); }
        .admin-modal-box h3 { margin:0 0 1.2rem;color:#f19e99;font-size:18px; }
        /* Edit services modal */
        .svc-catalog-grid { display:flex;flex-direction:column;gap:7px;max-height:260px;overflow-y:auto;border:1px solid #eee;border-radius:6px;padding:10px; }
        .svc-catalog-item { display:flex;align-items:center;gap:10px;padding:6px 8px;border-radius:5px;cursor:pointer;transition:background .15s; }
        .svc-catalog-item:hover { background:#fef5f5; }
        .svc-catalog-item input[type=checkbox] { accent-color:#f19e99;width:16px;height:16px;flex-shrink:0; }
        .svc-catalog-item label { flex:1;cursor:pointer;font-size:13px;display:flex;justify-content:space-between;align-items:center; }
        .svc-catalog-item .svc-meta { font-size:11px;color:#999;white-space:nowrap; }
        .selected-services-preview { margin-top:12px;background:#fef5f5;border-radius:6px;padding:10px 14px;font-size:13px;min-height:42px; }
        .selected-services-preview .sp-item { display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #f5d4d4; }
        .selected-services-preview .sp-total { font-weight:700;color:#f19e99;text-align:right;margin-top:6px; }
        /* Confirm remove */
        .remove-confirm-box { background:#fff5f5;border:2px solid #fee2e2;border-radius:8px;padding:18px;margin:10px 0;text-align:center; }
        .remove-confirm-box p { margin:0 0 6px;color:#555;font-size:14px; }
        .remove-confirm-box strong { color:#991b1b;font-size:16px; }
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
            <a href="dashboard.php">&#x1F4CA; Dashboard</a>
            <a href="appointments_admin.php" class="active">&#x1F4C5; Appointments<?php if ($pending_bookings > 0): ?> <span style="background:#f19e99;color:white;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px;"><?php echo $pending_bookings; ?></span><?php endif; ?></a>
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
            <h1>Appointments</h1>
            <div class="user-info">
                <span style="margin-right:15px;">Welcome, <strong><?php echo getCurrentUserName(); ?></strong></span>
                <a href="settings.php" class="btn btn-primary">&#x2699;&#xFE0F; Settings</a>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-pill"><strong><?php echo $totalBookings; ?></strong>Bookings shown</div>
            <div class="stat-pill"><strong>&#x20B1;<?php echo number_format($totalRevenue, 2); ?></strong>Total value</div>
            <div class="stat-pill"><strong><?php echo $pending_bookings; ?></strong>Pending</div>
        </div>

        <!-- Quick filters -->
        <div class="quick-filters">
            <?php
            $qs = http_build_query(array_filter(['search'=>$search,'start_date'=>$start_date,'end_date'=>$end_date]));
            $filters = ['all'=>'All','pending'=>'Pending','confirmed'=>'Confirmed','completed'=>'Completed','cancelled'=>'Cancelled'];
            foreach ($filters as $k => $v):
                $active = $filter === $k ? 'active' : '';
            ?>
            <a href="?filter=<?php echo $k; ?><?php echo $qs ? '&'.$qs : ''; ?>" class="<?php echo $active; ?>"><?php echo $v; ?></a>
            <?php endforeach; ?>
            <a href="?filter=<?php echo $filter; ?>&start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d'); ?>">Today</a>
            <a href="?filter=<?php echo $filter; ?>&start_date=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>">Last 7 Days</a>
            <a href="?filter=<?php echo $filter; ?>&start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-d'); ?>">This Month</a>
        </div>

        <!-- Filter form -->
        <div class="content-box" style="padding:16px 20px;">
            <form method="GET" class="filter-bar">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <div>
                    <label>Search</label><br>
                    <input type="text" name="search" placeholder="Name, email, or reference…" value="<?php echo htmlspecialchars($search); ?>" style="width:220px;">
                </div>
                <div>
                    <label>From</label><br>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div>
                    <label>To</label><br>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div style="align-self:flex-end;">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="appointments_admin.php" class="btn btn-warning" style="margin-left:6px;">Clear</a>
                </div>
            </form>
        </div>

        <!-- Bookings table -->
        <div class="content-box">
            <h2>Booking List</h2>
            <?php if (empty($bookings)): ?>
                <p style="color:#999;padding:20px 0;">No bookings found for the selected filters.</p>
            <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th>Services</th>
                            <th>Date &amp; Time</th>
                            <th>Stylist</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>View</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($bookings as $b):
                        $svcsData = !empty($b['services_json']) ? json_decode($b['services_json'], true) : null;
                    ?>
                        <tr>
                            <!-- Reference -->
                            <td>
                                <span class="ref-badge"><?php echo htmlspecialchars($b['booking_reference']); ?></span><br>
                                <small style="color:#aaa;font-size:11px;"><?php echo date('M j, Y', strtotime($b['created_at'])); ?></small>
                            </td>

                            <!-- Customer -->
                            <td>
                                <strong><?php echo htmlspecialchars($b['full_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($b['email']); ?></small><br>
                                <small><?php echo htmlspecialchars($b['phone']); ?></small>
                            </td>

                            <!-- Services -->
                            <td>
                                <div class="services-mini">
                                    <?php if ($svcsData && is_array($svcsData)): ?>
                                        <?php foreach ($svcsData as $s): ?>
                                            <div class="svc-item">
                                                <span><?php echo htmlspecialchars($s['name']); ?></span>
                                                <span class="svc-price">&#x20B1;<?php echo number_format($s['price'],2); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                        <div style="font-size:11px;color:#999;margin-top:3px;"><?php echo (int)$b['total_duration']; ?> mins total</div>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($b['service_name']); ?>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Date & Time -->
                            <td>
                                <strong><?php echo date('M j, Y', strtotime($b['booking_date'])); ?></strong><br>
                                <small><?php echo date('g:i A', strtotime($b['booking_time'])); ?></small>
                            </td>

                            <!-- Stylist -->
                            <td>
                                <?php if (!empty($b['stylist_name'])): ?>
                                    <span class="stylist-assigned">&#x2713; <?php echo htmlspecialchars($b['stylist_name']); ?></span>
                                <?php else: ?>
                                    <small style="color:#bbb;">Not assigned</small>
                                <?php endif; ?>
                            </td>

                            <!-- Total -->
                            <td><strong>&#x20B1;<?php echo number_format($b['total_price'], 2); ?></strong></td>

                            <!-- Status -->
                            <td>
                                <span class="status-badge status-<?php echo $b['status']; ?>">
                                    <?php echo ucfirst($b['status']); ?>
                                </span>
                            </td>

                            <!-- View -->
                            <td>
                                <button onclick="viewBooking(<?php echo htmlspecialchars(json_encode($b), ENT_QUOTES); ?>)" class="btn btn-warning btn-sm">View</button>
                            </td>

                            <!-- Actions -->
                            <td>
                                <div class="action-btns">
                                    <?php if (in_array($b['status'], ['pending', 'confirmed'])): ?>
                                    <button class="btn-edit"
                                        onclick="openEditModal(<?php echo htmlspecialchars(json_encode($b), ENT_QUOTES); ?>)">
                                        &#9998; Edit
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn-remove"
                                        onclick="openRemoveModal(<?php echo (int)$b['id']; ?>, '<?php echo htmlspecialchars($b['booking_reference'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($b['full_name'], ENT_QUOTES); ?>')">
                                        &#128465; Remove
                                    </button>
                                </div>
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

<!-- Detail Modal -->
<div id="bookingModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;overflow-y:auto;">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:2rem;border-radius:10px;max-width:580px;width:90%;max-height:90vh;overflow-y:auto;">
        <h3 id="modalTitle" style="margin-bottom:1rem;color:#f19e99;">Booking Details</h3>
        <div id="modalContent"></div>
        <div style="text-align:right;margin-top:1rem;">
            <button onclick="closeModal()" class="btn btn-warning">Close</button>
        </div>
    </div>
</div>

<script>
function viewBooking(b) {
    const svcs = b.services_json ? JSON.parse(b.services_json) : null;
    let svcsHtml = '';
    if (svcs && svcs.length) {
        svcsHtml = '<ul style="padding-left:18px;margin:4px 0;">' +
            svcs.map(s => '<li>' + s.name + ' — ₱' + parseFloat(s.price).toLocaleString('en-PH',{minimumFractionDigits:2}) + '</li>').join('') +
            '</ul>';
    } else {
        svcsHtml = b.service_name || '—';
    }

    document.getElementById('modalTitle').textContent = 'Booking — ' + b.booking_reference;
    document.getElementById('modalContent').innerHTML = `
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <tr><td style="padding:7px 0;border-bottom:1px solid #eee;color:#666;width:38%;">Customer</td><td style="padding:7px 0;border-bottom:1px solid #eee;">${b.full_name}</td></tr>
            <tr><td style="padding:7px 0;border-bottom:1px solid #eee;color:#666;">Email</td><td style="padding:7px 0;border-bottom:1px solid #eee;">${b.email}</td></tr>
            <tr><td style="padding:7px 0;border-bottom:1px solid #eee;color:#666;">Phone</td><td style="padding:7px 0;border-bottom:1px solid #eee;">${b.phone}</td></tr>
            <tr><td style="padding:7px 0;border-bottom:1px solid #eee;color:#666;">Services</td><td style="padding:7px 0;border-bottom:1px solid #eee;">${svcsHtml}</td></tr>
            <tr><td style="padding:7px 0;border-bottom:1px solid #eee;color:#666;">Date</td><td style="padding:7px 0;border-bottom:1px solid #eee;">${b.booking_date}</td></tr>
            <tr><td style="padding:7px 0;border-bottom:1px solid #eee;color:#666;">Time</td><td style="padding:7px 0;border-bottom:1px solid #eee;">${b.booking_time}</td></tr>
            <tr><td style="padding:7px 0;border-bottom:1px solid #eee;color:#666;">Stylist</td><td style="padding:7px 0;border-bottom:1px solid #eee;">${b.stylist_name || 'Not yet assigned'}</td></tr>
            <tr><td style="padding:7px 0;border-bottom:1px solid #eee;color:#666;">Duration</td><td style="padding:7px 0;border-bottom:1px solid #eee;">${b.total_duration || b.service_duration || 0} mins</td></tr>
            <tr><td style="padding:7px 0;border-bottom:1px solid #eee;color:#666;">Payment</td><td style="padding:7px 0;border-bottom:1px solid #eee;">Pay at Salon</td></tr>
            <tr><td style="padding:7px 0;border-bottom:1px solid #eee;color:#666;">Status</td><td style="padding:7px 0;border-bottom:1px solid #eee;">${b.status}</td></tr>
            <tr><td style="padding:7px 0;color:#666;font-weight:bold;">Total</td><td style="padding:7px 0;font-weight:bold;color:#f19e99;">₱${parseFloat(b.total_price).toLocaleString('en-PH',{minimumFractionDigits:2})}</td></tr>
        </table>`;
    document.getElementById('bookingModal').style.display = 'block';
}
function closeModal() { document.getElementById('bookingModal').style.display = 'none'; }
document.getElementById('bookingModal').addEventListener('click', e => { if (e.target === document.getElementById('bookingModal')) closeModal(); });
</script>

<!-- ══════════════════════════════════════════════
     EDIT SERVICES MODAL
══════════════════════════════════════════════ -->
<div id="editModal" class="admin-modal-overlay">
    <div class="admin-modal-box">
        <h3>&#9998; Edit Services</h3>
        <p style="font-size:13px;color:#888;margin:-8px 0 14px;">
            Booking: <strong id="editRef"></strong> &mdash; <span id="editCustomer"></span>
        </p>
        <p style="font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">Select services:</p>
        <div class="svc-catalog-grid" id="svcCatalogGrid">
            <?php if (empty($services_catalog)): ?>
                <p style="color:#999;font-size:13px;padding:8px;">No services found in the catalog.</p>
            <?php else: ?>
                <?php foreach ($services_catalog as $svc): ?>
                <div class="svc-catalog-item">
                    <input type="checkbox"
                           id="svc_<?php echo (int)$svc['id']; ?>"
                           value='<?php echo htmlspecialchars(json_encode([
                               "id"       => (int)$svc['id'],
                               "name"     => $svc['name'],
                               "price"    => floatval($svc['price']),
                               "duration" => intval($svc['duration'])
                           ]), ENT_QUOTES); ?>'
                           onchange="updateEditPreview()">
                    <label for="svc_<?php echo (int)$svc['id']; ?>">
                        <span><?php echo htmlspecialchars($svc['name']); ?></span>
                        <span class="svc-meta">
                            <?php echo (int)$svc['duration']; ?> mins &nbsp; &#x20B1;<?php echo number_format($svc['price'],2); ?>
                        </span>
                    </label>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="selected-services-preview" id="editPreview">
            <span style="color:#bbb;font-style:italic;">No services selected yet.</span>
        </div>
        <form method="POST" id="editServicesForm" style="margin-top:14px;">
            <input type="hidden" name="action"        value="edit_services">
            <input type="hidden" name="booking_id"    id="editBookingId">
            <input type="hidden" name="services_json" id="editServicesJson">
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="closeEditModal()" class="btn btn-warning">Cancel</button>
                <button type="button" onclick="submitEditServices()" class="btn btn-primary"
                        style="background:#4f86f7;border-color:#4f86f7;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     REMOVE BOOKING MODAL
══════════════════════════════════════════════ -->
<div id="removeModal" class="admin-modal-overlay">
    <div class="admin-modal-box" style="max-width:440px;">
        <h3 style="color:#ef4444;">&#128465; Remove Appointment</h3>
        <div class="remove-confirm-box">
            <p>You are about to permanently delete:</p>
            <strong id="removeRef"></strong>
            <p style="margin-top:8px;font-size:13px;color:#666;">Customer: <span id="removeCustomer"></span></p>
            <p style="margin-top:4px;font-size:12px;color:#ef4444;">&#9888; This action cannot be undone.</p>
        </div>
        <form method="POST" id="removeBookingForm">
            <input type="hidden" name="action"     value="remove_booking">
            <input type="hidden" name="booking_id" id="removeBookingId">
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px;">
                <button type="button" onclick="closeRemoveModal()" class="btn btn-warning">Cancel</button>
                <button type="submit" class="btn-remove" style="padding:8px 18px;font-size:13px;border-radius:5px;">
                    Yes, Remove
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ─── Edit Services Modal ──────────────────────────────────────────────────────
function openEditModal(b) {
    document.getElementById('editRef').textContent      = b.booking_reference;
    document.getElementById('editCustomer').textContent = b.full_name;
    document.getElementById('editBookingId').value      = b.id;

    let currentSvcs = [];
    try { currentSvcs = b.services_json ? JSON.parse(b.services_json) : []; } catch(e) {}

    const checkboxes = document.querySelectorAll('#svcCatalogGrid input[type=checkbox]');
    checkboxes.forEach(cb => {
        try {
            const svcData = JSON.parse(cb.value);
            cb.checked = currentSvcs.some(s => s.name === svcData.name);
        } catch(e) { cb.checked = false; }
    });

    updateEditPreview();
    document.getElementById('editModal').style.display = 'block';
}

function updateEditPreview() {
    const checkboxes = document.querySelectorAll('#svcCatalogGrid input[type=checkbox]:checked');
    const preview    = document.getElementById('editPreview');

    if (checkboxes.length === 0) {
        preview.innerHTML = '<span style="color:#bbb;font-style:italic;">No services selected yet.</span>';
        return;
    }

    let html = '', total = 0, totalMins = 0;
    checkboxes.forEach(cb => {
        const s = JSON.parse(cb.value);
        total     += s.price;
        totalMins += s.duration;
        html += '<div class="sp-item">'
              + '<span>' + s.name + ' <span style="font-size:11px;color:#aaa;">(' + s.duration + ' mins)</span></span>'
              + '<span>&#x20B1;' + s.price.toLocaleString('en-PH',{minimumFractionDigits:2}) + '</span>'
              + '</div>';
    });
    html += '<div class="sp-total">Total: ' + totalMins + ' mins &nbsp;|&nbsp; &#x20B1;'
          + total.toLocaleString('en-PH',{minimumFractionDigits:2}) + '</div>';
    preview.innerHTML = html;
}

function submitEditServices() {
    const checkboxes = document.querySelectorAll('#svcCatalogGrid input[type=checkbox]:checked');
    if (checkboxes.length === 0) { alert('Please select at least one service.'); return; }
    const services = Array.from(checkboxes).map(cb => JSON.parse(cb.value));
    document.getElementById('editServicesJson').value = JSON.stringify(services);
    document.getElementById('editServicesForm').submit();
}

function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

// ─── Remove Booking Modal ─────────────────────────────────────────────────────
function openRemoveModal(id, ref, customer) {
    document.getElementById('removeBookingId').value      = id;
    document.getElementById('removeRef').textContent      = ref;
    document.getElementById('removeCustomer').textContent = customer;
    document.getElementById('removeModal').style.display  = 'block';
}

function closeRemoveModal() { document.getElementById('removeModal').style.display = 'none'; }
document.getElementById('removeModal').addEventListener('click', function(e) {
    if (e.target === this) closeRemoveModal();
});
</script>

</body>
</html>