<?php
require_once 'config.php';
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userData   = null;

if ($isLoggedIn) {
    $stmt = $conn->prepare("SELECT name, email, phone FROM customer_accounts WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result   = $stmt->get_result();
    $userData = $result->fetch_assoc();
}

// ── Load ALL active services grouped by category ──────────────────────────────
$grouped_services = [];

try {
    $svcResult = $conn->query(
        "SELECT service_id AS id, service_name AS name, category,
                price, duration
         FROM   salon_services
         WHERE  status = 'active'
         ORDER  BY category, service_name"
    );
    if ($svcResult) {
        while ($row = $svcResult->fetch_assoc()) {
            $grouped_services[$row['category']][] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Service load error: " . $e->getMessage());
}

// Default services if DB is empty
if (empty($grouped_services)) {
    $grouped_services = [
        'Hair Services' => [
            ['id'=>1,'name'=>'Hair & Make-up',     'price'=>1500.00,'duration'=>90],
            ['id'=>2,'name'=>'Hair Coloring',       'price'=>2500.00,'duration'=>120],
            ['id'=>3,'name'=>'Hair Cutting',        'price'=>800.00, 'duration'=>60],
            ['id'=>4,'name'=>'Hair Spa Treatments', 'price'=>500.00, 'duration'=>60],
        ],
        'Nails' => [
            ['id'=>5,'name'=>'Manicure/Pedicure',  'price'=>350.00,'duration'=>60],
            ['id'=>6,'name'=>'Nail Design',         'price'=>350.00,'duration'=>60],
        ],
        'Eyebrows' => [
            ['id'=>7,'name'=>'Micro Blading',       'price'=>500.00,'duration'=>60],
            ['id'=>8,'name'=>'Shading',             'price'=>350.00,'duration'=>60],
            ['id'=>9,'name'=>'Eye Lash Extensions', 'price'=>650.00,'duration'=>60],
        ],
        'Skin' => [
            ['id'=>10,'name'=>'Waxing',             'price'=>250.00,'duration'=>60],
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Your Appointment - Levy Branzuela Salon</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="images/Untitled.png">
    <link rel="stylesheet" href="booking_style.css">
    <style>
        /* ── Category headings ── */
        .category-heading {
            font-size: 1.05rem;
            font-weight: 600;
            color: #f19e99;
            margin: 1.5rem 0 0.75rem;
            padding-bottom: 0.4rem;
            border-bottom: 2px solid #f19e99;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .category-heading:first-child { margin-top: 0; }

        /* ── Checkbox tiles ── */
        .service-item input[type="checkbox"] { display: none; }
        .service-item input[type="checkbox"]:checked + label {
            border-color: #f19e99;
            background: linear-gradient(135deg,rgba(241,158,153,.12) 0%,rgba(241,158,153,.05) 100%);
            box-shadow: 0 6px 20px rgba(241,158,153,.3);
        }
        .service-item input[type="checkbox"]:checked + label::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 10px; right: 12px;
            color: #f19e99;
            font-size: 1rem;
        }
        .service-item label { position: relative; }

        /* ── Booked / unavailable time slots ── */
        .time-slot.booked {
            background: #f0f0f0 !important;
            color: #bbb !important;
            cursor: not-allowed !important;
            border-color: #e0e0e0 !important;
            position: relative;
        }
        .time-slot.booked::after {
            content: 'Unavailable';
            display: block;
            font-size: 9px;
            color: #aaa;
            margin-top: 2px;
        }
        .time-slot.booked:hover { transform: none !important; box-shadow: none !important; }
    </style>
</head>
<body>
    <div class="header">
        <button class="back-btn" onclick="window.location.href='index.html'">
            <i class="fas fa-arrow-left"></i> Back to Main
        </button>
        <h1>Book Your Appointment</h1>
    </div>

    <div class="booking-container">
        <!-- Progress Tabs -->
        <div class="progress-tabs">
            <button class="tab active" data-step="1"><div>Step 1</div><div>Services</div></button>
            <button class="tab" data-step="2"><div>Step 2</div><div>Date &amp; Time</div></button>
            <button class="tab" data-step="3"><div>Step 3</div><div>Review &amp; Confirm</div></button>
            <button class="tab" data-step="4"><div>Step 4</div><div>Confirmation</div></button>
        </div>

        <div class="tab-content">

            <!-- ── STEP 1: Service Selection ─────────────────────────────── -->
            <div class="form-step active" id="step-1">
                <h2 class="step-title">Select Your Service(s)</h2>
                <p class="step-subtitle">Choose one or more treatments — you'll see the full total on the Review step</p>

                <?php foreach ($grouped_services as $category => $services): ?>
                    <h3 class="category-heading"><?php echo htmlspecialchars($category); ?></h3>
                    <div class="services-grid">
                        <?php foreach ($services as $service): ?>
                            <div class="service-item"
                                 data-price="<?php echo $service['price']; ?>"
                                 data-duration="<?php echo $service['duration']; ?>">
                                <input type="checkbox"
                                       id="service-<?php echo $service['id']; ?>"
                                       name="service"
                                       value="<?php echo $service['id']; ?>"
                                       data-name="<?php echo htmlspecialchars($service['name'], ENT_QUOTES); ?>"
                                       data-price="<?php echo $service['price']; ?>"
                                       data-duration="<?php echo $service['duration']; ?>">
                                <label for="service-<?php echo $service['id']; ?>">
                                    <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                                    <div class="service-price">
                                        ₱<?php echo number_format($service['price'], 2); ?>
                                        <span class="service-duration"><?php echo $service['duration']; ?> mins</span>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <div class="navigation-buttons">
                    <div></div>
                    <button class="btn btn-primary next-step" data-next="2">Next: Date &amp; Time</button>
                </div>
            </div>

            <!-- ── STEP 2: Date & Time ───────────────────────────────────── -->
            <div class="form-step" id="step-2">
                <h2 class="step-title">Pick Your Date &amp; Time</h2>
                <p class="step-subtitle">Select your preferred appointment slot — greyed-out times are already taken</p>

                <div class="datetime-section">
                    <div class="date-picker">
                        <h4><i class="fas fa-calendar"></i> Select Date</h4>
                        <input type="date" id="booking-date" required>
                    </div>
                    <div class="time-picker">
                        <h4><i class="fas fa-clock"></i> Available Times</h4>
                        <div id="time-slots-loading" style="display:none;color:#aaa;font-size:.9rem;padding:12px 0;">
                            <i class="fas fa-spinner fa-spin"></i> Checking availability…
                        </div>
                        <div class="time-slots" id="time-slots"></div>
                    </div>
                </div>

                <div class="navigation-buttons">
                    <button class="btn btn-secondary prev-step" data-prev="1">Previous</button>
                    <button class="btn btn-primary next-step" data-next="3">Next: Review Booking</button>
                </div>
            </div>

            <!-- ── STEP 3: Review & Confirm ──────────────────────────────── -->
            <div class="form-step" id="step-3">
                <h2 class="step-title">Review Your Booking</h2>
                <p class="step-subtitle">Confirm your appointment details</p>

                <div class="summary-card">
                    <h3 style="margin-bottom:1rem;color:#333;">Booking Summary</h3>
                    <div class="summary-item">
                        <span>Service(s):</span>
                        <span id="summary-service">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Duration:</span>
                        <span id="summary-total-duration">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Date &amp; Time:</span>
                        <span id="summary-datetime">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Stylist:</span>
                        <span>Will be assigned by our team</span>
                    </div>
                    <div class="summary-item" style="font-weight:700;font-size:1.05rem;border-top:2px solid #f19e99;margin-top:.5rem;padding-top:.75rem;">
                        <span>Total Price:</span>
                        <span id="summary-price" style="color:#f19e99;">₱0</span>
                    </div>
                </div>

                <form id="booking-form">
                    <div class="personal-info">
                        <?php if ($isLoggedIn && $userData): ?>
                            <input type="hidden" id="full-name" value="<?php echo htmlspecialchars($userData['name']); ?>">
                            <input type="hidden" id="email"     value="<?php echo htmlspecialchars($userData['email']); ?>">
                            <input type="hidden" id="phone"     value="<?php echo htmlspecialchars($userData['phone']); ?>">
                            <div class="form-group full-width">
                                <p><strong>Booking as:</strong> <?php echo htmlspecialchars($userData['name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($userData['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($userData['phone']); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="form-group">
                                <label for="full-name">Full Name *</label>
                                <input type="text"  id="full-name" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number *</label>
                                <input type="tel"   id="phone" required>
                            </div>
                        <?php endif; ?>
                    </div>

                    <h3 style="margin:2rem 0 1rem;color:#333;">Payment Method</h3>
                    <div class="payment-methods">
                        <div class="payment-item">
                            <input type="radio" id="pay-salon" name="paymentMethod" value="salon" checked>
                            <label for="pay-salon">
                                <i class="fas fa-store"></i>
                                <h4>Pay at Salon</h4>
                                <p>Cash or Card payment on arrival</p>
                            </label>
                        </div>
                    </div>
                </form>

                <div class="navigation-buttons">
                    <button class="btn btn-secondary prev-step" data-prev="2">Previous</button>
                    <button class="btn btn-primary" id="confirm-booking-btn">Confirm Booking</button>
                </div>
            </div>

            <!-- ── STEP 4: Confirmation ──────────────────────────────────── -->
            <div class="form-step" id="step-4">
                <div class="confirmation-card">
                    <i class="fas fa-check-circle"></i>
                    <h3>Booking Received!</h3>
                    <p>Thank you for choosing Levy Branzuela Salon. Your appointment request has been submitted. We will confirm your booking shortly.</p>
                    <div class="reference-number">
                        Booking Reference: <span id="booking-reference">LBS0001234</span>
                    </div>
                </div>

                <div class="summary-card">
                    <h3 style="margin-bottom:1rem;color:#333;">Your Appointment Details</h3>
                    <div class="summary-item"><span>Service(s):</span><span id="final-service">-</span></div>
                    <div class="summary-item"><span>Duration:</span><span id="final-duration">-</span></div>
                    <div class="summary-item"><span>Date &amp; Time:</span><span id="final-datetime">-</span></div>
                    <div class="summary-item"><span>Stylist:</span><span>Will be assigned by our team</span></div>
                    <div class="summary-item" style="font-weight:700;font-size:1.05rem;border-top:2px solid #f19e99;margin-top:.5rem;padding-top:.75rem;">
                        <span>Total Price:</span>
                        <span id="final-price" style="color:#f19e99;">₱0</span>
                    </div>
                </div>

                <div style="text-align:center;margin:2rem 0;">
                    <p><strong>A confirmation email has been sent to:</strong> <span id="confirmation-email">-</span></p>
                    <p style="margin-top:1rem;color:#666;">
                        Please arrive 10 minutes before your appointment time.<br>
                        Bring a valid ID for verification.<br>
                        For changes or cancellations, contact us at (02) 8829-9411<br>
                        Our cancellation policy applies to no-shows and same-day cancellations.
                    </p>
                </div>

                <div class="navigation-buttons" style="justify-content:center;">
                    <button class="btn btn-primary" onclick="window.location.href='index.html'">
                        Return to Main Page
                    </button>
                </div>
            </div>

        </div><!-- /.tab-content -->
    </div><!-- /.booking-container -->

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Processing your booking...</p>
        </div>
    </div>

    <script src="booking_script.js"></script>
</body>
</html>