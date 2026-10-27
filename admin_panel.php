<?php
session_start();
require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'vendor/autoload.php';

// ── Admin authentication guard ───────────────────────────────────────────────
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}
$adminName = $_SESSION['admin_name'] ?? 'Admin';

// ══════════════════════════════════════════════════════════════════════════════
// BOOKING ACTIONS
// ══════════════════════════════════════════════════════════════════════════════

// Handle stylist assignment
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'assign_stylist') {
    $bookingId = intval($_POST['booking_id']);
    $staffId   = intval($_POST['staff_id']);
    $assignSql  = "UPDATE bookings SET therapist_id = ? WHERE id = ?";
    $assignStmt = $conn->prepare($assignSql);
    $assignStmt->bind_param('ii', $staffId, $bookingId);
    if ($assignStmt->execute()) {
        $message     = "Staff assigned successfully!";
        $messageType = "success";
    } else {
        $message     = "Error assigning staff.";
        $messageType = "error";
    }
    $assignStmt->close();
}

// Handle status updates
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $bookingId = intval($_POST['booking_id']);
    $newStatus = $_POST['status'];
    
    $allowedStatuses = ['pending', 'confirmed', 'cancelled', 'completed'];
    if (in_array($newStatus, $allowedStatuses)) {
        $getBookingSql = "SELECT b.*, s.full_name AS stylist_name FROM bookings b LEFT JOIN staff s ON b.therapist_id = CAST(s.staff_id AS CHAR) WHERE b.id = ?";
        $getBookingStmt = $conn->prepare($getBookingSql);
        $getBookingStmt->bind_param('i', $bookingId);
        $getBookingStmt->execute();
        $bookingResult = $getBookingStmt->get_result();
        $bookingData = $bookingResult->fetch_assoc();
        
        if ($bookingData) {
            $updateSql = "UPDATE bookings SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param('si', $newStatus, $bookingId);
            
            if ($updateStmt->execute()) {
                $message = "Booking status updated successfully!";
                $messageType = "success";

                if ($newStatus === 'completed') {
                    try {
                        $receiptNo = 'BK-' . $bookingData['booking_reference'];
                        $svcsData = !empty($bookingData['services_json'])
                            ? json_decode($bookingData['services_json'], true) : null;
                        if ($svcsData && is_array($svcsData)) {
                            $svcNames     = array_map(fn($s) => $s['name'], $svcsData);
                            $serviceLabel = implode(', ', $svcNames);
                        } else {
                            $serviceLabel = $bookingData['service_name'] ?? 'Salon Service';
                        }
                        // Use the actual completion date (today), not the originally
                        // scheduled appointment date — otherwise a booking completed
                        // after its scheduled date gets filed under the wrong day and
                        // silently disappears from "Today's/Weekly/Monthly Sales" on
                        // the dashboard. We let MySQL compute CURDATE() itself below
                        // (rather than PHP's date()) so it always matches exactly the
                        // CURDATE() the dashboard uses to filter — no timezone drift.
                        $totalAmount = floatval($bookingData['total_price']);
                        $custName    = $bookingData['full_name'];
                        $staffId = null;
                        if (!empty($bookingData['therapist_id'])) {
                            $chkStmt = $conn->prepare("SELECT staff_id FROM staff WHERE staff_id = ? LIMIT 1");
                            $chkStmt->bind_param('i', $bookingData['therapist_id']);
                            $chkStmt->execute();
                            $chkStmt->store_result();
                            if ($chkStmt->num_rows > 0) { $staffId = intval($bookingData['therapist_id']); }
                            $chkStmt->close();
                        }
                        $dupChk = $conn->prepare("SELECT sale_id FROM sales WHERE receipt_no = ? LIMIT 1");
                        $dupChk->bind_param('s', $receiptNo);
                        $dupChk->execute();
                        $dupChk->store_result();
                        if ($dupChk->num_rows === 0) {
                            $dupChk->close();
                            // sale_date is CURDATE() (MySQL-side "today"), not a bound param
                            $insSql  = "INSERT INTO sales (sale_date, staff_id, service_name, quantity, unit_price, total_amount, payment_method, status, approved_at, customer_name, receipt_no) VALUES (CURDATE(), ?, ?, 1, ?, ?, 'cash', 'approved', NOW(), ?, ?)";
                            $insStmt = $conn->prepare($insSql);
                            $saleRecorded = false;
                            if ($insStmt) {
                                $insStmt->bind_param('isddss', $staffId, $serviceLabel, $totalAmount, $totalAmount, $custName, $receiptNo);
                                if ($insStmt->execute()) {
                                    $saleRecorded = true;
                                    $message .= " Sale automatically recorded.";
                                }
                                $insStmt->close();
                            }
                            if (!$saleRecorded) {
                                // Fallback for older DB schemas without customer_name / receipt_no columns
                                $insFallback = $conn->prepare("INSERT INTO sales (sale_date, staff_id, service_name, quantity, unit_price, total_amount, payment_method, status, approved_at) VALUES (CURDATE(), ?, ?, 1, ?, ?, 'cash', 'approved', NOW())");
                                if ($insFallback) {
                                    $insFallback->bind_param('isdd', $staffId, $serviceLabel, $totalAmount, $totalAmount);
                                    if ($insFallback->execute()) {
                                        $saleRecorded = true;
                                        $message .= " Sale recorded.";
                                    }
                                    $insFallback->close();
                                }
                            }
                            if (!$saleRecorded) {
                                error_log('Auto-sale insert failed for booking ' . $bookingId . ': ' . $conn->error);
                                $message .= " (Note: sale could not be auto-recorded — please add it manually via POS.)";
                            }
                        } else { $dupChk->close(); }
                    } catch (Throwable $saleErr) {
                        error_log('Auto-sale error: ' . $saleErr->getMessage());
                        $message .= " (Note: sale could not be auto-recorded.)";
                    }
                }

                if ($newStatus === 'confirmed') {
                    $emailSent = sendBookingConfirmationEmail($bookingData);
                    $message .= $emailSent ? " Confirmation email sent." : " Note: email could not be sent.";
                } elseif ($newStatus === 'cancelled') {
                    $emailSent = sendCancellationEmail($bookingData);
                    $message .= $emailSent ? " Cancellation email sent." : " Note: email could not be sent.";
                } elseif ($newStatus === 'completed') {
                    $emailSent = sendCompletionEmail($bookingData);
                    $message .= $emailSent ? " Thank-you email sent." : " Note: email could not be sent.";
                }
            } else {
                $message = "Error updating booking status.";
                $messageType = "error";
            }
        } else {
            $message = "Booking not found.";
            $messageType = "error";
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// POS ACTIONS (walk-in sales)
// ══════════════════════════════════════════════════════════════════════════════

$posSuccess = '';
$posError   = '';
if (isset($_GET['sale_ok'])) {
    $posSuccess = htmlspecialchars(urldecode($_GET['sale_ok']));
}

// Add service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_service') {
    $svcName     = trim($_POST['svc_name'] ?? '');
    $svcPrice    = floatval($_POST['svc_price'] ?? 0);
    $svcCat      = trim($_POST['svc_category'] ?? 'General');
    $svcDuration = intval($_POST['svc_duration'] ?? 60);
    if ($svcName === '') { $posError = 'Service name is required.'; }
    elseif ($svcPrice < 0) { $posError = 'Price cannot be negative.'; }
    elseif ($svcDuration < 1) { $posError = 'Duration must be at least 1 minute.'; }
    else {
        $insSvc = $conn->prepare("INSERT INTO salon_services (service_name, price, category, duration, status) VALUES (?, ?, ?, ?, 'active') ON DUPLICATE KEY UPDATE price = VALUES(price), category = VALUES(category), duration = VALUES(duration), status = 'active'");
        if ($insSvc) { $insSvc->bind_param('sdsi', $svcName, $svcPrice, $svcCat, $svcDuration); $insSvc->execute(); $insSvc->close(); $posSuccess = 'Service "' . htmlspecialchars($svcName) . '" saved.'; }
        else { $posError = 'DB error: ' . $conn->error; }
    }
}

// Add product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_product') {
    $prodName  = trim($_POST['prod_name'] ?? '');
    $prodPrice = floatval($_POST['prod_price'] ?? 0);
    $prodQty   = intval($_POST['prod_qty'] ?? 0);
    if ($prodName === '') { $posError = 'Product name is required.'; }
    elseif ($prodPrice < 0) { $posError = 'Price cannot be negative.'; }
    else {
        $prodStatus = $prodQty > 0 ? 'available' : 'out_of_stock';
        $insProd = $conn->prepare("INSERT INTO products (product_name, price, quantity, status) VALUES (?, ?, ?, ?)");
        if ($insProd) { $insProd->bind_param('sdis', $prodName, $prodPrice, $prodQty, $prodStatus); $insProd->execute(); $insProd->close(); $posSuccess = 'Product "' . htmlspecialchars($prodName) . '" saved.'; }
        else { $posError = 'DB error: ' . $conn->error; }
    }
}

