<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'vendor/autoload.php';

ob_start();
header('Content-Type: application/json');

set_error_handler(function($no, $str, $file, $line) {
    error_log("PHP Error [$no]: $str in $file on line $line");
    return true;
});

try {
    require_once 'config.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Only POST allowed');

    $raw = file_get_contents('php://input');
    if (empty($raw)) throw new Exception('No data received');

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Invalid JSON: ' . json_last_error_msg());

    // ── Validate required fields ──────────────────────────────────────────────
    $required = ['fullName','email','phone','services','bookingDate','bookingTime','paymentMethod'];
    foreach ($required as $f) {
        if (empty($data[$f])) throw new Exception("Missing required field: $f");
    }
    if (!is_array($data['services']) || count($data['services']) === 0) {
        throw new Exception('At least one service must be selected');
    }

    // ── Sanitize scalars ──────────────────────────────────────────────────────
    $fullName      = mysqli_real_escape_string($conn, trim($data['fullName']));
    $email         = mysqli_real_escape_string($conn, trim($data['email']));
    $phone         = mysqli_real_escape_string($conn, trim($data['phone']));
    $bookingDate   = mysqli_real_escape_string($conn, trim($data['bookingDate']));
    $bookingTime   = trim($data['bookingTime24'] ?? $data['bookingTime']);
    $paymentMethod = mysqli_real_escape_string($conn, trim($data['paymentMethod']));
    $userId        = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

    // ── Process services array ────────────────────────────────────────────────
    $services      = $data['services'];
    $totalPrice    = 0;
    $totalDuration = 0;
    $serviceNames  = [];

    foreach ($services as &$svc) {
        $svc['price']    = floatval($svc['price']);
        $svc['duration'] = intval($svc['duration']);
        $svc['name']     = htmlspecialchars(strip_tags($svc['name']), ENT_QUOTES, 'UTF-8');
        $totalPrice    += $svc['price'];
        $totalDuration += $svc['duration'];
        $serviceNames[]  = $svc['name'];
    }
    unset($svc);

    $serviceNameSummary = implode(', ', $serviceNames);
    $servicesJson       = mysqli_real_escape_string($conn, json_encode($services));

    // ── Normalise booking time to HH:MM:SS ───────────────────────────────────
    $timeFormatted = convertTimeFormat($bookingTime);

    // ── CHECK FOR SLOT CONFLICT ───────────────────────────────────────────────
    // Reject if another pending or confirmed booking already exists at this date+time
    $conflictCheck = $conn->prepare(
        "SELECT id FROM bookings
         WHERE  booking_date = ?
           AND  booking_time = ?
           AND  status IN ('pending', 'confirmed')
         LIMIT  1"
    );
    $conflictCheck->bind_param('ss', $bookingDate, $timeFormatted);
    $conflictCheck->execute();
    $conflictCheck->store_result();

    if ($conflictCheck->num_rows > 0) {
        $conflictCheck->close();
        ob_end_clean();
        http_response_code(409);
        echo json_encode([
            'success'  => false,
            'conflict' => true,
            'message'  => 'Sorry, that time slot has just been booked by someone else. '
                        . 'Please go back and select a different date or time.'
        ]);
        exit;
    }
    $conflictCheck->close();

    // ── Ensure bookings table has required columns ────────────────────────────
    $conn->query("ALTER TABLE bookings
        ADD COLUMN IF NOT EXISTS services_json  TEXT NOT NULL DEFAULT '',
        ADD COLUMN IF NOT EXISTS total_duration INT  NOT NULL DEFAULT 0");

    // ── Generate unique booking reference ─────────────────────────────────────
    do {
        $ref = 'LBS' . date('Y') . rand(1000, 9999);
        $chk = $conn->query("SELECT id FROM bookings WHERE booking_reference = '$ref'");
    } while ($chk && $chk->num_rows > 0);

    // ── Insert booking ────────────────────────────────────────────────────────
    $userIdSQL = $userId !== null ? $userId : 'NULL';
    $sql = "INSERT INTO bookings
                (booking_reference, user_id, full_name, email, phone,
                 service_name, services_json, service_price, service_duration,
                 total_duration, booking_date, booking_time,
                 room_type, room_price, total_price,
                 therapist_id, payment_method, status)
            VALUES
                ('$ref', $userIdSQL, '$fullName', '$email', '$phone',
                 '$serviceNameSummary', '$servicesJson', $totalPrice, $totalDuration,
                 $totalDuration, '$bookingDate', '$timeFormatted',
                 'salon', 0, $totalPrice,
                 'tba', '$paymentMethod', 'pending')";

    if (!$conn->query($sql)) throw new Exception('Booking insert failed: ' . $conn->error);
    $bookingId = $conn->insert_id;

    // ── Send confirmation email ───────────────────────────────────────────────
    $emailSent  = false;
    $emailError = '';
    try {
        $emailSent = sendBookingEmail($email, $fullName, $ref, $services,
                                      $totalPrice, $totalDuration, $bookingDate,
                                      $data['bookingTime'], $paymentMethod);
        if ($emailSent) {
            $conn->query("UPDATE bookings SET email_sent = 1 WHERE id = $bookingId");
        }
    } catch (Exception $e) {
        $emailError = $e->getMessage();
        error_log("Email failed: $emailError");
    }

    ob_end_clean();
    echo json_encode([
        'success'          => true,
        'message'          => 'Booking created successfully',
        'bookingReference' => $ref,
        'bookingId'        => $bookingId,
        'emailSent'        => $emailSent
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(400);
    error_log("Booking Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'conflict' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function convertTimeFormat($time) {
    $time = trim($time);
    // Already HH:MM or HH:MM:SS
    if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
        return strlen($time) === 5 ? $time . ':00' : $time;
    }
    // 12h format
    if (preg_match('/^(\d{1,2}):?(\d{0,2})\s*(AM|PM)$/i', $time, $m)) {
        $h   = intval($m[1]);
        $min = $m[2] !== '' ? intval($m[2]) : 0;
        $per = strtoupper($m[3]);
        if ($per === 'AM' && $h === 12) $h = 0;
        elseif ($per === 'PM' && $h !== 12) $h += 12;
        return sprintf('%02d:%02d:00', $h, $min);
    }
    return $time;
}

function sendBookingEmail($toEmail, $toName, $ref, $services, $totalPrice,
                          $totalDuration, $bookingDate, $bookingTimeDisplay, $paymentMethod) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'serenityspamassage25@gmail.com';
        $mail->Password   = 'axsu ktfl xpig rcgd';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('serenityspamassage25@gmail.com', 'Levy Branzuela Salon');
        $mail->addAddress($toEmail, $toName);

        $formattedDate = (new DateTime($bookingDate))->format('l, F j, Y');

        $serviceRowsHTML = '';
        $serviceRowsTXT  = '';
        foreach ($services as $s) {
            $serviceRowsHTML .= "<tr>
                <td style='padding:8px 0;border-bottom:1px solid #f0f0f0;color:#444;'>" . htmlspecialchars($s['name']) . "</td>
                <td style='padding:8px 0;border-bottom:1px solid #f0f0f0;text-align:right;'>" . $s['duration'] . " mins</td>
                <td style='padding:8px 0;border-bottom:1px solid #f0f0f0;text-align:right;'>&#8369;" . number_format($s['price'], 2) . "</td>
            </tr>";
            $serviceRowsTXT .= "  - " . $s['name'] . " (" . $s['duration'] . " mins) — ₱" . number_format($s['price'], 2) . "\n";
        }

        $mail->isHTML(true);
        $mail->Subject = "Booking Received - $ref - Levy Branzuela Salon";

        $mail->Body = "
        <!DOCTYPE html>
        <html><head><meta charset='UTF-8'></head>
        <body style='font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:20px;background:#f5f5f5;'>
        <div style='max-width:600px;margin:0 auto;background:white;border-radius:8px;overflow:hidden;'>
            <div style='background:#f19e99;padding:30px 25px;'>
                <h1 style='color:white;margin:0;font-size:22px;'>Levy Branzuela Salon</h1>
                <h2 style='color:white;margin:8px 0 0;font-size:16px;font-weight:normal;'>Booking Received — Pending Confirmation</h2>
            </div>
            <div style='padding:30px 25px;'>
                <p style='margin:0 0 15px;'>Dear $toName,</p>
                <p style='margin:0 0 20px;'>Thank you for choosing Levy Branzuela Salon! Your booking request has been received and is pending confirmation.</p>

                <div style='background:#fff8f7;border-left:4px solid #f19e99;padding:15px;margin:0 0 25px;border-radius:0 6px 6px 0;'>
                    <strong style='color:#c0615d;'>Booking Reference:</strong>
                    <span style='font-size:1.2em;font-weight:bold;color:#333;margin-left:8px;'>$ref</span>
                </div>

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
                        <td style='padding:10px 0;text-align:right;'>&#8369;" . number_format($totalPrice, 2) . "</td>
                    </tr>
                </table>

                <table style='width:100%;border-collapse:collapse;margin-bottom:25px;'>
                    <tr><td style='padding:8px 0;border-bottom:1px solid #eee;color:#666;'>Date:</td>
                        <td style='padding:8px 0;border-bottom:1px solid #eee;text-align:right;'>$formattedDate</td></tr>
                    <tr><td style='padding:8px 0;border-bottom:1px solid #eee;color:#666;'>Time:</td>
                        <td style='padding:8px 0;border-bottom:1px solid #eee;text-align:right;'>$bookingTimeDisplay</td></tr>
                    <tr><td style='padding:8px 0;border-bottom:1px solid #eee;color:#666;'>Stylist:</td>
                        <td style='padding:8px 0;border-bottom:1px solid #eee;text-align:right;'>Will be assigned by our team</td></tr>
                    <tr><td style='padding:8px 0;color:#666;'>Payment:</td>
                        <td style='padding:8px 0;text-align:right;'>Pay at Salon on the day of your appointment</td></tr>
                </table>

                <h3 style='color:#f19e99;border-bottom:2px solid #f19e99;padding-bottom:6px;font-size:15px;'>Important Reminders</h3>
                <ul style='padding-left:20px;margin:10px 0 25px;color:#555;'>
                    <li style='margin-bottom:6px;'>Please arrive <strong>10 minutes early</strong></li>
                    <li style='margin-bottom:6px;'>Bring a valid ID for verification</li>
                    <li style='margin-bottom:6px;'>For changes or cancellations, contact us at least 24 hours in advance</li>
                    <li>Our cancellation policy applies to no-shows and same-day cancellations</li>
                </ul>

                <p>We look forward to serving you!</p>
                <p style='margin-top:20px;'>Warm regards,<br><strong>The Levy Branzuela Salon Team</strong></p>
            </div>
            <div style='background:#fef5f5;padding:15px 25px;text-align:center;font-size:12px;color:#888;'>
                Levy Branzuela Salon &nbsp;|&nbsp; (02) 8829-9411 &nbsp;
            </div>
        </div>
        </body></html>";

        $mail->AltBody =
            "LEVY BRANZUELA SALON — BOOKING RECEIVED\n" .
            "==========================================\n\n" .
            "Dear $toName,\n\n" .
            "Your booking reference: $ref\n\n" .
            "SERVICES:\n$serviceRowsTXT\n" .
            "Total Duration : $totalDuration mins\n" .
            "Total Price    : ₱" . number_format($totalPrice, 2) . "\n\n" .
            "Date    : $formattedDate\n" .
            "Time    : $bookingTimeDisplay\n" .
            "Stylist : Will be assigned by our team\n" .
            "Payment : Pay at Salon\n\n" .
            "Please arrive 10 minutes early.\n\n" .
            "Contact: (02) 8829-9411\n\n" .
            "Levy Branzuela Salon";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email Error: {$mail->ErrorInfo}");
        throw new Exception("Email failed: {$mail->ErrorInfo}");
    }
}
?>