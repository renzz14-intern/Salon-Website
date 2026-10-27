<?php
/**
 * cancel_booking.php
 * Handles user-initiated appointment cancellations.
 * Only allows cancelling bookings that belong to the logged-in user
 * and are in 'pending' or 'confirmed' status.
 */

require_once 'config.php';
session_start();

header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorised. Please log in.']);
    exit();
}

// Must be a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$user_id    = (int) $_SESSION['user_id'];
$booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;

if ($booking_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID.']);
    exit();
}

try {
    // Fetch the booking — verify it belongs to this user and is cancellable
    $stmt = $conn->prepare("
        SELECT id, booking_reference, status, booking_date, booking_time, full_name, email,
               service_name, total_price, services_json
        FROM   bookings
        WHERE  id = ? AND user_id = ?
        LIMIT  1
    ");
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found or does not belong to your account.']);
        exit();
    }

    $cancellableStatuses = ['pending'];
    if (!in_array($booking['status'], $cancellableStatuses)) {
        echo json_encode([
            'success' => false,
            'message' => 'This appointment cannot be cancelled (status: ' . ucfirst($booking['status']) . ').'
        ]);
        exit();
    }

    // Update status to cancelled
    $upd = $conn->prepare("
        UPDATE bookings
        SET    status = 'cancelled', updated_at = NOW()
        WHERE  id = ? AND user_id = ?
    ");
    $upd->bind_param("ii", $booking_id, $user_id);
    $upd->execute();

    if ($upd->affected_rows === 0) {
        $upd->close();
        echo json_encode(['success' => false, 'message' => 'Could not cancel the booking. Please try again.']);
        exit();
    }
    $upd->close();

    // ── Optional: send cancellation email via PHPMailer (same pattern as admin) ──
    $emailNote = '';
    $vendorPath = __DIR__ . '/vendor/autoload.php';
    if (file_exists($vendorPath)) {
        require_once $vendorPath;
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'serenityspamassage25@gmail.com';
                $mail->Password   = 'axsu ktfl xpig rcgd';
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom('serenityspamassage25@gmail.com', 'Levy Branzuela Salon');
                $mail->addAddress($booking['email'], $booking['full_name']);
                $mail->isHTML(true);
                $mail->Subject = 'Booking Cancelled - ' . $booking['booking_reference'] . ' - Levy Branzuela Salon';

                $name    = htmlspecialchars($booking['full_name']);
                $ref     = htmlspecialchars($booking['booking_reference']);
                $date    = date('l, F j, Y', strtotime($booking['booking_date']));
                $time    = date('g:i A',     strtotime($booking['booking_time']));

                // Build service rows
                $svcsData = !empty($booking['services_json']) ? json_decode($booking['services_json'], true) : null;
                $svcRowsHtml = '';
                $svcRowsTxt  = '';
                if ($svcsData && is_array($svcsData)) {
                    foreach ($svcsData as $s) {
                        $svcRowsHtml .= "<tr>
                            <td style='padding:7px 0;border-bottom:1px solid #f0f0f0;color:#444;'>" . htmlspecialchars($s['name']) . "</td>
                            <td style='padding:7px 0;border-bottom:1px solid #f0f0f0;text-align:right;'>&#8369;" . number_format($s['price'], 2) . "</td>
                        </tr>";
                        $svcRowsTxt .= "  - " . $s['name'] . " — ₱" . number_format($s['price'], 2) . "\n";
                    }
                } else {
                    $svcRowsHtml = "<tr>
                        <td style='padding:7px 0;border-bottom:1px solid #f0f0f0;color:#444;'>" . htmlspecialchars($booking['service_name']) . "</td>
                        <td style='padding:7px 0;border-bottom:1px solid #f0f0f0;text-align:right;'>&#8369;" . number_format($booking['total_price'], 2) . "</td>
                    </tr>";
                    $svcRowsTxt = "  - " . $booking['service_name'] . " — ₱" . number_format($booking['total_price'], 2) . "\n";
                }

                $total = number_format($booking['total_price'], 2);

                $mail->Body = "
                <!DOCTYPE html>
                <html><head><meta charset='UTF-8'></head>
                <body style='font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:20px;background:#f5f5f5;'>
                <div style='max-width:600px;margin:0 auto;background:white;border-radius:8px;overflow:hidden;'>
                    <div style='background:#f19e99;padding:30px 25px;'>
                        <h1 style='color:white;margin:0;font-size:22px;'>Levy Branzuela Salon</h1>
                        <h2 style='color:white;margin:8px 0 0;font-size:16px;font-weight:normal;'>Booking Cancelled ❌</h2>
                    </div>
                    <div style='padding:30px 25px;'>
                        <p>Dear {$name},</p>
                        <p>Your appointment has been <strong>cancelled</strong> as requested.</p>

                        <div style='background:#fff8f7;border-left:4px solid #f19e99;padding:15px;margin:0 0 20px;border-radius:0 6px 6px 0;'>
                            <strong style='color:#c0615d;'>Booking Reference:</strong>
                            <span style='font-size:1.1em;font-weight:bold;color:#333;margin-left:8px;'>{$ref}</span>
                        </div>

                        <h3 style='color:#f19e99;border-bottom:2px solid #f19e99;padding-bottom:6px;font-size:15px;'>Cancelled Appointment Details</h3>
                        <table style='width:100%;border-collapse:collapse;margin-bottom:20px;'>
                            <tr style='background:#fef5f5;'>
                                <th style='padding:8px 0;text-align:left;color:#666;font-weight:normal;'>Service</th>
                                <th style='padding:8px 0;text-align:right;color:#666;font-weight:normal;'>Price</th>
                            </tr>
                            {$svcRowsHtml}
                            <tr style='background:#fef5f5;font-weight:bold;'>
                                <td style='padding:10px 0;'>Total</td>
                                <td style='padding:10px 0;text-align:right;'>&#8369;{$total}</td>
                            </tr>
                        </table>
                        <table style='width:100%;border-collapse:collapse;margin-bottom:20px;'>
                            <tr><td style='padding:8px 0;border-bottom:1px solid #eee;color:#666;'>Date:</td>
                                <td style='padding:8px 0;border-bottom:1px solid #eee;text-align:right;'>{$date}</td></tr>
                            <tr><td style='padding:8px 0;color:#666;'>Time:</td>
                                <td style='padding:8px 0;text-align:right;'>{$time}</td></tr>
                        </table>

                        <p style='margin:0 0 10px;'>We hope to serve you again in the future! You can book a new appointment anytime through our website.</p>
                        <p>If you have any questions, please contact us at <a href='mailto:serenityspamassage25@gmail.com'>serenityspamassage25@gmail.com</a>.</p>

                        <p style='margin-top:20px;'>Warm regards,<br><strong>The Levy Branzuela Salon Team</strong></p>
                    </div>
                    <div style='background:#fef5f5;padding:15px 25px;text-align:center;font-size:12px;color:#888;'>
                        Levy Branzuela Salon &nbsp;|&nbsp; serenityspamassage25@gmail.com
                    </div>
                </div>
                </body></html>";

                $mail->AltBody =
                    "LEVY BRANZUELA SALON — BOOKING CANCELLED\n" .
                    "==========================================\n\n" .
                    "Dear {$booking['full_name']},\n\n" .
                    "Your appointment has been cancelled as requested.\n\n" .
                    "Booking Reference: {$booking['booking_reference']}\n\n" .
                    "SERVICES:\n{$svcRowsTxt}\n" .
                    "Total : ₱{$total}\n" .
                    "Date  : {$date}\n" .
                    "Time  : {$time}\n\n" .
                    "We hope to see you again soon!\n\n" .
                    "Contact: serenityspamassage25@gmail.com\n\n" .
                    "Levy Branzuela Salon";

                $mail->send();
                $emailNote = ' A cancellation confirmation has been sent to your email.';
            } catch (\Exception $mailErr) {
                error_log("User cancel email error (booking #{$booking_id}): " . $mailErr->getMessage());
                // Non-fatal — cancellation still succeeded
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Your appointment has been successfully cancelled.' . $emailNote
    ]);

} catch (\Exception $e) {
    error_log("cancel_booking.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
}