// Remove service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_service') {
    $svcId = intval($_POST['svc_id'] ?? 0);
    $delSvc = $conn->prepare("DELETE FROM salon_services WHERE service_id = ?");
    if ($delSvc) { $delSvc->bind_param('i', $svcId); $delSvc->execute(); $delSvc->close(); $posSuccess = 'Service deleted.'; }
}

// Remove product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_product') {
    $prodId = intval($_POST['prod_id'] ?? 0);
    $delProd = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    if ($delProd) { $delProd->bind_param('i', $prodId); $delProd->execute(); $delProd->close(); $posSuccess = 'Product deleted.'; }
}

// Delete a sale
if (isset($_GET['delete_sale'])) {
    $delSaleId = intval($_GET['delete_sale']);
    $dsSel = $conn->prepare("SELECT product_id, quantity FROM sales WHERE sale_id = ? AND status = 'approved'");
    if ($dsSel) {
        $dsSel->bind_param('i', $delSaleId);
        $dsSel->execute();
        $dsRow = $dsSel->get_result()->fetch_assoc();
        $dsSel->close();
        if ($dsRow && $dsRow['product_id']) {
            $restoreQ = $conn->prepare("UPDATE products SET quantity = quantity + ?, status = 'available' WHERE product_id = ?");
            $restoreQ->bind_param('ii', $dsRow['quantity'], $dsRow['product_id']);
            $restoreQ->execute(); $restoreQ->close();
        }
    }
    $delS = $conn->prepare("DELETE FROM sales WHERE sale_id = ?");
    if ($delS) { $delS->bind_param('i', $delSaleId); $delS->execute(); $delS->close(); $posSuccess = 'Transaction #' . $delSaleId . ' removed.'; }
}

// Edit a sale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_sale') {
    $editId      = intval($_POST['edit_sale_id']);
    $editDate    = $_POST['edit_sale_date'];
    $editSvcName = $_POST['edit_service_name'] ?? '';
    $editQty     = intval($_POST['edit_quantity']);
    $editPrice   = floatval($_POST['edit_unit_price']);
    $editPay     = $_POST['edit_payment_method'];
    $upSale = $conn->prepare("UPDATE sales SET sale_date=?, service_name=?, quantity=?, unit_price=?, total_amount=?, payment_method=?, approved_at=NOW() WHERE sale_id=?");
    if ($upSale) { $upSale->bind_param('ssiddsi', $editDate, $editSvcName, $editQty, $editPrice, $editPrice, $editPay, $editId); $upSale->execute(); $upSale->close(); $posSuccess = 'Transaction #' . $editId . ' updated.'; }
}

