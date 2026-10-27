<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// Initialize variables
$success = false;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input data
    $name = isset($_POST['fullName']) ? trim($_POST['fullName']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';

    // Basic validation
    if (empty($name) || empty($email) || empty($message)) {
        $error_message = 'All fields are required. Please fill in all the information.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Attempt to send email
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
            $mail->setFrom('serenityspamassage25@gmail.com', 'Contact Form');
            $mail->addAddress('serenityspamassage25@gmail.com', 'Serenity Spa (Foot & Body Massage)');
            $mail->addReplyTo($email, $name);

            // Content - Simple HTML format
            $mail->isHTML(true);
            $mail->Subject = 'New Contact Form Message from ' . $name;
            
            // Simple, clean HTML email
            $htmlBody = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2>New Contact Form Submission</h2>
                
                <p><strong>Name:</strong> ' . htmlspecialchars($name) . '</p>
                <p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
                
                <p><strong>Message:</strong></p>
                <p style="background-color: #f5f5f5; padding: 15px; border-left: 3px solid #007cba;">' . nl2br(htmlspecialchars($message)) . '</p>
                
                <hr style="margin: 20px 0; border: 1px solid #ddd;">
                
                <p style="color: #666; font-size: 14px;">
                    Sent on: ' . date('F j, Y') . '<br>
                    From: Serenity Spa website contact form
                </p>
            </div>';
            
            $mail->Body = $htmlBody;
            
            // Plain text version
            $mail->AltBody = "New Contact Form Submission\n\n" .
                           "Name: " . $name . "\n" .
                           "Email: " . $email . "\n\n" .
                           "Message:\n" . $message . "\n\n" .
                           "Sent on: " . date('F j, Y') . "\n" .
                           "From: Serenity Spa website contact form";

            if($mail->send()) {
                $success = true;
            }

        } catch (Exception $e) {
            $error_message = "Sorry, there was an error sending your message. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $success ? 'Message Sent - ' : 'Contact - '; ?>Serenity Spa</title>
    <link rel="icon" href="images/original-logo.jpg">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 40px 20px;
            color: #333;
        }

        .container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
            text-align: center;
        }

        .message {
            margin-bottom: 30px;
            line-height: 1.5;
            text-align: center;
        }

        .success {
            color: #28a745;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 4px;
        }

        .error {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 4px;
        }

        .actions {
            text-align: center;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            background-color: #007cba;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #005a8a;
        }

        .btn-secondary {
            background-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #545b62;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
            <h1>Thank You!</h1>
            <div class="message success">
                <p>Your message has been sent successfully.</p>
                <p>We'll get back to you within 24 hours.</p>
            </div>
        <?php else: ?>
            <h1>Message Not Sent</h1>
            <div class="message error">
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="actions">
            <a href="index.html" class="btn">Back to Website</a>
            <?php if (!$success): ?>
                <a href="javascript:history.back()" class="btn btn-secondary">Try Again</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>