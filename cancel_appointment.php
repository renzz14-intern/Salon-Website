<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!file_exists('config.php')) {
    echo json_encode(['success' => false, 'message' => 'Configuration file not found']);
    exit();
}

require_once 'config.php';
require 'vendor/autoload.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);
$booking_id = isset($data['booking_id']) ? intval($data['booking_id']) : 0;

if ($booking_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit();
}

try {
    // Fetch booking details and verify ownership
    $stmt = $conn->prepare("
        SELECT 
            b.id,
            b.booking_reference,
            b.service_name,
            b.booking_date,
            b.booking_time,
            b.room_type,
            b.total_price,
            b.status,
            u.first_name,
            u.last_name,
            u.email,
            u.phone
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ? AND b.user_id = ?
    ");
    
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit();
    }
    
    $booking = $result->fetch_assoc();
    
    // Check if booking can be cancelled
    if (!in_array($booking['status'], ['pending', 'confirmed'])) {
        echo json_encode(['success' => false, 'message' => 'This appointment cannot be cancelled']);
        exit();
    }
    
    // Mark the booking as "cancellation requested"
    $updateStmt = $conn->prepare("
        UPDATE bookings 
        SET cancellation_requested = 1, 
            cancellation_requested_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $updateStmt->bind_param("i", $booking_id);
    $updateStmt->execute();
    
    // Prepare email using PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = "serenityspamassage25@gmail.com";
        $mail->Password = "axsu ktfl xpig rcgd";
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('serenityspamassage25@gmail.com', 'Serenity Spa Booking System');
        $mail->addAddress('serenityspamassage25@gmail.com', 'Serenity Spa Admin');
        $mail->addReplyTo($booking['email'], $booking['first_name'] . ' ' . $booking['last_name']);

        // Format date and time
        $formatted_date = date('F j, Y', strtotime($booking['booking_date']));
        $formatted_time = date('g:i A', strtotime($booking['booking_time']));
        
        // Get room display name
        $room_names = [
            'single' => 'Single Room',
            'couples' => 'Couples Room',
            'group' => 'Group Room',
            'vip' => 'VIP Suite'
        ];
        $room_display = $room_names[$booking['room_type']] ?? 'Standard Room';
        
        // Email subject
        $mail->Subject = '🔔 Cancellation Request - ' . $booking['booking_reference'];
        
        // Content
        $mail->isHTML(true);
        
        // Create HTML email body
        $htmlBody = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                .detail-row { padding: 10px; border-bottom: 1px solid #ddd; }
                .detail-label { font-weight: bold; color: #555; }
                .detail-value { color: #333; }
                .alert { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; color: #856404; }
                .urgent { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; font-weight: bold; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                .action-box { text-align: center; margin-top: 20px; padding: 20px; background: #e7f3ff; border-radius: 8px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🔔 NEW CANCELLATION REQUEST</h2>
                </div>
                <div class='content'>
                    <div class='alert urgent'>
                        <strong>⚠️ ACTION REQUIRED:</strong> A customer has requested to cancel their appointment. Please review and process this request in the admin panel.
                    </div>
                    
                    <h3>Customer Information:</h3>
                    <div class='detail-row'>
                        <span class='detail-label'>Name:</span>
                        <span class='detail-value'>{$booking['first_name']} {$booking['last_name']}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Email:</span>
                        <span class='detail-value'>{$booking['email']}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Phone:</span>
                        <span class='detail-value'>{$booking['phone']}</span>
                    </div>
                    
                    <h3>Appointment Details:</h3>
                    <div class='detail-row'>
                        <span class='detail-label'>Reference Number:</span>
                        <span class='detail-value'>{$booking['booking_reference']}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Service:</span>
                        <span class='detail-value'>{$booking['service_name']}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Date & Time:</span>
                        <span class='detail-value'>{$formatted_date} at {$formatted_time}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Room Type:</span>
                        <span class='detail-value'>{$room_display}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Total Amount:</span>
                        <span class='detail-value'>₱" . number_format($booking['total_price'], 2) . "</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Current Status:</span>
                        <span class='detail-value'>" . ucfirst($booking['status']) . "</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Request Date:</span>
                        <span class='detail-value'>" . date('F j, Y g:i A') . "</span>
                    </div>
                    
                    <div class='action-box'>
                        <p style='margin: 0 0 15px 0; font-size: 16px;'><strong>Please log in to the admin panel to approve or reject this cancellation request.</strong></p>
                        <p style='margin: 0; color: #666; font-size: 14px;'>The appointment is still active until you process this request.</p>
                    </div>
                </div>
                <div class='footer'>
                    <p>This is an automated notification from Serenity Spa booking system.</p>
                    <p><strong>Important:</strong> The booking has NOT been cancelled yet. Please review the request in your admin panel.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->Body = $htmlBody;
        
        // Plain text version
        $mail->AltBody = "NEW CANCELLATION REQUEST\n\n" .
                       "⚠️ ACTION REQUIRED: A customer has requested to cancel their appointment.\n\n" .
                       "Customer Information:\n" .
                       "Name: {$booking['first_name']} {$booking['last_name']}\n" .
                       "Email: {$booking['email']}\n" .
                       "Phone: {$booking['phone']}\n\n" .
                       "Appointment Details:\n" .
                       "Reference: {$booking['booking_reference']}\n" .
                       "Service: {$booking['service_name']}\n" .
                       "Date & Time: {$formatted_date} at {$formatted_time}\n" .
                       "Room: {$room_display}\n" .
                       "Amount: ₱" . number_format($booking['total_price'], 2) . "\n" .
                       "Status: " . ucfirst($booking['status']) . "\n" .
                       "Request Date: " . date('F j, Y g:i A') . "\n\n" .
                       "Please log in to the admin panel to process this request.\n" .
                       "The booking has NOT been cancelled yet.";

        $mail->send();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Cancellation request submitted successfully. The spa will review your request shortly.'
        ]);
        
    } catch (Exception $e) {
        error_log("Failed to send cancellation email: " . $mail->ErrorInfo);
        // Still return success since the database was updated
        echo json_encode([
            'success' => true, 
            'message' => 'Cancellation request submitted successfully. The spa will review your request shortly.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Cancellation request error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while processing your request'
    ]);
}
?>