// Complete walk-in sale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete_sale') {
    $saleDate     = $_POST['sale_date'];
    $saleStaffId  = (isset($_POST['staff_id']) && intval($_POST['staff_id']) > 0) ? intval($_POST['staff_id']) : null;
    $salePay      = $_POST['payment_method'];
    $saleCust     = trim($_POST['customer_name'] ?? '');
    $cartItems    = json_decode($_POST['cart_data'] ?? '[]', true);
    $adminId      = $_SESSION['admin_id'] ?? null;

    if (empty($cartItems)) {
        $posError = 'Cart is empty.';
    } else {
        $names = []; $grandTotal = 0.0;
        foreach ($cartItems as $item) {
            $names[]     = $item['name'] . ' x' . $item['qty'];
            $grandTotal += floatval($item['price']) * intval($item['qty']);
        }
        $label     = implode(', ', $names);
        $receiptNo = 'RCP-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));

        $singleProdId = null;
        $prodItems = array_filter($cartItems, fn($i) => $i['type'] === 'product');
        if (count($cartItems) === 1 && count($prodItems) === 1) {
            $singleProdId = intval(array_values($prodItems)[0]['id']);
        }

        // Sum all item quantities for the quantity column
        $totalQtySold = 0;
        foreach ($cartItems as $ci) { $totalQtySold += intval($ci['qty']); }

        // mysqli bind_param needs named variables (no expressions/nulls inline)
        $bindStaffId  = $saleStaffId;
        $bindProdId   = $singleProdId;
        $bindAdminId  = $adminId;
        $bindCustName = ($saleCust !== '') ? $saleCust : null;

        // SQL: 11 ? placeholders
        // sale_date(s) staff_id(i) product_id(i) service_name(s) quantity(i)
        // unit_price(d) total_amount(d) payment_method(s)
        // approved_by(i) customer_name(s) receipt_no(s)
        $insSale = $conn->prepare(
            "INSERT INTO sales
                (sale_date, staff_id, product_id, service_name, quantity,
                 unit_price, total_amount, payment_method, status,
                 approved_by, approved_at, customer_name, receipt_no)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), ?, ?)"
        );
        if ($insSale) {
            $insSale->bind_param(
                'siisiddsiss',
                $saleDate, $bindStaffId, $bindProdId, $label, $totalQtySold,
                $grandTotal, $grandTotal, $salePay,
                $bindAdminId, $bindCustName, $receiptNo
            );
            if ($insSale->execute()) {
                foreach ($cartItems as $item) {
                    if ($item['type'] === 'product') {
                        $pid = intval($item['id']); $qty = intval($item['qty']);
                        $upProd = $conn->prepare("UPDATE products SET quantity = quantity - ?, status = CASE WHEN (quantity - ?) <= 0 THEN 'out_of_stock' ELSE 'available' END WHERE product_id = ?");
                        $upProd->bind_param('iii', $qty, $qty, $pid); $upProd->execute(); $upProd->close();
                    }
                }
                $insSale->close();
                // Redirect immediately so data fetches run on a clean GET request
                $encodedMsg = urlencode('Sale recorded! Receipt: ' . $receiptNo . ' — Total: ₱' . number_format($grandTotal, 2));
                header('Location: admin_panel.php?tab=pos&sale_ok=' . $encodedMsg);
                exit();
            } else {
                $posError = 'Error saving sale: ' . $conn->error;
                $insSale->close();
            }
        } else {
            // Fallback — older DB without customer_name / receipt_no columns
            $insSaleFb = $conn->prepare(
                "INSERT INTO sales
                    (sale_date, staff_id, service_name, quantity,
                     unit_price, total_amount, payment_method, status, approved_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', NOW())"
            );
            if ($insSaleFb) {
                $fbStaffId = $bindStaffId;
                $insSaleFb->bind_param('siisdds', $saleDate, $fbStaffId, $label, $totalQtySold, $grandTotal, $grandTotal, $salePay);
                $insSaleFb->execute(); $insSaleFb->close();
                $posSuccess = 'Sale recorded! Total: &#x20B1;' . number_format($grandTotal, 2);
            }
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// DATA FETCHES
// ══════════════════════════════════════════════════════════════════════════════

// Fetch bookings
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql    = "SELECT b.*, s.full_name AS stylist_name FROM bookings b LEFT JOIN staff s ON b.therapist_id = CAST(s.staff_id AS CHAR) WHERE 1=1";
$params = []; $types = '';

if ($filter !== 'all') { $sql .= " AND b.status = ?"; $params[] = $filter; $types .= 's'; }
if ($search) {
    $sql .= " AND (b.full_name LIKE ? OR b.email LIKE ? OR b.booking_reference LIKE ?)";
    $sp = '%' . $search . '%'; $params[] = $sp; $params[] = $sp; $params[] = $sp; $types .= 'sss';
}
$sql .= " ORDER BY b.booking_date DESC, b.booking_time DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch staff
$staffList   = [];
$staffResult = $conn->query("SELECT staff_id, full_name FROM staff WHERE status='active' ORDER BY full_name");
if ($staffResult) { while ($row = $staffResult->fetch_assoc()) { $staffList[] = $row; } }

// Close the bookings stmt and flush any buffered results before running more queries
if (isset($stmt)) { $stmt->close(); unset($stmt); }
while ($conn->more_results()) { $conn->next_result(); }

// Fetch POS data
$posProducts = [];
$posServices = [];
$recentSales = [];

$prodRes = $conn->query("SELECT product_id, product_name, price, quantity FROM products WHERE status='available' ORDER BY product_name");
if ($prodRes) {
    while ($r = $prodRes->fetch_assoc()) { $posProducts[] = $r; }
    $prodRes->free();
} else {
    error_log("POS products query failed: " . $conn->error);
}

$svcRes = $conn->query("SELECT service_id, service_name, price, category, duration FROM salon_services WHERE status='active' ORDER BY service_name");
if ($svcRes) {
    while ($r = $svcRes->fetch_assoc()) { $posServices[] = $r; }
    $svcRes->free();
} else {
    // salon_services table might not exist — try alternate table name
    $svcRes2 = $conn->query("SELECT service_id, name AS service_name, price, '' AS category FROM services WHERE status='active' ORDER BY name");
    if ($svcRes2) {
        while ($r = $svcRes2->fetch_assoc()) { $posServices[] = $r; }
        $svcRes2->free();
    }
    error_log("POS services query failed: " . $conn->error);
}

try {
    $saleRes = $conn->query("SELECT s.sale_id, s.sale_date, s.approved_at, s.quantity, s.total_amount, s.payment_method, s.service_name, s.unit_price, COALESCE(NULLIF(s.manual_staff_name,''), st.full_name) AS staff_name, s.customer_name, s.receipt_no FROM sales s LEFT JOIN staff st ON s.staff_id = st.staff_id WHERE s.status='approved' ORDER BY s.approved_at DESC, s.sale_id DESC LIMIT 20");
    if ($saleRes) { while ($r = $saleRes->fetch_assoc()) { $recentSales[] = $r; } }
} catch (Throwable $e) {
    $saleRes2 = $conn->query("SELECT s.sale_id, s.sale_date, s.approved_at, s.quantity, s.total_amount, s.payment_method, s.service_name, s.unit_price, COALESCE(st.full_name,'N/A') AS staff_name, NULL AS customer_name, NULL AS receipt_no FROM sales s LEFT JOIN staff st ON s.staff_id = st.staff_id WHERE s.status='approved' ORDER BY s.approved_at DESC LIMIT 20");
    if ($saleRes2) { while ($r = $saleRes2->fetch_assoc()) { $recentSales[] = $r; } }
}

$posProductsJson = json_encode($posProducts, JSON_UNESCAPED_UNICODE);
$posServicesJson = json_encode($posServices, JSON_UNESCAPED_UNICODE);

// ══════════════════════════════════════════════════════════════════════════════
// EMAIL FUNCTIONS (unchanged from original)
// ══════════════════════════════════════════════════════════════════════════════

// ══════════════════════════════════════════════════════════════════════════════
// EMAIL FUNCTIONS
// ══════════════════════════════════════════════════════════════════════════════

function sendBookingConfirmationEmail($bookingData) {
    $mail = new PHPMailer(true);
    try {
        $mail->CharSet  = 'UTF-8';
        $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
        $mail->Username = "serenityspamassage25@gmail.com"; $mail->Password = "axsu ktfl xpig rcgd";
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = 587;
        $mail->setFrom('serenityspamassage25@gmail.com', 'Levy Branzuela Salon');
        $mail->addAddress($bookingData['email'], $bookingData['full_name']);
        $mail->isHTML(true); $mail->Subject = 'Booking Confirmed - ' . $bookingData['booking_reference'];
        $stylistDisplay = !empty($bookingData['stylist_name']) ? $bookingData['stylist_name'] : 'Will be assigned by our team';
        $details = [
            'reference'      => $bookingData['booking_reference'],
            'name'           => $bookingData['full_name'],
            'email'          => $bookingData['email'],
            'phone'          => $bookingData['phone'],
            'service'        => $bookingData['service_name'],
            'date'           => date('l, F j, Y', strtotime($bookingData['booking_date'])),
            'time'           => date('g:i A', strtotime($bookingData['booking_time'])),
            'room'           => getRoomDisplayName($bookingData['room_type']),
            'stylist'        => $stylistDisplay,
            'duration'       => $bookingData['service_duration'],
            'total'          => $bookingData['total_price'],
            'payment_method' => ucfirst($bookingData['payment_method'])
        ];
        $healthInfo = !empty($bookingData['health_info']) ? json_decode($bookingData['health_info'], true) : null;
        $mail->Body    = generateConfirmationEmailHTML($details, $healthInfo);
        $mail->AltBody = generateConfirmationEmailText($details, $healthInfo);
        return $mail->send();
    } catch (Exception $e) { error_log("Email failed: " . $e->getMessage()); return false; }
}

function generateConfirmationEmailHTML($details, $healthInfo) {
    $formattedTotal = number_format((float)$details['total'], 2);
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:20px;'><div style='max-width:600px;margin:0 auto;background:white;'><div style='border-bottom:2px solid #e8958f;padding-bottom:20px;margin-bottom:20px;'><h1 style='color:#e8958f;margin:0;'>Levy Branzuela Salon</h1><h2 style='color:#666;margin:10px 0 0 0;'>Booking Confirmed!</h2></div><p>Dear {$details['name']},</p><p>Your booking has been <strong>confirmed</strong>. We look forward to welcoming you!</p><h3 style='color:#e8958f;'>Appointment Details</h3><table style='width:100%;border-collapse:collapse;margin-bottom:20px;'><tr><td style='padding:8px 0;border-bottom:1px solid #eee;'><strong>Reference:</strong></td><td style='padding:8px 0;border-bottom:1px solid #eee;'>{$details['reference']}</td></tr><tr><td style='padding:8px 0;border-bottom:1px solid #eee;'><strong>Service:</strong></td><td style='padding:8px 0;border-bottom:1px solid #eee;'>{$details['service']}</td></tr><tr><td style='padding:8px 0;border-bottom:1px solid #eee;'><strong>Date:</strong></td><td style='padding:8px 0;border-bottom:1px solid #eee;'>{$details['date']}</td></tr><tr><td style='padding:8px 0;border-bottom:1px solid #eee;'><strong>Time:</strong></td><td style='padding:8px 0;border-bottom:1px solid #eee;'>{$details['time']}</td></tr><tr><td style='padding:8px 0;border-bottom:1px solid #eee;'><strong>Stylist:</strong></td><td style='padding:8px 0;border-bottom:1px solid #eee;'>{$details['stylist']}</td></tr><tr><td style='padding:10px 8px;font-weight:bold;background:#f8f9fa;'><strong>Total:</strong></td><td style='padding:10px 8px;font-weight:bold;background:#f8f9fa;'>&#8369;{$formattedTotal}</td></tr></table><p>Best regards,<br>The Levy Branzuela Salon Team</p></div></body></html>";
}

function generateConfirmationEmailText($details, $healthInfo) {
    $formattedTotal = number_format((float)$details['total'], 2);
    return "LEVY BRANZUELA SALON - BOOKING CONFIRMED\n\nDear {$details['name']},\nYour booking is confirmed.\n\nReference: {$details['reference']}\nService: {$details['service']}\nDate: {$details['date']}\nTime: {$details['time']}\nStylist: {$details['stylist']}\nTotal: &#8369;{$formattedTotal}\n\nBest regards,\nLevy Branzuela Salon Team";
}

function sendCancellationEmail($bookingData) {
    $mail = new PHPMailer(true);
    try {
        $mail->CharSet  = 'UTF-8';
        $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
        $mail->Username = "serenityspamassage25@gmail.com"; $mail->Password = "axsu ktfl xpig rcgd";
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = 587;
        $mail->setFrom('serenityspamassage25@gmail.com', 'Levy Branzuela Salon');
        $mail->addAddress($bookingData['email'], $bookingData['full_name']);
        $mail->isHTML(true); $mail->Subject = 'Booking Cancellation - ' . $bookingData['booking_reference'];
        $mail->Body    = generateCancellationEmailHTML($bookingData);
        $mail->AltBody = generateCancellationEmailText($bookingData);
        return $mail->send();
    } catch (Exception $e) { error_log("Cancellation email failed: " . $e->getMessage()); return false; }
}

function generateCancellationEmailHTML($bookingData) {
    $bookingDate = date('l, F j, Y', strtotime($bookingData['booking_date']));
    $bookingTime = date('g:i A', strtotime($bookingData['booking_time']));
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:20px;'><div style='max-width:600px;margin:0 auto;'><div style='border-bottom:2px solid #e8958f;padding-bottom:20px;margin-bottom:20px;'><h1 style='color:#e8958f;margin:0;'>Levy Branzuela Salon</h1><h2 style='color:#666;'>Booking Cancellation</h2></div><p>Dear {$bookingData['full_name']},</p><p>Your appointment has been cancelled.</p><table style='width:100%;border-collapse:collapse;margin-bottom:20px;'><tr><td style='padding:8px 0;border-bottom:1px solid #eee;'><strong>Reference:</strong></td><td>{$bookingData['booking_reference']}</td></tr><tr><td style='padding:8px 0;border-bottom:1px solid #eee;'><strong>Service:</strong></td><td>{$bookingData['service_name']}</td></tr><tr><td style='padding:8px 0;'><strong>Date:</strong></td><td>{$bookingDate} at {$bookingTime}</td></tr></table><p>We hope to serve you again. Best regards,<br>Levy Branzuela Salon Team</p></div></body></html>";
}

function generateCancellationEmailText($bookingData) {
    return "LEVY BRANZUELA SALON - CANCELLATION\n\nDear {$bookingData['full_name']},\nYour booking #{$bookingData['booking_reference']} has been cancelled.\n\nWe hope to serve you again.\n\nLevy Branzuela Salon Team";
}

function sendCompletionEmail($bookingData) {
    $mail = new PHPMailer(true);
    try {
        $mail->CharSet  = 'UTF-8';
        $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
        $mail->Username = "serenityspamassage25@gmail.com"; $mail->Password = "axsu ktfl xpig rcgd";
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = 587;
        $mail->setFrom('serenityspamassage25@gmail.com', 'Levy Branzuela Salon');
        $mail->addAddress($bookingData['email'], $bookingData['full_name']);
        $mail->isHTML(true); $mail->Subject = 'Thank You for Visiting - ' . $bookingData['booking_reference'];
        $mail->Body    = generateCompletionEmailHTML($bookingData);
        $mail->AltBody = generateCompletionEmailText($bookingData);
        return $mail->send();
    } catch (Exception $e) { error_log("Completion email failed: " . $e->getMessage()); return false; }
}

function generateCompletionEmailHTML($bookingData) {
    $bookingDate    = date('l, F j, Y', strtotime($bookingData['booking_date']));
    $fullName       = htmlspecialchars($bookingData['full_name'] ?? 'Valued Customer');
    $bookingRef     = htmlspecialchars($bookingData['booking_reference'] ?? 'N/A');
    $serviceName    = htmlspecialchars($bookingData['service_name'] ?? 'Salon Service');
    $formattedTotal = number_format(floatval($bookingData['total_price'] ?? 0), 2);
    
    return <<<EOT
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #f4a8a8 0%, #d4827a 100%); padding: 40px 20px; text-align: center; color: white; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 300; letter-spacing: 1px; }
        .header p { margin: 10px 0 0 0; font-size: 14px; font-weight: 300; }
        .content { padding: 40px; color: #333; line-height: 1.6; }
        .greeting { font-size: 16px; margin-bottom: 20px; }
        .section { margin-bottom: 35px; }
        .section-title { font-size: 14px; font-weight: 600; color: #d4827a; text-transform: uppercase; letter-spacing: 1px; padding-bottom: 10px; border-bottom: 2px solid #f0e6e6; margin-bottom: 15px; }
        .booking-details { background-color: #faf9f8; padding: 15px; border-radius: 4px; font-size: 14px; }
        .detail-row { margin-bottom: 10px; display: flex; justify-content: space-between; }
        .detail-row:last-child { margin-bottom: 0; }
        .detail-label { font-weight: 600; color: #666; }
        .detail-value { color: #333; }
        .feedback-box { background-color: #fff5f5; padding: 15px; border-left: 4px solid #d4827a; border-radius: 4px; font-size: 14px; }
        .bullet-points { list-style: none; padding: 0; margin: 15px 0; }
        .bullet-points li { margin-bottom: 10px; padding-left: 20px; position: relative; font-size: 14px; }
        .bullet-points li:before { content: "✓"; position: absolute; left: 0; color: #d4827a; font-weight: bold; }
        .contact-info { background-color: #faf9f8; padding: 15px; border-radius: 4px; font-size: 13px; text-align: center; }
        .contact-info a { color: #d4827a; text-decoration: none; }
        .footer { background-color: #faf9f8; padding: 30px 40px; text-align: center; color: #666; font-size: 13px; line-height: 1.8; }
        .footer-signature { margin: 20px 0 15px 0; font-style: italic; color: #333; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Levy Branzuela Salon</h1>
            <p>Thank You for Visiting Us</p>
        </div>
        
        <div class="content">
            <div class="greeting">
                <p>Dear {$fullName},</p>
                <p>Thank you for choosing Levy Branzuela Salon for your <strong>{$serviceName}</strong> session on {$bookingDate}.</p>
            </div>
            
            <div class="section">
                <div class="section-title">Booking Details</div>
                <div class="booking-details">
                    <div class="detail-row">
                        <span class="detail-label">Booking Reference:</span>
                        <span class="detail-value">{$bookingRef}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Service:</span>
                        <span class="detail-value">{$serviceName}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Total Amount:</span>
                        <span class="detail-value"><strong>&#8369;{$formattedTotal}</strong></span>
                    </div>
                </div>
                <p>We hope you enjoyed your relaxing experience with us and feel refreshed and rejuvenated!</p>
            </div>
            
            <div class="section">
                <div class="section-title">We Value Your Feedback</div>
                <div class="feedback-box">
                    <p>Your opinion matters to us! Please take a few minutes to share your experience by emailing us at:</p>
                    <p style="margin: 10px 0; text-align: center;">
                        <strong><a href="mailto:serenitysgamassage25@gmail.com">serenitysgamassage25@gmail.com</a></strong>
                    </p>
                    <p>Your feedback helps us improve our services and provide you with even better experiences.</p>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">Book Your Next Visit</div>
                <p>We would love to welcome you back! Consider booking your next appointment to maintain your wellness routine:</p>
                <ul class="bullet-points">
                    <li>Regular salon treatments help maintain stress relief and wellness</li>
                    <li>Book online through our website for convenience</li>
                    <li>Ask about our loyalty program for returning customers</li>
                </ul>
                <div class="contact-info">
                    <p><strong>Questions or want to book again?</strong></p>
                    <p>Email: <a href="mailto:serenitysgamassage25@gmail.com">serenitysgamassage25@gmail.com</a></p>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>Thank you again for choosing us. We look forward to serving you again soon!</p>
            <div class="footer-signature">Warm regards,<br>The Levy Branzuela Salon Team</div>
            <p style="margin: 15px 0 0 0; border-top: 1px solid #e0e0e0; padding-top: 15px;">
                Email: <a href="mailto:serenitysgamassage25@gmail.com" style="color: #d4827a; text-decoration: none;">serenitysgamassage25@gmail.com</a>
            </p>
        </div>
    </div>
</body>
</html>
EOT;
}

function generateCompletionEmailText($bookingData) {
    $fullName       = $bookingData['full_name'] ?? 'Valued Customer';
    $bookingRef     = $bookingData['booking_reference'] ?? 'N/A';
    $serviceName    = $bookingData['service_name'] ?? 'Salon Service';
    $formattedTotal = number_format(floatval($bookingData['total_price'] ?? 0), 2);
    $bookingDate    = date('l, F j, Y', strtotime($bookingData['booking_date']));
    
    return <<<EOT
LEVY BRANZUELA SALON - THANK YOU

Dear {$fullName},

Thank you for choosing Levy Branzuela Salon for your {$serviceName} session on {$bookingDate}.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
BOOKING DETAILS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Booking Reference: {$bookingRef}
Service: {$serviceName}
Total Amount: &#8369;{$formattedTotal}

We hope you enjoyed your relaxing experience with us and feel refreshed and rejuvenated!

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
WE VALUE YOUR FEEDBACK
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Your opinion matters to us! Please take a few minutes to share your experience:
Email: serenitysgamassage25@gmail.com

Your feedback helps us improve our services.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
BOOK YOUR NEXT VISIT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

We would love to welcome you back! Consider booking your next appointment to maintain your wellness routine:

• Regular salon treatments help maintain stress relief and wellness
• Book online through our website for convenience
• Ask about our loyalty program for returning customers

Questions or want to book again?
Email: serenitysgamassage25@gmail.com

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Thank you again for choosing us. We look forward to serving you again soon!

Warm regards,
The Levy Branzuela Salon Team

Email: serenitysgamassage25@gmail.com
EOT;
}
function getRoomDisplayName($roomType) {
    $roomNames = ['single' => 'Single Treatment Room', 'couples' => 'Couples Room', 'vip' => 'VIP Suite'];
    return $roomNames[$roomType] ?? 'Standard Room';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel — Levy Branzuela Salon</title>
    <link rel="icon" href="images/Untitled.png">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>

<!-- ══ HEADER ══════════════════════════════════════════════════════════════ -->
<div class="header">
    <div class="header-brand">
        <img src="images/Untitled.png" alt="Logo" class="brand-logo">
        <h1><span>Levy Branzuela</span> Salon</h1>
    </div>
    <div class="header-right">
        <span class="header-welcome">Welcome, <strong><?php echo htmlspecialchars($adminName); ?></strong></span>
        <a href="admin_logout.php" class="btn-logout"
           onclick="return confirm('Are you sure you want to log out?');">&#x2192; Logout</a>
    </div>
</div>

<!-- ══ NAV TABS ════════════════════════════════════════════════════════════ -->
<nav class="admin-nav">
    <div class="admin-nav-tab active" id="navBookings" onclick="switchAdminTab('bookings')">
        <span class="tab-icon">&#x1F4C5;</span> Bookings
    </div>
    <div class="admin-nav-tab" id="navPOS" onclick="switchAdminTab('pos')">
        <span class="tab-icon">&#x1F4B0;</span> Point of Sale
    </div>
</nav>

<!-- ══ MAIN CONTAINER ══════════════════════════════════════════════════════ -->
<div class="container">

    <!-- ── BOOKINGS TAB ─────────────────────────────────────────────────── -->
    <div id="panelBookings" class="tab-panel active">

        <?php if (isset($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="filters">
            <form method="GET" class="filter-group">
                <input type="hidden" name="tab" value="bookings">
                <label for="filter">Status:</label>
                <select name="filter" id="filter">
                    <option value="all"       <?php echo $filter==='all'?'selected':''; ?>>All Bookings</option>
                    <option value="pending"   <?php echo $filter==='pending'?'selected':''; ?>>Pending</option>
                    <option value="confirmed" <?php echo $filter==='confirmed'?'selected':''; ?>>Confirmed</option>
                    <option value="completed" <?php echo $filter==='completed'?'selected':''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $filter==='cancelled'?'selected':''; ?>>Cancelled</option>
                </select>
                <label for="search">Search:</label>
                <input type="text" name="search" id="search" placeholder="Name, email, or reference…" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($filter !== 'all' || $search): ?>
                    <a href="admin_panel.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="bookings-table">
            <?php if (empty($bookings)): ?>
                <div class="no-bookings">
                    <h3>No bookings found</h3>
                    <p>There are no bookings matching your criteria.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Date &amp; Time</th>
                            <th>Stylist</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($booking['booking_reference']); ?></strong><br>
                                    <small style="color:#b09e9a;"><?php echo date('M j, Y', strtotime($booking['created_at'])); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($booking['full_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($booking['email']); ?></small><br>
                                    <small><?php echo htmlspecialchars($booking['phone']); ?></small>
                                </td>
                                <td>
                                    <?php
                                    $bSvcs = !empty($booking['services_json']) ? json_decode($booking['services_json'], true) : null;
                                    if ($bSvcs && is_array($bSvcs)):
                                    ?>
                                        <div style="font-size:13px;line-height:1.7;">
                                        <?php foreach ($bSvcs as $bSvc): ?>
                                            <div style="display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #f3ede8;padding:1px 0;">
                                                <span><?php echo htmlspecialchars($bSvc['name']); ?></span>
                                                <span style="color:#e8958f;font-weight:600;white-space:nowrap;">&#x20B1;<?php echo number_format($bSvc['price'],2); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                        </div>
                                        <small style="color:#b09e9a;"><?php echo (int)$booking['total_duration']; ?> mins total</small>
                                    <?php else: ?>
                                        <strong><?php echo htmlspecialchars($booking['service_name']); ?></strong><br>
                                        <small><?php echo $booking['service_duration']; ?> mins</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></strong><br>
                                    <small><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></small>
                                </td>
                                <td class="therapist-info">
                                    <?php if (!empty($booking['stylist_name'])): ?>
                                        <strong style="color:#1a5c35;">&#x2713; <?php echo htmlspecialchars($booking['stylist_name']); ?></strong>
                                    <?php else: ?>
                                        <small style="color:#c9bfbc;">Not assigned</small>
                                    <?php endif; ?>
                                    <?php if ($booking['status'] === 'pending'): ?>
                                        <br>
                                        <button onclick="openAssignModal(<?php echo $booking['id']; ?>, '<?php echo addslashes($booking['stylist_name'] ?? ''); ?>')"
                                            class="btn btn-primary" style="margin-top:5px;font-size:11px;padding:3px 9px;">
                                            Assign Stylist
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong>&#x20B1;<?php echo number_format($booking['total_price'], 2); ?></strong>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $booking['status']; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?')">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <?php if ($booking['status'] === 'pending'): ?>
                                                <button type="submit" name="status" value="confirmed" class="btn btn-success">Confirm</button>
                                            <?php endif; ?>
                                            <?php if ($booking['status'] !== 'cancelled' && $booking['status'] !== 'completed'): ?>
                                                <button type="submit" name="status" value="cancelled" class="btn btn-danger">Cancel</button>
                                            <?php endif; ?>
                                            <?php if ($booking['status'] === 'confirmed'): ?>
                                                <button type="submit" name="status" value="completed" class="btn btn-success">Complete</button>
                                            <?php endif; ?>
                                        </form>
                                        <button onclick="viewBookingDetails(<?php echo htmlspecialchars(json_encode($booking)); ?>)" class="btn btn-gold">View</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div><!-- /panelBookings -->

    <!-- ── POS TAB ──────────────────────────────────────────────────────── -->
    <div id="panelPOS" class="tab-panel">

        <?php if ($posSuccess): ?>
            <div class="message success">&#x2713; <?php echo $posSuccess; ?></div>
        <?php endif; ?>
        <?php if ($posError): ?>
            <div class="message error"><?php echo htmlspecialchars($posError); ?></div>
        <?php endif; ?>

        <div class="pos-layout">

            <!-- LEFT: tiles -->
            <div class="pos-left">
                <div class="pos-tabs">
                    <div class="pos-tab active" id="tabSvc"  onclick="switchPosTab('services')">Services</div>
                    <div class="pos-tab"         id="tabProd" onclick="switchPosTab('products')">Products</div>
                </div>

                <div class="pos-search">
                    <input type="text" id="posSearchBox" placeholder="Search services or products…" oninput="renderTiles()">
                </div>
                <div class="tiles-scroll" id="tilesArea"></div>
            </div>

            <!-- RIGHT: cart -->
            <div class="pos-right">
                <div class="cart-header">
                    <h3>&#x1F6D2; Current Sale</h3>
                    <div class="cart-meta">
                        <input type="date" id="cartDate" value="<?php echo date('Y-m-d'); ?>">
                        <select id="cartStaff">
                            <option value="">— No Staff Assigned —</option>
                            <?php foreach ($staffList as $s): ?>
                                <option value="<?php echo $s['staff_id']; ?>"><?php echo htmlspecialchars($s['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="cartCustomer" placeholder="Customer name (optional)">
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
        </div><!-- /pos-layout -->

        <!-- hidden sale form -->
        <form id="saleForm" method="POST" action="?tab=pos" style="display:none;">
            <input type="hidden" name="action"         value="complete_sale">
            <input type="hidden" name="sale_date"      id="fDate">
            <input type="hidden" name="staff_id"       id="fStaff">
            <input type="hidden" name="payment_method" id="fPayment">
            <input type="hidden" name="customer_name"  id="fCustomer">
            <input type="hidden" name="cart_data"      id="fCart">
        </form>

        <!-- Recent Sales -->
        <div class="pos-recent">
            <h2>Recent Sales Transactions</h2>
            <?php if (empty($recentSales)): ?>
                <div class="message success">No sales recorded yet. Use the POS above to record walk-in sales.</div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table style="min-width:900px;">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Date &amp; Time</th>
                                <th>Customer</th>
                                <th>Staff</th>
                                <th>Services / Products</th>
                                <th>Total</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSales as $sale): ?>
                                <tr>
                                    <td>
                                        <span class="receipt-code">
                                            <?php echo !empty($sale['receipt_no']) ? htmlspecialchars($sale['receipt_no']) : ('#' . $sale['sale_id']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($sale['sale_date'])); ?><br>
                                        <small style="color:#b09e9a;"><?php echo (!empty($sale['approved_at']) && $sale['approved_at'] !== '0000-00-00 00:00:00') ? date('g:i A', strtotime($sale['approved_at'])) : 'N/A'; ?></small>
                                    </td>
                                    <td>
                                        <?php echo !empty($sale['customer_name']) ? htmlspecialchars($sale['customer_name']) : '<span style="color:#c9bfbc;font-size:.78rem;">Walk-in</span>'; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($sale['staff_name'] ?? 'N/A'); ?></td>
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

        <!-- hidden remove forms -->
        <form id="rmSvcForm" method="POST" action="" style="display:none;">
            <input type="hidden" name="action" value="remove_service">
            <input type="hidden" name="svc_id" id="rmSvcId">
        </form>
        <form id="rmProdForm" method="POST" action="" style="display:none;">
            <input type="hidden" name="action" value="remove_product">
            <input type="hidden" name="prod_id" id="rmProdId">
        </form>

    </div><!-- /panelPOS -->

</div><!-- /container -->

<!-- ══ BOOKING DETAIL MODAL ════════════════════════════════════════════════ -->
<div id="bookingModal" class="modal-overlay" onclick="if(event.target===this)closeBookingModal()">
    <div class="modal-box">
        <button class="modal-close" onclick="closeBookingModal()">&#x2715;</button>
        <h3 id="bookingModalTitle">Booking Details</h3>
        <div id="bookingModalContent"></div>
        <div style="text-align:right;margin-top:1rem;">
            <button onclick="closeBookingModal()" class="btn btn-secondary">Close</button>
        </div>
    </div>
</div>

<!-- ══ ASSIGN STYLIST MODAL ════════════════════════════════════════════════ -->
<div id="assignModal" class="modal-overlay" onclick="if(event.target===this)closeAssignModal()">
    <div class="modal-box" style="max-width:420px;">
        <button class="modal-close" onclick="closeAssignModal()">&#x2715;</button>
        <h3>Assign Stylist</h3>
        <p id="assignCurrentLabel" style="font-size:.82rem;color:#b09e9a;margin-bottom:1rem;"></p>
        <form method="POST" id="assignForm">
            <input type="hidden" name="action" value="assign_stylist">
            <input type="hidden" name="booking_id" id="assignBookingId">
            <div class="mg">
                <label>Select Stylist</label>
                <select name="staff_id" id="assignStaffSelect" required>
                    <option value="">— Choose a stylist —</option>
                    <?php foreach ($staffList as $staff): ?>
                        <option value="<?php echo $staff['staff_id']; ?>"><?php echo htmlspecialchars($staff['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:1rem;">
                <button type="button" onclick="closeAssignModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-success">Assign</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ POS: ADD SERVICE / PRODUCT MODAL ════════════════════════════════════ -->
<div class="mo" id="addModal">
    <div class="mb wide">
        <button class="mc" onclick="closeAddModal()">&#x2715;</button>
        <h4 id="addModalTitle">Add New Service</h4>
        <div class="type-tabs">
            <div class="type-tab active" id="typeTabSvc"  onclick="switchAddType('service')">Service</div>
            <div class="type-tab"        id="typeTabProd" onclick="switchAddType('product')">Product</div>
        </div>
        <div id="addSvcFields">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_service">
                <div class="mr">
                    <div class="mg"><label>Service Name *</label><input type="text" name="svc_name" placeholder="e.g., Hair Botox" required></div>
                    <div class="mg"><label>Price (&#x20B1;) *</label><input type="number" name="svc_price" step="0.01" min="0" placeholder="0.00" required></div>
                </div>
                <div class="mr">
                    <div class="mg"><label>Category</label><input type="text" name="svc_category" placeholder="e.g., Hair, Nails, Skin"></div>
                    <div class="mg"><label>Duration (minutes) *</label><input type="number" name="svc_duration" min="1" placeholder="60" value="60" required></div>
                </div>
                <div class="ma">
                    <button type="submit" class="ba">Save Service</button>
                    <button type="button" class="bc" onclick="closeAddModal()">Cancel</button>
                </div>
            </form>
        </div>
        <div id="addProdFields" style="display:none;">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_product">
                <div class="mr">
                    <div class="mg"><label>Product Name *</label><input type="text" name="prod_name" placeholder="e.g., Shampoo 250ml" required></div>
                    <div class="mg"><label>Price (&#x20B1;) *</label><input type="number" name="prod_price" step="0.01" min="0" placeholder="0.00" required></div>
                </div>
                <div class="mg"><label>Stock Quantity *</label><input type="number" name="prod_qty" min="0" placeholder="0" required></div>
                <div class="ma">
                    <button type="submit" class="ba">Save Product</button>
                    <button type="button" class="bc" onclick="closeAddModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ POS: CUSTOM SERVICE MODAL ═══════════════════════════════════════════ -->
<div class="mo" id="cmModal">
    <div class="mb">
        <button class="mc" onclick="closeCM()">&#x2715;</button>
        <h4>Custom Service (this session only)</h4>
        <div class="mg"><label>Service Name *</label><input type="text" id="cmName" placeholder="e.g., Deep Conditioning"></div>
        <div class="mg"><label>Price (&#x20B1;) *</label><input type="number" id="cmPrice" step="0.01" min="0" placeholder="0.00"></div>
        <div class="ma">
            <button class="ba" onclick="submitCM()">Add to Cart</button>
            <button class="bc" onclick="closeCM()">Cancel</button>
        </div>
    </div>
</div>

<!-- Edit sale modal removed -->

<script>
/* ══════════════════════════════════════════════════════════════════════════
   TAB NAVIGATION
   ══════════════════════════════════════════════════════════════════════════ */
function switchAdminTab(tab) {
    document.getElementById('panelBookings').classList.toggle('active', tab === 'bookings');
    document.getElementById('panelPOS').classList.toggle('active', tab === 'pos');
    document.getElementById('navBookings').classList.toggle('active', tab === 'bookings');
    document.getElementById('navPOS').classList.toggle('active', tab === 'pos');
    if (tab === 'pos') {
        // Use setTimeout to ensure panel is visible before rendering tiles
        setTimeout(function() { renderTiles(); renderCart(); }, 0);
    }
}

// Auto-open the correct tab based on URL params — run after DOM ready
document.addEventListener('DOMContentLoaded', function() {
    var urlTab = new URLSearchParams(window.location.search).get('tab');
    if (urlTab === 'pos') {
        switchAdminTab('pos');
    } else {
        // Always init tiles even on bookings tab so data is ready
        renderTiles();
        renderCart();
    }
});

/* ══════════════════════════════════════════════════════════════════════════
   BOOKING MODALS
   ══════════════════════════════════════════════════════════════════════════ */
function openAssignModal(bookingId, currentStylist) {
    document.getElementById('assignBookingId').value = bookingId;
    document.getElementById('assignCurrentLabel').innerHTML = currentStylist
        ? 'Currently assigned: <strong>' + currentStylist + '</strong>'
        : 'No stylist currently assigned.';
    document.getElementById('assignStaffSelect').value = '';
    document.getElementById('assignModal').classList.add('open');
}
function closeAssignModal() { document.getElementById('assignModal').classList.remove('open'); }

function viewBookingDetails(booking) {
    document.getElementById('bookingModalTitle').textContent = 'Booking – ' + booking.booking_reference;
    var stylist = (booking.therapist_id && booking.therapist_id !== 'tba') ? (booking.stylist_name || booking.therapist_id) : 'Not assigned';
    var svcsHtml = '';
    try {
        var svcs = booking.services_json ? JSON.parse(booking.services_json) : null;
        if (svcs && svcs.length) {
            svcsHtml = '<ul style="margin:2px 0;padding-left:16px;">';
            svcs.forEach(function(s) {
                svcsHtml += '<li>' + esc(s.name) + ' &mdash; &#x20B1;' + parseFloat(s.price).toLocaleString('en-PH',{minimumFractionDigits:2}) + ' (' + s.duration + ' mins)</li>';
            });
            svcsHtml += '</ul>';
            svcsHtml += '<small style="color:#b09e9a;">' + (booking.total_duration || 0) + ' mins total</small>';
        } else {
            svcsHtml = esc(booking.service_name) + '<br><small>' + (booking.service_duration||0) + ' mins</small>';
        }
    } catch(e) {
        svcsHtml = esc(booking.service_name);
    }
    document.getElementById('bookingModalContent').innerHTML =
        '<table class="modal-detail-table">' +
        '<tr><td>Customer</td><td><strong>' + esc(booking.full_name) + '</strong></td></tr>' +
        '<tr><td>Email</td><td>' + esc(booking.email) + '</td></tr>' +
        '<tr><td>Phone</td><td>' + esc(booking.phone) + '</td></tr>' +
        '<tr><td>Services</td><td>' + svcsHtml + '</td></tr>' +
        '<tr><td>Stylist</td><td>' + esc(stylist) + '</td></tr>' +
        '<tr><td>Total Price</td><td><strong>&#x20B1;' + parseFloat(booking.total_price).toLocaleString(undefined, {minimumFractionDigits:2}) + '</strong></td></tr>' +
        '<tr><td>Date</td><td>' + new Date(booking.booking_date).toLocaleDateString() + '</td></tr>' +
        '<tr><td>Time</td><td>' + new Date('2000-01-01 ' + booking.booking_time).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) + '</td></tr>' +
        '<tr><td>Payment</td><td>' + esc(booking.payment_method) + '</td></tr>' +
        '<tr><td>Status</td><td><span class="status-badge status-' + booking.status + '">' + booking.status.charAt(0).toUpperCase() + booking.status.slice(1) + '</span></td></tr>' +
        '<tr><td>Created</td><td>' + new Date(booking.created_at).toLocaleString() + '</td></tr>' +
        '</table>';
    document.getElementById('bookingModal').classList.add('open');
}
function closeBookingModal() { document.getElementById('bookingModal').classList.remove('open'); }

/* ══════════════════════════════════════════════════════════════════════════
   POS DATA
   ══════════════════════════════════════════════════════════════════════════ */
// Guard: ensure JSON is always a valid array even if PHP encoding fails
var _rawProducts = <?php echo (json_encode($posProducts, JSON_UNESCAPED_UNICODE) ?: '[]'); ?>;
var _rawServices = <?php echo (json_encode($posServices, JSON_UNESCAPED_UNICODE) ?: '[]'); ?>;

var PRODUCTS = Array.isArray(_rawProducts) ? _rawProducts.map(function(p) {
    return { id: p.product_id, name: p.product_name, price: parseFloat(p.price)||0, stock: parseInt(p.quantity)||0, type: 'product' };
}) : [];

var SERVICES = Array.isArray(_rawServices) ? _rawServices.map(function(s) {
    return { id: s.service_id, name: s.service_name, price: parseFloat(s.price)||0, category: s.category || '', duration: parseInt(s.duration)||60, type: 'service' };
}) : [];

console.log('[POS] Services loaded:', SERVICES.length, '| Products loaded:', PRODUCTS.length);

/* ══════════════════════════════════════════════════════════════════════════
   POS STATE & CART
   ══════════════════════════════════════════════════════════════════════════ */
var cart      = [];
var posTab    = 'services';
var delMode   = false;

function cKey(type, id) { return type + '||' + String(id); }

function addToCart(type, id, name, price) {
    var key   = cKey(type, id);
    var found = cart.find(function(c) { return c.key === key; });
    if (found) { found.qty++; }
    else { cart.push({ key: key, type: type, id: id, name: name, price: parseFloat(price), qty: 1 }); }
    renderCart(); renderTiles();
}
function removeRow(key) { cart = cart.filter(function(c) { return c.key !== key; }); renderCart(); renderTiles(); }
function changeQty(key, delta) {
    var item = cart.find(function(c) { return c.key === key; });
    if (item) { item.qty = Math.max(1, item.qty + delta); renderCart(); }
}
function clearCart() {
    cart = [];
    document.getElementById('cartCustomer').value = '';
    renderCart(); renderTiles();
}

function renderCart() {
    var body  = document.getElementById('cartBody');
    var count = document.getElementById('cartCount');
    var total = document.getElementById('cartTotal');
    var btn   = document.getElementById('btnComplete');
    if (cart.length === 0) {
        body.innerHTML = '<div class="cart-empty">No items yet.<br>Tap a service or product to add it.</div>';
        count.textContent = '0'; total.textContent = '\u20B10.00'; btn.disabled = true;
        return;
    }
    var html = '', grand = 0, totalQty = 0;
    cart.forEach(function(item) {
        var sub = item.price * item.qty; grand += sub; totalQty += item.qty;
        var sk  = item.key.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
        html += '<div class="cart-row">'
             +   '<div class="cart-row-info">'
             +     '<div class="cart-row-name">' + esc(item.name) + '</div>'
             +     '<span class="badge badge-' + item.type + '">' + item.type + '</span>'
             +     '<div class="qty-ctrl">'
             +       '<button class="qty-btn" onclick="changeQty(\'' + sk + '\',-1)">&#8722;</button>'
             +       '<span class="qty-val">' + item.qty + '</span>'
             +       '<button class="qty-btn" onclick="changeQty(\'' + sk + '\',1)">+</button>'
             +       '<span style="font-size:11px;color:#b09e9a;margin-left:4px;">&times; &#x20B1;' + item.price.toFixed(2) + '</span>'
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

function renderTiles() {
    var area   = document.getElementById('tilesArea');
    if (!area) return;
    var q      = (document.getElementById('posSearchBox').value || '').toLowerCase().trim();
    var inCart = {};
    cart.forEach(function(c) { inCart[c.key] = true; });
    var html   = '';

    if (posTab === 'services') {
        if (SERVICES.length === 0) {
            html += '<div class="no-svc-note">No services found in the database. Please add services via the salon management system.</div>';
        }
        var list = SERVICES.filter(function(s) { return !q || s.name.toLowerCase().indexOf(q) >= 0; });
        if (list.length > 0) {
            html += '<div class="tile-label">Salon Services</div><div class="tiles-grid">';
            list.forEach(function(s) {
                var key   = cKey('service', s.id);
                var ic    = inCart[key] ? ' in-cart' : '';
                var dm    = delMode ? ' del-mode' : '';
                var click = delMode ? '' : 'addToCart(\'service\',' + s.id + ',\'' + escA(s.name) + '\',' + s.price + ')';
                var delBtn = delMode ? '<span class="del-x" onclick="confirmRemoveSvc(' + s.id + ',\'' + escA(s.name) + '\',event)">&#x2715;</span>' : '';
                html += '<div class="tile svc-tile' + ic + dm + '" onclick="' + click + '">'
                      + delBtn
                      + '<div class="tile-name">' + esc(s.name) + '</div>'
                      + '<div class="tile-price">&#x20B1;' + s.price.toFixed(2) + '</div>'
                      + '</div>';
            });
            html += '</div>';
        }
        if (!q) {
            html += '<div class="tiles-grid"><div class="tile custom-tile" onclick="openCM()">'
                  + '<div style="font-size:22px;margin-bottom:4px;">+</div>'
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
            var dm    = delMode ? ' del-mode' : '';
            var click = delMode ? '' : 'addToCart(\'product\',' + p.id + ',\'' + escA(p.name) + '\',' + p.price + ')';
            var delBtn = delMode ? '<span class="del-x" onclick="confirmRemoveProd(' + p.id + ',\'' + escA(p.name) + '\',event)">&#x2715;</span>' : '';
            html += '<div class="tile' + ic + dm + '" onclick="' + click + '">'
                  + delBtn
                  + '<div class="tile-name">' + esc(p.name) + '</div>'
                  + '<div class="tile-price">&#x20B1;' + p.price.toFixed(2) + '</div>'
                  + '<div style="font-size:10px;color:#b09e9a;margin-top:2px;">Stock: ' + p.stock + '</div>'
                  + '</div>';
        });
        if (!plist.length) html += '<div style="color:#b09e9a;font-size:13px;grid-column:1/-1;padding:14px 0;">No products found.</div>';
        html += '</div>';
    }
    area.innerHTML = html;
}

function switchPosTab(tab) {
    posTab = tab;
    document.getElementById('tabSvc').classList.toggle('active',  tab === 'services');
    document.getElementById('tabProd').classList.toggle('active', tab === 'products');
    document.getElementById('posSearchBox').value = '';
    renderTiles();
}

/* ── Delete mode ── */
function toggleDelMode() {
    delMode = !delMode;
    document.getElementById('btnDelMode').classList.toggle('active', delMode);
    document.getElementById('delHint').classList.toggle('visible', delMode);
    renderTiles();
}
function confirmRemoveSvc(id, name, event) {
    event.stopPropagation();
    if (!confirm('Permanently delete service "' + name + '"?')) return;
    document.getElementById('rmSvcId').value = id;
    document.getElementById('rmSvcForm').submit();
}
function confirmRemoveProd(id, name, event) {
    event.stopPropagation();
    if (!confirm('Permanently delete product "' + name + '"?')) return;
    document.getElementById('rmProdId').value = id;
    document.getElementById('rmProdForm').submit();
}

/* ── Add modal ── */
var addModalType = 'service';
function openAddModal() {
    switchAddType(posTab === 'products' ? 'product' : 'service');
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
    if (e.target === this) closeAddModal();
});

/* ── Custom service modal ── */
function openCM() {
    document.getElementById('cmName').value = '';
    document.getElementById('cmPrice').value = '';
    document.getElementById('cmModal').classList.add('open');
    setTimeout(function() { document.getElementById('cmName').focus(); }, 80);
}
function closeCM() { document.getElementById('cmModal').classList.remove('open'); }
function submitCM() {
    var name  = document.getElementById('cmName').value.trim();
    var price = parseFloat(document.getElementById('cmPrice').value);
    if (!name)                     { alert('Please enter a service name.'); return; }
    if (isNaN(price) || price < 0) { alert('Please enter a valid price.'); return; }
    addToCart('service', 'custom_' + Date.now(), name, price);
    closeCM();
}

/* ── Complete sale ── */
function completeSale() {
    if (!cart.length) return;
    document.getElementById('fDate').value     = document.getElementById('cartDate').value;
    document.getElementById('fStaff').value    = document.getElementById('cartStaff').value;
    document.getElementById('fPayment').value  = document.getElementById('cartPayment').value;
    document.getElementById('fCustomer').value = document.getElementById('cartCustomer').value;
    document.getElementById('fCart').value     = JSON.stringify(cart.map(function(c) {
        return { type: c.type, id: c.id, name: c.name, price: c.price, qty: c.qty };
    }));
    document.getElementById('saleForm').submit();
}

s

/* ── Helpers ── */
function esc(s)  { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escA(s) { return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }

/* ── Init handled by DOMContentLoaded above ── */
</script>
</body>
</html>

<?php
$conn->close();
?>