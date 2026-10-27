<?php
require_once 'config.php';
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userData = null;

if ($isLoggedIn) {
    // Fetch user data from database
    $stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
}

// Initialize empty arrays in case queries fail
$therapists = [];
$services = [];

try {
    // Fetch therapists
    $therapistsQuery = "SELECT * FROM therapists"; // removed status filter
    $therapistsResult = $conn->query($therapistsQuery);
    if ($therapistsResult) {
        $therapists = $therapistsResult->fetch_all(MYSQLI_ASSOC);
    }

    // Fetch services
    $servicesQuery = "SELECT * FROM services"; // removed status filter
    $servicesResult = $conn->query($servicesQuery);
    if ($servicesResult) {
        $services = $servicesResult->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    // Log error and continue with empty arrays
    error_log("Database error: " . $e->getMessage());
}

// Check if we have services and therapists, if not, provide default data
if (empty($services)) {
    $services = [
        [
            'id' => 1,
            'name' => 'Swedish Massage',
            'description' => 'Gentle, flowing strokes that release tension and improve circulation.',
            'price' => 600.00,
            'duration' => 60
        ],
        [
            'id' => 2,
            'name' => 'Deep Tissue Massage',
            'description' => 'Targeted therapy for deeper muscle layers and connective tissue.',
            'price' => 800.00,
            'duration' => 75
        ],
        [
            'id' => 3,
            'name' => 'Hot Stone Massage',
            'description' => 'Heated stones to melt away stress and tension.',
            'price' => 1000.00,
            'duration' => 90
        ],
        [
            'id' => 4,
            'name' => 'Aromatherapy Massage',
            'description' => 'Essential oils combined with therapeutic touch.',
            'price' => 1000.00,
            'duration' => 75
        ],
        [
            'id' => 5,
            'name' => 'Thai Massage',
            'description' => 'Yoga stretches and acupressure for total body wellness.',
            'price' => 1000.00,
            'duration' => 75
        ],
        [
            'id' => 6,
            'name' => 'Reflexology',
            'description' => 'Healing through targeted pressure points on your feet.',
            'price' => 600.00,
            'duration' => 60
        ]
    ];
}

if (empty($therapists)) {
    $therapists = [
        [
            'id' => 1,
            'name' => 'Sarah Johnson',
            'specialty' => 'Swedish & Aromatherapy Massage'
        ],
        [
            'id' => 2,
            'name' => 'Michael Chen',
            'specialty' => 'Deep Tissue & Thai Massage'
        ],
        [
            'id' => 3,
            'name' => 'Emma Wilson',
            'specialty' => 'Hot Stone & Reflexology'
        ],
        [
            'id' => 4,
            'name' => 'David Rodriguez',
            'specialty' => 'Sports & Deep Tissue Massage'
        ],
        [
            'id' => 5,
            'name' => 'Lisa Patel',
            'specialty' => 'Aromatherapy & Swedish Massage'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Your Spa Experience - Serenity Spa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="images/original-logo.jpg">
    <link rel="stylesheet" href="booking_style.css">
</head>

<body>
    <div class="header">
        <button class="back-btn" onclick="window.location.href='admin_panel.php'">
            <i class="fas fa-arrow-left"></i> Back to Admin
        </button>
        <h1>Book Your Spa Experience</h1>
    </div>

    <div class="booking-container">
        <div class="progress-tabs">
            <button class="tab active" data-step="1">
                <div>Step 1</div>
                <div>Health Check & Services</div>
            </button>
            <button class="tab" data-step="2">
                <div>Step 2</div>
                <div>Date & Therapist</div>
            </button>
            <button class="tab" data-step="3">
                <div>Step 3</div>
                <div>Review & Payment</div>
            </button>
            <button class="tab" data-step="4">
                <div>Step 4</div>
                <div>Confirmation</div>
            </button>
        </div>

        <div class="tab-content">
            <!-- Step 1: Health Check & Services Selection -->
            <div class="form-step active" id="step-1">
                <!-- Health Check Section -->
                <div class="health-check-container">
                    <div class="health-check-title">
                        <i class="fas fa-heartbeat"></i>
                        Health Check
                    </div>
                    <p class="health-check-subtitle">Before confirming your booking, please answer the following:</p>

                    <!-- Question 1: Medical Conditions -->
                    <div class="health-question">
                        <h4>1. Do you have any of the following medical conditions?</h4>
                        <div class="health-options">
                            <div class="health-option">
                                <input type="checkbox" id="heart-condition" name="medicalConditions" value="heart">
                                <label for="heart-condition">Heart Condition / High Blood Pressure</label>
                            </div>
                            <div class="health-option">
                                <input type="checkbox" id="diabetes" name="medicalConditions" value="diabetes">
                                <label for="diabetes">Diabetes</label>
                            </div>
                            <div class="health-option">
                                <input type="checkbox" id="surgery-injury" name="medicalConditions" value="surgery">
                                <label for="surgery-injury">Recent Surgery / Injury</label>
                            </div>
                            <div class="health-option">
                                <input type="checkbox" id="skin-infection" name="medicalConditions" value="skin">
                                <label for="skin-infection">Skin Infection / Open Wounds</label>
                            </div>
                            <div class="health-option">
                                <input type="checkbox" id="no-conditions" name="medicalConditions" value="none">
                                <label for="no-conditions">None</label>
                            </div>
                        </div>
                    </div>

                    <!-- Question 2: Pregnancy -->
                    <div class="health-question">
                        <h4>2. Are you pregnant? (For female respondents)</h4>
                        <div class="health-options">
                            <div class="health-option">
                                <input type="radio" id="pregnant-yes" name="pregnancy" value="yes">
                                <label for="pregnant-yes">Yes</label>
                            </div>
                            <div class="pregnancy-weeks">
                                <input type="number" id="pregnancy-weeks" placeholder="weeks" min="1" max="42">
                                <label for="pregnancy-weeks"> weeks</label>
                            </div>
                            <div class="health-option">
                                <input type="radio" id="pregnant-no" name="pregnancy" value="no">
                                <label for="pregnant-no">No</label>
                            </div>
                        </div>
                    </div>

                    <!-- Question 3: Allergies -->
                    <div class="health-question">
                        <h4>3. Do you have any allergies?</h4>
                        <div class="health-options">
                            <div class="health-option">
                                <input type="checkbox" id="oil-allergy" name="allergies" value="oils">
                                <label for="oil-allergy">Massage Oils / Lotions</label>
                            </div>
                            <div class="health-option">
                                <input type="checkbox" id="essential-allergy" name="allergies" value="essential">
                                <label for="essential-allergy">Essential Oils</label>
                            </div>
                            <div class="health-option">
                                <input type="checkbox" id="other-allergy" name="allergies" value="other">
                                <label for="other-allergy">Other:</label>
                            </div>
                            <div class="allergies-other health-text-input">
                                <input type="text" id="other-allergy-text" placeholder="Please specify...">
                            </div>
                            <div class="health-option">
                                <input type="checkbox" id="no-allergies" name="allergies" value="none">
                                <label for="no-allergies">None</label>
                            </div>
                        </div>
                    </div>

                    <!-- Question 4: Current Illness -->
                    <div class="health-question">
                        <h4>4. Are you currently sick (fever, flu, contagious condition)?</h4>
                        <div class="health-options">
                            <div class="health-option">
                                <input type="radio" id="sick-yes" name="currentlyIll" value="yes">
                                <label for="sick-yes">Yes</label>
                            </div>
                            <div class="health-option">
                                <input type="radio" id="sick-no" name="currentlyIll" value="no">
                                <label for="sick-no">No</label>
                            </div>
                        </div>
                    </div>

                    <!-- Health Warning -->
                    <div class="health-warning" id="health-warning">
                        <p><i class="fas fa-exclamation-triangle"></i>
                            <strong>Please note:</strong> Based on your health information, we may need to modify
                            certain treatments or recommend consulting with our wellness coordinator before your
                            appointment. We'll contact you to discuss the best options for your safety and comfort.
                        </p>
                    </div>
                </div>

                <!-- Services Section -->
                <h2 class="step-title">Select Your Service</h2>
                <p class="step-subtitle">Choose the perfect treatment for your relaxation needs</p>

                <div class="services-grid">
                    <?php foreach ($services as $service): ?>
                        <div class="service-item" data-price="<?php echo $service['price']; ?>"
                            data-duration="<?php echo $service['duration']; ?>"
                            data-type="<?php echo strtolower(str_replace(' ', '_', $service['name'])); ?>">
                            <input type="radio" id="service-<?php echo $service['id']; ?>" name="service"
                                value="<?php echo strtolower(str_replace(' ', '_', $service['name'])); ?>">
                            <label for="service-<?php echo $service['id']; ?>">
                                <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                                <p><?php echo htmlspecialchars($service['description']); ?></p>
                                <div class="service-price">₱<?php echo number_format($service['price'], 2); ?>
                                    <span class="service-duration"><?php echo $service['duration']; ?> mins</span>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 style="margin: 2rem 0 1rem 0; color: #333;">Choose Room Type</h3>
                <div class="room-grid">
                    <div class="service-item">
                        <input type="radio" id="single-room" name="roomType" value="single">
                        <label for="single-room">
                            <h3>Single Treatment Room</h3>
                            <p>Private room for individual treatments</p>
                            <div class="service-price">₱600</div>
                        </label>
                    </div>

                    <div class="service-item">
                        <input type="radio" id="couples-room" name="roomType" value="couples">
                        <label for="couples-room">
                            <h3>Couples Room</h3>
                            <p>Side-by-side treatments for two people</p>
                            <div class="service-price">₱1,000</div>
                        </label>
                    </div>


                    <div class="service-item">
                        <input type="radio" id="vip-suite" name="roomType" value="vip">
                        <label for="vip-suite">
                            <h3>VIP Suite</h3>
                            <p>Luxury suite with premium amenities</p>
                            <div class="service-price">₱2,000</div>
                        </label>
                    </div>
                </div>

                <div class="navigation-buttons">
                    <div></div>
                    <button class="btn btn-primary next-step" data-next="2">Next: Date & Time</button>
                </div>
            </div>

            <!-- Step 2: Date & Time Selection -->
            <div class="form-step" id="step-2">
                <h2 class="step-title">Pick Your Date & Time</h2>
                <p class="step-subtitle">Select your preferred appointment slot</p>

                <div class="datetime-section">
                    <div class="date-picker">
                        <h4><i class="fas fa-calendar"></i> Select Date</h4>
                        <input type="date" id="booking-date" required>
                    </div>

                    <div class="time-picker">
                        <h4><i class="fas fa-clock"></i> Available Times</h4>
                        <div class="time-slots" id="time-slots">
                            <!-- Time slots will be generated by JavaScript -->
                        </div>
                    </div>
                </div>

                <div class="therapist-section">
                    <h3 style="margin-bottom: 1rem; color: #333;">Select Therapist</h3>
                    <div class="services-grid" id="therapist-grid">
                        <div class="service-item">
                            <input type="radio" id="no-preference" name="therapist" value="no-preference" checked>
                            <label for="no-preference">
                                <h3>No Preference</h3>
                                <p>Let us assign the best available therapist for you</p>
                            </label>
                        </div>
                        <?php foreach ($therapists as $therapist): ?>
                            <div class="service-item">
                                <input type="radio" id="therapist-<?php echo $therapist['id']; ?>" name="therapist"
                                    value="<?php echo $therapist['id']; ?>">
                                <label for="therapist-<?php echo $therapist['id']; ?>">
                                    <h3><?php echo htmlspecialchars($therapist['name']); ?></h3>
                                    <p>Specialist in <?php echo htmlspecialchars($therapist['specialty']); ?></p>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="navigation-buttons">
                    <button class="btn btn-secondary prev-step" data-prev="1">Previous</button>
                    <button class="btn btn-primary next-step" data-next="3">Next: Review Booking</button>
                </div>
            </div>

            <!-- Step 3: Personal Info & Payment -->
            <div class="form-step" id="step-3">
                <h2 class="step-title">Personal Information & Payment</h2>
                <p class="step-subtitle">Complete your booking details</p>

                <div class="summary-card">
                    <h3 style="margin-bottom: 1rem; color: #333;">Booking Summary</h3>
                    <div class="summary-item">
                        <span>Service:</span>
                        <span id="summary-service">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Room Type:</span>
                        <span id="summary-room">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Date & Time:</span>
                        <span id="summary-datetime">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Therapist:</span>
                        <span id="summary-therapist">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Total Price:</span>
                        <span id="summary-price">₱0</span>
                    </div>
                </div>

                <!-- Health Information Summary -->
                <div class="summary-card"
                    style="background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%); border: 1px solid #ffc9c9;">
                    <h3 style="margin-bottom: 1rem; color: #d4a574; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-heartbeat"></i>
                        Health Information Summary
                    </h3>

                    <div class="health-summary-section">
                        <h4 style="color: #666; margin-bottom: 0.5rem; font-size: 0.95rem;">Medical Conditions:</h4>
                        <div id="summary-medical-conditions"
                            style="margin-bottom: 1rem; color: #333; font-size: 0.9rem;">-</div>

                        <h4 style="color: #666; margin-bottom: 0.5rem; font-size: 0.95rem;">Pregnancy Status:</h4>
                        <div id="summary-pregnancy" style="margin-bottom: 1rem; color: #333; font-size: 0.9rem;">-</div>

                        <h4 style="color: #666; margin-bottom: 0.5rem; font-size: 0.95rem;">Allergies:</h4>
                        <div id="summary-allergies" style="margin-bottom: 1rem; color: #333; font-size: 0.9rem;">-</div>

                        <h4 style="color: #666; margin-bottom: 0.5rem; font-size: 0.95rem;">Current Health Status:</h4>
                        <div id="summary-illness" style="margin-bottom: 0.5rem; color: #333; font-size: 0.9rem;">-</div>
                    </div>

                    <div id="health-alert"
                        style="display: none; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 0.8rem; margin-top: 1rem;">
                        <div style="color: #856404; font-weight: 500; font-size: 0.9rem;">
                            <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i>
                            Health Alert: Special considerations may be needed for this client's treatment.
                        </div>
                    </div>
                </div>

                <form id="booking-form">
                    <div class="personal-info">
                        <?php if ($isLoggedIn): ?>
                            <input type="hidden" id="full-name" value="<?php echo htmlspecialchars($userData['name']); ?>">
                            <input type="hidden" id="email" value="<?php echo htmlspecialchars($userData['email']); ?>">
                            <input type="hidden" id="phone" value="<?php echo htmlspecialchars($userData['phone']); ?>">
                            <div class="form-group full-width">
                                <p><strong>Booking as:</strong> <?php echo htmlspecialchars($userData['name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($userData['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($userData['phone']); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="form-group">
                                <label for="full-name">Full Name *</label>
                                <input type="text" id="full-name" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number *</label>
                                <input type="tel" id="phone" required>
                            </div>
                        <?php endif; ?>
                    </div>

                    <h3 style="margin: 2rem 0 1rem 0; color: #333;">Payment Method</h3>
                    <div class="payment-methods">
                        <div class="payment-item">
                            <input type="radio" id="pay-spa" name="paymentMethod" value="spa">
                            <label for="pay-spa">
                                <i class="fas fa-store"></i>
                                <h4>Pay at Spa</h4>
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

            <!-- Step 4: Confirmation -->
            <div class="form-step" id="step-4">
                <div class="confirmation-card">
                    <i class="fas fa-check-circle"></i>
                    <h3>Booking Confirmed!</h3>
                    <p>Thank you for choosing Serenity Spa. Your appointment has been successfully booked.</p>
                    <div class="reference-number">
                        Booking Reference: <span id="booking-reference">SPA001234</span>
                    </div>
                </div>

                <div class="summary-card">
                    <h3 style="margin-bottom: 1rem; color: #333;">Your Appointment Details</h3>
                    <div class="summary-item">
                        <span>Service:</span>
                        <span id="final-service">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Room Type:</span>
                        <span id="final-room">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Date & Time:</span>
                        <span id="final-datetime">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Therapist:</span>
                        <span id="final-therapist">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Total Price:</span>
                        <span id="final-price">₱0</span>
                    </div>
                </div>

                <!-- Final Health Information Summary -->
                <div class="summary-card"
                    style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 1px solid #bae6fd;">
                    <h3 style="margin-bottom: 1rem; color: #0369a1; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-notes-medical"></i>
                        Health Information on File
                    </h3>

                    <div class="health-summary-section">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <h4 style="color: #666; margin-bottom: 0.5rem; font-size: 0.9rem;">Medical Conditions:
                                </h4>
                                <div id="final-medical-conditions"
                                    style="margin-bottom: 1rem; color: #333; font-size: 0.85rem;">-</div>
                            </div>
                            <div>
                                <h4 style="color: #666; margin-bottom: 0.5rem; font-size: 0.9rem;">Allergies:</h4>
                                <div id="final-allergies" style="margin-bottom: 1rem; color: #333; font-size: 0.85rem;">
                                    -</div>
                            </div>
                            <div>
                                <h4 style="color: #666; margin-bottom: 0.5rem; font-size: 0.9rem;">Pregnancy:</h4>
                                <div id="final-pregnancy" style="margin-bottom: 1rem; color: #333; font-size: 0.85rem;">
                                    -</div>
                            </div>
                            <div>
                                <h4 style="color: #666; margin-bottom: 0.5rem; font-size: 0.9rem;">Health Status:</h4>
                                <div id="final-illness" style="margin-bottom: 1rem; color: #333; font-size: 0.85rem;">-
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="text-align: center; margin: 2rem 0;">
                    <p><strong>Confirmation Details:</strong></p>
                    <p>A confirmation email has been sent to: <span id="confirmation-email">-</span></p>
                    <p style="margin-top: 1rem; color: #666;">
                        IMPORTANT INFORMATION:<br>
                        Please arrive 15 minutes before your appointment time.<br>
                        Bring a valid ID for verification.<br>
                        For any changes or cancellations, email us at serenityspamassage25@gmail.com <br>
                        Our cancellation policy applies to no-shows and same-day cancellations.
                    </p>
                </div>

                <div class="navigation-buttons" style="justify-content: center;">
                    <button class="btn btn-primary" onclick="window.location.href='admin_panel.php'">
                        Return to Admin Panel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Processing your booking...</p>
        </div>
    </div>

    <!-- Right before closing body tag -->
    <script>
        // Spa Booking System JavaScript

// Complete SpaBookingSystem with Couples Room Support
// Complete SpaBookingSystem with Couples Room Support
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.booking-container')) {
        console.log('Initializing booking system...');
        window.spaBooking = new SpaBookingSystem();
    } else {
        console.log('Booking container not found, skipping initialization');
    }
});

class SpaBookingSystem {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 4;
        this.bookingData = {
            healthInfo: {
                medicalConditions: [],
                pregnancy: '',
                pregnancyWeeks: '',
                allergies: [],
                otherAllergy: '',
                currentlyIll: ''
            },
            service: {},
            roomType: '',
            dateTime: {},
            therapist: {},
            therapist2: null,
            personalInfo: {},
            paymentMethod: '',
            totalPrice: 0
        };
        this.therapistAvailability = {};
        this.selectedTherapists = [];
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupDatePicker();
        this.generateTimeSlots();
        this.updateProgressTabs();
        this.setupHealthCheckLogic();
    }

    setupHealthCheckLogic() {
        const noneCondition = document.getElementById('no-conditions');
        const medicalCheckboxes = document.querySelectorAll('input[name="medicalConditions"]:not(#no-conditions)');
        
        if (noneCondition) {
            noneCondition.addEventListener('change', () => {
                if (noneCondition.checked) {
                    medicalCheckboxes.forEach(cb => cb.checked = false);
                }
            });
        }

        medicalCheckboxes.forEach(cb => {
            cb.addEventListener('change', () => {
                if (cb.checked && noneCondition) {
                    noneCondition.checked = false;
                }
                this.checkHealthWarnings();
            });
        });

        const noneAllergy = document.getElementById('no-allergies');
        const allergyCheckboxes = document.querySelectorAll('input[name="allergies"]:not(#no-allergies)');
        
        if (noneAllergy) {
            noneAllergy.addEventListener('change', () => {
                if (noneAllergy.checked) {
                    allergyCheckboxes.forEach(cb => cb.checked = false);
                    const otherText = document.querySelector('.allergies-other');
                    if (otherText) otherText.style.display = 'none';
                }
            });
        }

        allergyCheckboxes.forEach(cb => {
            cb.addEventListener('change', () => {
                if (cb.checked && noneAllergy) {
                    noneAllergy.checked = false;
                }
            });
        });

        const otherAllergyCheckbox = document.getElementById('other-allergy');
        const otherAllergyText = document.querySelector('.allergies-other');
        
        if (otherAllergyCheckbox && otherAllergyText) {
            otherAllergyCheckbox.addEventListener('change', () => {
                otherAllergyText.style.display = otherAllergyCheckbox.checked ? 'block' : 'none';
            });
        }

        const pregnantYes = document.getElementById('pregnant-yes');
        const pregnancyWeeks = document.querySelector('.pregnancy-weeks');
        
        if (pregnantYes && pregnancyWeeks) {
            pregnantYes.addEventListener('change', () => {
                pregnancyWeeks.style.display = pregnantYes.checked ? 'block' : 'none';
                this.checkHealthWarnings();
            });
        }

        const pregnantNo = document.getElementById('pregnant-no');
        if (pregnantNo && pregnancyWeeks) {
            pregnantNo.addEventListener('change', () => {
                pregnancyWeeks.style.display = 'none';
            });
        }

        const sickYes = document.getElementById('sick-yes');
        const sickNo = document.getElementById('sick-no');
        
        if (sickYes) {
            sickYes.addEventListener('change', () => {
                this.checkHealthWarnings();
            });
        }

        if (sickNo) {
            sickNo.addEventListener('change', () => {
                this.checkHealthWarnings();
            });
        }
    }

    checkHealthWarnings() {
        const warning = document.getElementById('health-warning');
        if (!warning) return;
        
        let showWarning = false;

        const heartCondition = document.getElementById('heart-condition');
        const diabetes = document.getElementById('diabetes');
        const surgery = document.getElementById('surgery-injury');
        const skinInfection = document.getElementById('skin-infection');
        const pregnant = document.getElementById('pregnant-yes');
        const sick = document.getElementById('sick-yes');

        if ((heartCondition && heartCondition.checked) ||
            (diabetes && diabetes.checked) ||
            (surgery && surgery.checked) ||
            (skinInfection && skinInfection.checked) ||
            (pregnant && pregnant.checked) ||
            (sick && sick.checked)) {
            showWarning = true;
        }

        warning.classList.toggle('show', showWarning);
    }

    setupEventListeners() {
        console.log('Setting up event listeners...');
        
        document.querySelectorAll('.next-step').forEach(btn => {
            btn.addEventListener('click', (e) => {
                console.log('Next button clicked');
                const nextStep = parseInt(e.target.dataset.next);
                if (this.validateCurrentStep()) {
                    this.goToStep(nextStep);
                }
            });
        });

        document.querySelectorAll('.prev-step').forEach(btn => {
            btn.addEventListener('click', (e) => {
                console.log('Previous button clicked');
                const prevStep = parseInt(e.target.dataset.prev);
                this.goToStep(prevStep);
            });
        });

        const confirmBookingBtn = document.getElementById('confirm-booking-btn');
        if (confirmBookingBtn) {
            confirmBookingBtn.addEventListener('click', (e) => {
                console.log('Confirm booking clicked');
                e.preventDefault();
                if (this.validateCurrentStep()) {
                    this.confirmBooking();
                }
            });
        }

        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                console.log('Tab clicked:', tab.dataset.step);
                const stepNumber = parseInt(tab.dataset.step);
                if (stepNumber <= this.currentStep || stepNumber === this.currentStep + 1) {
                    if (stepNumber === this.currentStep + 1) {
                        if (this.validateCurrentStep()) {
                            this.goToStep(stepNumber);
                        }
                    } else {
                        this.goToStep(stepNumber);
                    }
                }
            });
        });

        document.querySelectorAll('.service-item input[name="service"]').forEach(radio => {
            radio.addEventListener('change', () => {
                console.log('Service selected:', radio.value);
                this.handleServiceSelection(radio);
                this.updateBookingSummary();
            });
        });

        document.querySelectorAll('.service-item input[name="roomType"]').forEach(radio => {
            radio.addEventListener('change', () => {
                console.log('Room selected:', radio.value);
                this.handleRoomSelection(radio);
                this.updateBookingSummary();
            });
        });

        document.querySelectorAll('input[name="therapist"]').forEach(radio => {
            radio.addEventListener('change', () => {
                console.log('Therapist selected:', radio.value);
                this.handleTherapistSelection(radio);
                this.checkTherapistTimeAvailability();
            });
        });

        document.querySelectorAll('input[name="paymentMethod"]').forEach(radio => {
            radio.addEventListener('change', () => {
                console.log('Payment method selected:', radio.value);
                this.handlePaymentMethodSelection(radio);
            });
        });

        const dateInput = document.getElementById('booking-date');
        if (dateInput) {
            dateInput.addEventListener('change', (e) => {
                console.log('Date selected:', e.target.value);
                this.handleDateChange(e.target.value);
            });
        }

        this.setupFormValidation();
        console.log('Event listeners setup complete');
    }

    setupFormValidation() {
        const inputs = document.querySelectorAll('input[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateInput(input));
            input.addEventListener('input', () => this.clearValidationError(input));
        });
    }

    validateInput(input) {
        const value = input.value.trim();
        let isValid = true;
        let errorMessage = '';

        this.clearValidationError(input);

        if (!value && input.required) {
            isValid = false;
            errorMessage = 'This field is required';
        } else if (input.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid email address';
            }
        } else if (input.type === 'tel' && value) {
            const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
            if (!phoneRegex.test(value.replace(/[\s\-\(\)]/g, ''))) {
                isValid = false;
                errorMessage = 'Please enter a valid phone number';
            }
        }

        if (!isValid) {
            this.showValidationError(input, errorMessage);
        }

        return isValid;
    }

    showValidationError(input, message) {
        input.style.borderColor = '#dc3545';
        input.style.backgroundColor = '#fff5f5';
        
        const existingError = input.parentNode.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }

        const errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        errorElement.style.color = '#dc3545';
        errorElement.style.fontSize = '0.875rem';
        errorElement.style.marginTop = '0.25rem';
        errorElement.textContent = message;
        input.parentNode.appendChild(errorElement);
    }

    clearValidationError(input) {
        input.style.borderColor = '#e1e5e9';
        input.style.backgroundColor = '';
        
        const errorMessage = input.parentNode.querySelector('.error-message');
        if (errorMessage) {
            errorMessage.remove();
        }
    }

    validateCurrentStep() {
        console.log('Validating step:', this.currentStep);
        let isValid = true;

        switch (this.currentStep) {
            case 1:
                this.collectHealthInfo();
                
                const selectedService = document.querySelector('input[name="service"]:checked');
                const selectedRoom = document.querySelector('input[name="roomType"]:checked');
                
                if (!selectedService) {
                    this.showAlert('Please select a service', 'error');
                    isValid = false;
                }
                if (!selectedRoom) {
                    this.showAlert('Please select a room type', 'error');
                    isValid = false;
                }
                break;

            case 2:
                const selectedDate = document.getElementById('booking-date').value;
                const selectedTime = document.querySelector('.time-slot.selected');
                const isCouplesRoom = this.bookingData.roomType === 'couples';

                if (!selectedDate) {
                    this.showAlert('Please select a date', 'error');
                    isValid = false;
                }
                if (!selectedTime) {
                    this.showAlert('Please select a time slot', 'error');
                    isValid = false;
                }
                
                if (isCouplesRoom) {
                    // For couples room, check if exactly 2 therapists are selected
                    const selectedTherapists = document.querySelectorAll('input[name="therapist"]:checked:not(#no-preference)');
                    
                    if (selectedTherapists.length === 0) {
                        this.showAlert('Please select 2 therapists for the couples room', 'error');
                        isValid = false;
                    } else if (selectedTherapists.length === 1) {
                        this.showAlert('Please select 2 therapists. You have only selected 1 therapist.', 'error');
                        isValid = false;
                    } else if (selectedTherapists.length > 2) {
                        this.showAlert('You can only select 2 therapists for the couples room', 'error');
                        isValid = false;
                    }
                } else {
                    // For single/VIP rooms, check if 1 therapist is selected
                    const selectedTherapist = document.querySelector('input[name="therapist"]:checked');
                    
                    if (!selectedTherapist) {
                        this.showAlert('Please select a therapist', 'error');
                        isValid = false;
                    }
                }

                if (isValid) {
                    this.bookingData.dateTime = {
                        date: selectedDate,
                        time: selectedTime.textContent,
                        formattedDate: this.formatDate(selectedDate)
                    };
                }
                break;

            case 3:
                if (!document.querySelector('.payment-item input[name="paymentMethod"]:checked')) {
                    this.showAlert('Please select a payment method', 'error');
                    isValid = false;
                }

                const isLoggedIn = typeof PHP_IS_LOGGED_IN !== 'undefined' ? PHP_IS_LOGGED_IN : false;
                if (!isLoggedIn) {
                    const requiredFields = ['full-name', 'email', 'phone'];
                    requiredFields.forEach(fieldId => {
                        const input = document.getElementById(fieldId);
                        if (input && !input.value.trim()) {
                            this.showValidationError(input, 'This field is required');
                            isValid = false;
                        }
                    });
                }

                if (isValid) {
                    this.bookingData.personalInfo = {
                        fullName: document.getElementById('full-name').value.trim(),
                        email: document.getElementById('email').value.trim(),
                        phone: document.getElementById('phone').value.trim()
                    };
                    this.bookingData.paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
                }
                break;
        }

        console.log('Validation result:', isValid);
        return isValid;
    }

    collectHealthInfo() {
        const medicalConditions = [];
        document.querySelectorAll('input[name="medicalConditions"]:checked').forEach(cb => {
            medicalConditions.push(cb.value);
        });

        const pregnancyRadio = document.querySelector('input[name="pregnancy"]:checked');
        const pregnancyWeeksInput = document.getElementById('pregnancy-weeks');
        const pregnancyWeeks = pregnancyWeeksInput ? pregnancyWeeksInput.value : '';

        const allergies = [];
        document.querySelectorAll('input[name="allergies"]:checked').forEach(cb => {
            allergies.push(cb.value);
        });
        const otherAllergyInput = document.getElementById('other-allergy-text');
        const otherAllergy = otherAllergyInput ? otherAllergyInput.value : '';

        const currentlyIllRadio = document.querySelector('input[name="currentlyIll"]:checked');

        this.bookingData.healthInfo = {
            medicalConditions: medicalConditions,
            pregnancy: pregnancyRadio ? pregnancyRadio.value : '',
            pregnancyWeeks: pregnancyWeeks,
            allergies: allergies,
            otherAllergy: otherAllergy,
            currentlyIll: currentlyIllRadio ? currentlyIllRadio.value : ''
        };
    }

    goToStep(stepNumber) {
        console.log('Going to step:', stepNumber);
        if (stepNumber < 1 || stepNumber > this.totalSteps) return;

        const currentStepElement = document.getElementById(`step-${this.currentStep}`);
        if (currentStepElement) {
            currentStepElement.classList.remove('active');
        }

        const newStepElement = document.getElementById(`step-${stepNumber}`);
        if (newStepElement) {
            newStepElement.classList.add('active');
        }

        this.currentStep = stepNumber;
        this.updateProgressTabs();

        if (stepNumber === 4) {
            this.updateFinalSummary();
        }

        const container = document.querySelector('.booking-container');
        if (container) {
            container.scrollIntoView({ behavior: 'smooth' });
        }
    }

    updateProgressTabs() {
        document.querySelectorAll('.tab').forEach(tab => {
            const stepNumber = parseInt(tab.dataset.step);
            tab.classList.remove('active', 'completed');

            if (stepNumber < this.currentStep) {
                tab.classList.add('completed');
            } else if (stepNumber === this.currentStep) {
                tab.classList.add('active');
            }
        });
    }

    handleServiceSelection(radio) {
        console.log('Handling service selection:', radio.value);
        
        document.querySelectorAll('input[name="service"]').forEach(r => {
            const item = r.closest('.service-item');
            if (item) item.classList.remove('selected');
        });

        const serviceItem = radio.closest('.service-item');
        if (serviceItem) {
            serviceItem.classList.add('selected');
            
            const serviceName = serviceItem.querySelector('h3').textContent;
            const servicePrice = parseFloat(serviceItem.dataset.price);
            const serviceDuration = parseInt(serviceItem.dataset.duration);

            this.bookingData.service = {
                type: serviceName.toLowerCase().replace(/\s+/g, '_'),
                name: serviceName,
                price: servicePrice,
                duration: serviceDuration
            };
        }
    }

    handleRoomSelection(radio) {
        console.log('Handling room selection:', radio.value);
        
        document.querySelectorAll('input[name="roomType"]').forEach(r => {
            const item = r.closest('.service-item');
            if (item) item.classList.remove('selected');
        });

        const roomItem = radio.closest('.service-item');
        if (roomItem) {
            roomItem.classList.add('selected');
        }
        
        this.bookingData.roomType = radio.value;
        this.toggleCouplesTherapistSelection(radio.value === 'couples');
        this.updateBookingSummary();
    }

    toggleCouplesTherapistSelection(isCouplesRoom) {
        const therapistGrid = document.getElementById('therapist-grid');
        if (!therapistGrid) return;
        
        // Add or update instruction text
        let instructionText = document.getElementById('therapist-instruction');
        const therapistTitle = document.querySelector('.therapist-section h3');
        
        if (isCouplesRoom) {
            // Disable "No Preference" for couples room
            const noPreference = document.getElementById('no-preference');
            if (noPreference) {
                noPreference.disabled = true;
                if (noPreference.checked) {
                    noPreference.checked = false;
                    const item = noPreference.closest('.service-item');
                    if (item) item.classList.remove('selected');
                }
                const noPrefItem = noPreference.closest('.service-item');
                if (noPrefItem) {
                    noPrefItem.style.opacity = '0.5';
                    noPrefItem.style.pointerEvents = 'none';
                }
            }
            
            // Add instruction text
            if (!instructionText) {
                instructionText = document.createElement('p');
                instructionText.id = 'therapist-instruction';
                instructionText.style.cssText = 'color: #d4a574; font-weight: 600; margin-bottom: 1rem; font-size: 1rem;';
                instructionText.innerHTML = '<i class="fas fa-info-circle"></i> Please select 2 different therapists for the couples room';
                if (therapistTitle && therapistTitle.nextSibling) {
                    therapistTitle.parentNode.insertBefore(instructionText, therapistTitle.nextSibling);
                } else if (therapistTitle) {
                    therapistTitle.parentNode.appendChild(instructionText);
                }
            }
            instructionText.style.display = 'block';
            
            // Change therapist inputs to checkboxes for multiple selection
            document.querySelectorAll('input[name="therapist"]').forEach(input => {
                if (input.id !== 'no-preference') {
                    input.type = 'checkbox';
                    input.dataset.originalType = 'radio';
                }
            });
            
            // Clear previous selections
            this.bookingData.therapist = {};
            this.bookingData.therapist2 = null;
            this.selectedTherapists = [];
            
        } else {
            // Re-enable "No Preference" for other rooms
            const noPreference = document.getElementById('no-preference');
            if (noPreference) {
                noPreference.disabled = false;
                const noPrefItem = noPreference.closest('.service-item');
                if (noPrefItem) {
                    noPrefItem.style.opacity = '1';
                    noPrefItem.style.pointerEvents = 'auto';
                }
            }
            
            // Hide instruction text
            if (instructionText) {
                instructionText.style.display = 'none';
            }
            
            // Change back to radio buttons
            document.querySelectorAll('input[name="therapist"]').forEach(input => {
                if (input.dataset.originalType === 'radio') {
                    input.type = 'radio';
                    delete input.dataset.originalType;
                }
                input.checked = false;
                const item = input.closest('.service-item');
                if (item) item.classList.remove('selected');
            });
            
            this.bookingData.therapist2 = null;
            this.selectedTherapists = [];
        }
    }

    handleTherapistSelection(radio) {
        console.log('Handling therapist selection:', radio.value);
        
        const isCouplesRoom = this.bookingData.roomType === 'couples';
        
        if (isCouplesRoom) {
            // Handle checkbox selection for couples room
            const therapistItem = radio.closest('.service-item');
            
            if (!this.selectedTherapists) {
                this.selectedTherapists = [];
            }
            
            if (radio.checked) {
                // Check if we already have 2 therapists selected
                if (this.selectedTherapists.length >= 2) {
                    // Uncheck this one
                    radio.checked = false;
                    this.showAlert('You can only select 2 therapists for the couples room', 'error');
                    return;
                }
                
                // Add to selected therapists
                this.selectedTherapists.push({
                    id: radio.value,
                    name: therapistItem ? therapistItem.querySelector('h3').textContent : 'Selected Therapist'
                });
                
                if (therapistItem) {
                    therapistItem.classList.add('selected');
                }
            } else {
                // Remove from selected therapists
                this.selectedTherapists = this.selectedTherapists.filter(t => t.id !== radio.value);
                
                if (therapistItem) {
                    therapistItem.classList.remove('selected');
                }
            }
            
            // Update bookingData
            if (this.selectedTherapists.length > 0) {
                this.bookingData.therapist = this.selectedTherapists[0];
            } else {
                this.bookingData.therapist = {};
            }
            
            if (this.selectedTherapists.length > 1) {
                this.bookingData.therapist2 = this.selectedTherapists[1];
            } else {
                this.bookingData.therapist2 = null;
            }
            
        } else {
            // Handle radio selection for single/VIP rooms
            const therapistItem = radio.closest('.service-item');
            document.querySelectorAll('input[name="therapist"]').forEach(item => {
                const serviceItem = item.closest('.service-item');
                if (serviceItem) serviceItem.classList.remove('selected');
            });
            
            if (therapistItem) {
                therapistItem.classList.add('selected');
            }
            
            this.bookingData.therapist = {
                id: radio.value,
                name: radio.value === 'no-preference' ? 'No Preference' : (therapistItem ? therapistItem.querySelector('h3').textContent : 'Selected Therapist')
            };
            this.bookingData.therapist2 = null;
        }
        
        this.updateBookingSummary();
        this.checkTherapistTimeAvailability();
    }

    async checkTherapistTimeAvailability() {
        const selectedDate = document.getElementById('booking-date');
        const selectedTime = document.querySelector('.time-slot.selected');

        if (!selectedDate || !selectedTime || !selectedDate.value) {
            return;
        }

        const therapistsToCheck = [];
        
        // Collect all selected therapist IDs
        if (this.bookingData.roomType === 'couples') {
            // For couples room, get all checked therapists
            document.querySelectorAll('input[name="therapist"]:checked:not(#no-preference)').forEach(input => {
                therapistsToCheck.push(input.value);
            });
        } else {
            // For single/VIP room, get the selected therapist
            const selectedTherapist = document.querySelector('input[name="therapist"]:checked');
            if (selectedTherapist && selectedTherapist.value !== 'no-preference') {
                therapistsToCheck.push(selectedTherapist.value);
            }
        }

        if (therapistsToCheck.length === 0) {
            return;
        }

        try {
            const response = await fetch('check_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    date: selectedDate.value,
                    time: selectedTime.textContent,
                    therapists: therapistsToCheck
                })
            });

            if (!response.ok) {
                console.error('Server returned error:', response.status);
                return;
            }

            const result = await response.json();

            if (result.error) {
                console.error('Server error:', result.message);
                return;
            }

            if (result.unavailableTherapists && result.unavailableTherapists.length > 0) {
                // Get names for all unavailable therapists
                const unavailableNames = result.unavailableTherapists.map(id => {
                    const therapistInput = document.getElementById(`therapist-${id}`);
                    if (therapistInput) {
                        const nameElement = therapistInput.closest('.service-item').querySelector('h3');
                        return nameElement ? nameElement.textContent : 'Therapist';
                    }
                    return 'Therapist';
                });
                
                const names = unavailableNames.join(' and ');
                const verb = result.unavailableTherapists.length === 1 ? 'is' : 'are';
                
                this.showAlert(
                    `${names} ${verb} not available at ${selectedTime.textContent}. Please choose different therapist(s) or time slot.`, 
                    'error'
                );
                
                selectedTime.classList.add('therapist-unavailable');
            } else {
                selectedTime.classList.remove('therapist-unavailable');
                selectedTime.removeAttribute('title');
            }

        } catch (error) {
            console.error('Error checking therapist availability:', error);
        }
    }

    getTherapistName(therapistId) {
        if (therapistId === 'no-preference') return 'No Preference';
        
        const therapistElement = document.getElementById(`therapist-${therapistId}`);
        if (therapistElement) {
            const nameElement = therapistElement.closest('.service-item').querySelector('h3');
            return nameElement ? nameElement.textContent : 'Selected Therapist';
        }
        return 'Selected Therapist';
    }

    handlePaymentMethodSelection(radio) {
        console.log('Handling payment method selection:', radio.value);
        
        const paymentItem = radio.closest('.payment-item');
        document.querySelectorAll('input[name="paymentMethod"]').forEach(item => {
            const payItem = item.closest('.payment-item');
            if (payItem) payItem.classList.remove('selected');
        });
        
        if (paymentItem) {
            paymentItem.classList.add('selected');
        }
        this.bookingData.paymentMethod = radio.value;
    }

    setupDatePicker() {
        const dateInput = document.getElementById('booking-date');
        if (!dateInput) return;
        
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);

        dateInput.min = tomorrow.toISOString().split('T')[0];

        const maxDate = new Date(today);
        maxDate.setMonth(maxDate.getMonth() + 3);
        dateInput.max = maxDate.toISOString().split('T')[0];
    }

    generateTimeSlots() {
        const timeSlotsContainer = document.getElementById('time-slots');
        if (!timeSlotsContainer) return;
        
        const timeSlots = [
            '9:00 AM', '10:00 AM', 
            '11:00 AM', '1:00 PM',
            '2:00 PM','3:00 PM',
            '4:00 PM', '5:00 PM',
            '6:00 PM', '7:00 PM'
        ];

        timeSlotsContainer.innerHTML = '';

        timeSlots.forEach(time => {
            const timeSlot = document.createElement('div');
            timeSlot.className = 'time-slot';
            timeSlot.textContent = time;
            timeSlot.addEventListener('click', () => this.selectTimeSlot(timeSlot));
            timeSlotsContainer.appendChild(timeSlot);
        });
    }

    selectTimeSlot(timeSlot) {
        console.log('Time slot clicked:', timeSlot.textContent);
        
        if (timeSlot.classList.contains('unavailable')) {
            console.log('Time slot unavailable');
            return;
        }

        document.querySelectorAll('.time-slot').forEach(slot => {
            slot.classList.remove('selected');
        });

        timeSlot.classList.add('selected');
        this.checkTherapistTimeAvailability();
    }

    convertTo12Hour(time24) {
        if (!time24) return '';
        
        const [hours, minutes] = time24.split(':');
        const hour12 = parseInt(hours) % 12 || 12;
        const period = parseInt(hours) >= 12 ? 'PM' : 'AM';
        const minuteStr = minutes === '00' ? '' : `:${minutes}`;
        return `${hour12}${minuteStr} ${period}`;
    }

    async handleDateChange(selectedDate) {
        if (!selectedDate) return;
        
        document.querySelectorAll('.time-slot').forEach(slot => {
            slot.classList.remove('selected', 'unavailable', 'therapist-unavailable');
            slot.removeAttribute('title');
        });
        
        await this.updateTimeSlotAvailability(selectedDate);
        this.updateBookingSummary();
    }

    async updateTimeSlotAvailability(date) {
        try {
            const response = await fetch('check_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ date: date })
            });
            
            if (!response.ok) {
                console.error('Server returned error:', response.status, response.statusText);
                return;
            }
            
            const responseText = await response.text();
            console.log('Server response:', responseText);
            
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse server response:', parseError);
                console.error('Response was:', responseText);
                return;
            }
            
            if (result.error) {
                console.error('Server error:', result.message);
                this.showAlert('Unable to check availability: ' + result.message, 'error');
                return;
            }
            
            this.therapistAvailability = result.timeSlotAvailability || {};
            
            const timeSlots = document.querySelectorAll('.time-slot');
            timeSlots.forEach(slot => {
                const slotTime = slot.textContent.trim();
                const time24Hour = this.convertTo24Hour(slotTime);
                
                const bookedTherapists = this.therapistAvailability[time24Hour] || [];
                const maxTherapists = 5;
                
                if (bookedTherapists.length >= maxTherapists) {
                    slot.classList.add('unavailable');
                    slot.title = 'All therapists are booked at this time';
                } else {
                    slot.classList.remove('unavailable');
                    slot.title = '';
                }
            });
            
        } catch (error) {
            console.error('Error fetching availability:', error);
            this.showAlert('Unable to check time slot availability. Please try selecting a different date.', 'error');
        }
    }

    convertTo24Hour(time12) {
        const time = time12.trim();
        
        if (time.match(/^(\d{1,2}):?(\d{0,2})\s*(AM|PM)$/i)) {
            const matches = time.match(/^(\d{1,2}):?(\d{0,2})\s*(AM|PM)$/i);
            let hour = parseInt(matches[1]);
            const minute = matches[2] ? parseInt(matches[2]) : 0;
            const period = matches[3].toUpperCase();
            
            if (period === 'AM' && hour === 12) {
                hour = 0;
            } else if (period === 'PM' && hour !== 12) {
                hour += 12;
            }
            
            return `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}:00`;
        }
        
        return time;
    }

    async confirmBooking() {
        if (!this.validateCurrentStep()) {
            return;
        }

        this.showLoadingOverlay();

        try {
            const therapistsToCheck = [this.bookingData.therapist.id];
            if (this.bookingData.roomType === 'couples' && this.bookingData.therapist2) {
                therapistsToCheck.push(this.bookingData.therapist2.id);
            }

            const availabilityCheck = await fetch('check_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    date: this.bookingData.dateTime.date,
                    time: this.bookingData.dateTime.time,
                    therapists: therapistsToCheck
                })
            });
            
            if (availabilityCheck.ok) {
                const responseText = await availabilityCheck.text();
                
                let availabilityData;
                try {
                    availabilityData = JSON.parse(responseText);
                } catch (parseError) {
                    console.warn('Could not parse availability response, proceeding with booking');
                    availabilityData = null;
                }
                
                if (availabilityData && availabilityData.unavailableTherapists && availabilityData.unavailableTherapists.length > 0) {
                    const names = availabilityData.unavailableTherapists.map(id => this.getTherapistName(id)).join(' and ');
                    throw new Error(`Sorry, ${names} ${availabilityData.unavailableTherapists.length === 1 ? 'is' : 'are'} not available at ${this.bookingData.dateTime.time}. Please select different therapist(s) or time slot.`);
                }
            }

            const bookingData = {
                fullName: this.bookingData.personalInfo.fullName,
                email: this.bookingData.personalInfo.email,
                phone: this.bookingData.personalInfo.phone,
                serviceType: this.bookingData.service.type,
                serviceName: this.bookingData.service.name,
                servicePrice: this.bookingData.service.price,
                serviceDuration: this.bookingData.service.duration,
                bookingDate: this.bookingData.dateTime.date,
                bookingTime: this.bookingData.dateTime.time,
                roomType: this.bookingData.roomType,
                therapistId: this.bookingData.therapist.id,
                therapistId2: this.bookingData.roomType === 'couples' && this.bookingData.therapist2 ? 
                             this.bookingData.therapist2.id : null,
                paymentMethod: this.bookingData.paymentMethod,
                healthInfo: this.bookingData.healthInfo
            };

            console.log('Sending booking data:', bookingData);

            const response = await fetch('process_booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(bookingData)
            });

            const rawResponse = await response.text();
            console.log('Raw server response:', rawResponse);

            let result;
            try {
                result = JSON.parse(rawResponse);
            } catch (e) {
                console.error('Failed to parse server response:', rawResponse);
                throw new Error('Server returned invalid response. Please try again.');
            }

            if (!response.ok) {
                throw new Error(result.message || `Server returned error ${response.status}`);
            }
            
            if (result.success) {
                this.bookingData.reference = result.bookingReference;
                this.processBookingConfirmation(result.bookingReference);
                this.goToStep(4);
                this.showAlert('Booking request submitted! Awaiting confirmation from our team.', 'success');
            } else {
                throw new Error(result.message || 'Failed to process booking');
            }
            
        } catch (error) {
            console.error('Booking error:', error);
            this.showAlert(error.message || 'Error processing booking', 'error');
            
            if (error.message.includes('not available')) {
                await this.updateTimeSlotAvailability(this.bookingData.dateTime.date);
            }
        } finally {
            this.hideLoadingOverlay();
        }
    }

    processBookingConfirmation(bookingReference) {
        const refElement = document.getElementById('booking-reference');
        const emailElement = document.getElementById('confirmation-email');
        
        if (refElement) {
            refElement.textContent = bookingReference;
        }
        if (emailElement) {
            emailElement.textContent = this.bookingData.personalInfo.email;
        }
    }

    updateFinalSummary() {
        const serviceElement = document.getElementById('final-service');
        const roomElement = document.getElementById('final-room');
        const datetimeElement = document.getElementById('final-datetime');
        const therapistElement = document.getElementById('final-therapist');
        const priceElement = document.getElementById('final-price');
        
        if (serviceElement) {
            serviceElement.textContent = this.bookingData.service.name || '-';
        }
        if (roomElement) {
            roomElement.textContent = this.getRoomDisplayName() || '-';
        }
        if (datetimeElement) {
            datetimeElement.textContent = 
                `${this.bookingData.dateTime.formattedDate} at ${this.bookingData.dateTime.time}` || '-';
        }
        if (therapistElement) {
            let therapistText = this.bookingData.therapist.name || '-';
            if (this.bookingData.roomType === 'couples' && this.bookingData.therapist2) {
                therapistText += ` & ${this.bookingData.therapist2.name}`;
            }
            therapistElement.textContent = therapistText;
        }
        if (priceElement) {
            priceElement.textContent = `${this.bookingData.totalPrice}`;
        }
        
        // Update the confirmation message to reflect pending status
        const confirmationCard = document.querySelector('.confirmation-card');
        if (confirmationCard) {
            const heading = confirmationCard.querySelector('h3');
            const message = confirmationCard.querySelector('p');
            
            if (heading) {
                heading.innerHTML = 'Booking Request Submitted!';
                heading.style.color = '#856404';
            }
            if (message) {
                message.textContent = 'Your booking request has been received and is pending confirmation. You will receive a confirmation email once our team reviews and approves your booking.';
            }
        }
        
        this.updateFinalHealthSummary();
    }

    updateFinalHealthSummary() {
        const healthInfo = this.bookingData.healthInfo;
        
        const finalMedicalElement = document.getElementById('final-medical-conditions');
        if (finalMedicalElement) {
            if (healthInfo.medicalConditions && healthInfo.medicalConditions.length > 0) {
                const conditions = healthInfo.medicalConditions.map(condition => {
                    switch (condition) {
                        case 'heart': return 'Heart Condition / High Blood Pressure';
                        case 'diabetes': return 'Diabetes';
                        case 'surgery': return 'Recent Surgery / Injury';
                        case 'skin': return 'Skin Infection / Open Wounds';
                        case 'none': return 'None reported';
                        default: return condition;
                    }
                });
                finalMedicalElement.textContent = conditions.join(', ');
            } else {
                finalMedicalElement.textContent = 'None reported';
            }
        }

        const finalPregnancyElement = document.getElementById('final-pregnancy');
        if (finalPregnancyElement) {
            if (healthInfo.pregnancy === 'yes') {
                const weeks = healthInfo.pregnancyWeeks ? ` (${healthInfo.pregnancyWeeks} weeks)` : '';
                finalPregnancyElement.textContent = `Yes${weeks}`;
            } else if (healthInfo.pregnancy === 'no') {
                finalPregnancyElement.textContent = 'No';
            } else {
                finalPregnancyElement.textContent = 'Not specified';
            }
        }

        const finalAllergiesElement = document.getElementById('final-allergies');
        if (finalAllergiesElement) {
            if (healthInfo.allergies && healthInfo.allergies.length > 0) {
                const allergies = healthInfo.allergies.map(allergy => {
                    switch (allergy) {
                        case 'oils': return 'Massage Oils / Lotions';
                        case 'essential': return 'Essential Oils';
                        case 'other': return healthInfo.otherAllergy ? `Other: ${healthInfo.otherAllergy}` : 'Other (not specified)';
                        case 'none': return 'None reported';
                        default: return allergy;
                    }
                });
                finalAllergiesElement.textContent = allergies.join(', ');
            } else {
                finalAllergiesElement.textContent = 'None reported';
            }
        }

        const finalIllnessElement = document.getElementById('final-illness');
        if (finalIllnessElement) {
            if (healthInfo.currentlyIll === 'yes') {
                finalIllnessElement.textContent = 'Currently experiencing illness symptoms';
                finalIllnessElement.style.color = '#dc3545';
            } else if (healthInfo.currentlyIll === 'no') {
                finalIllnessElement.textContent = 'No current illness reported';
                finalIllnessElement.style.color = '#28a745';
            } else {
                finalIllnessElement.textContent = 'Not specified';
            }
        }
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        return date.toLocaleDateString('en-US', options);
    }

    showLoadingOverlay() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.display = 'flex';
        }
    }

    hideLoadingOverlay() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    showAlert(message, type = 'info') {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            min-width: 300px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `;

        switch (type) {
            case 'error':
                alert.style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';
                break;
            case 'success':
                alert.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
                break;
            default:
                alert.style.background = 'linear-gradient(135deg, #17a2b8 0%, #138496 100%)';
        }

        alert.textContent = message;
        document.body.appendChild(alert);

        setTimeout(() => {
            alert.style.transform = 'translateX(0)';
        }, 100);

        setTimeout(() => {
            alert.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 300);
        }, 4000);
    }

    updateBookingSummary() {
        const selectedService = document.querySelector('input[name="service"]:checked');
        const selectedRoom = document.querySelector('input[name="roomType"]:checked');

        if (selectedService) {
            const summaryServiceElement = document.getElementById('summary-service');
            if (summaryServiceElement) {
                summaryServiceElement.textContent = this.bookingData.service.name;
            }
        }

        if (selectedRoom) {
            const summaryRoomElement = document.getElementById('summary-room');
            if (summaryRoomElement) {
                summaryRoomElement.textContent = this.getRoomDisplayName();
            }
        }

        if (this.bookingData.dateTime.date && this.bookingData.dateTime.time) {
            const summaryDatetimeElement = document.getElementById('summary-datetime');
            if (summaryDatetimeElement) {
                summaryDatetimeElement.textContent = 
                    `${this.bookingData.dateTime.formattedDate} at ${this.bookingData.dateTime.time}`;
            }
        }

        if (this.bookingData.therapist.name) {
            const summaryTherapistElement = document.getElementById('summary-therapist');
            if (summaryTherapistElement) {
                let therapistText = this.bookingData.therapist.name;
                
                if (this.bookingData.roomType === 'couples' && this.bookingData.therapist2) {
                    therapistText += ` & ${this.bookingData.therapist2.name}`;
                }
                
                summaryTherapistElement.textContent = therapistText;
            }
        }

        this.calculateTotalPrice();
        const summaryPriceElement = document.getElementById('summary-price');
        if (summaryPriceElement) {
            summaryPriceElement.textContent = `${this.bookingData.totalPrice}`;
        }
        
        this.updateHealthSummary();
    }

    updateHealthSummary() {
        if (!this.bookingData.healthInfo || Object.keys(this.bookingData.healthInfo).length === 0) {
            this.collectHealthInfo();
        }

        const healthInfo = this.bookingData.healthInfo;
        
        const medicalElement = document.getElementById('summary-medical-conditions');
        if (medicalElement) {
            if (healthInfo.medicalConditions && healthInfo.medicalConditions.length > 0) {
                const conditions = healthInfo.medicalConditions.map(condition => {
                    switch (condition) {
                        case 'heart': return 'Heart Condition / High Blood Pressure';
                        case 'diabetes': return 'Diabetes';
                        case 'surgery': return 'Recent Surgery / Injury';
                        case 'skin': return 'Skin Infection / Open Wounds';
                        case 'none': return 'None reported';
                        default: return condition;
                    }
                });
                medicalElement.textContent = conditions.join(', ');
            } else {
                medicalElement.textContent = 'None reported';
            }
        }

        const pregnancyElement = document.getElementById('summary-pregnancy');
        if (pregnancyElement) {
            if (healthInfo.pregnancy === 'yes') {
                const weeks = healthInfo.pregnancyWeeks ? ` (${healthInfo.pregnancyWeeks} weeks)` : '';
                pregnancyElement.textContent = `Yes${weeks}`;
            } else if (healthInfo.pregnancy === 'no') {
                pregnancyElement.textContent = 'No';
            } else {
                pregnancyElement.textContent = 'Not specified';
            }
        }

        const allergiesElement = document.getElementById('summary-allergies');
        if (allergiesElement) {
            if (healthInfo.allergies && healthInfo.allergies.length > 0) {
                const allergies = healthInfo.allergies.map(allergy => {
                    switch (allergy) {
                        case 'oils': return 'Massage Oils / Lotions';
                        case 'essential': return 'Essential Oils';
                        case 'other': return healthInfo.otherAllergy ? `Other: ${healthInfo.otherAllergy}` : 'Other (not specified)';
                        case 'none': return 'None reported';
                        default: return allergy;
                    }
                });
                allergiesElement.textContent = allergies.join(', ');
            } else {
                allergiesElement.textContent = 'None reported';
            }
        }

        const illnessElement = document.getElementById('summary-illness');
        if (illnessElement) {
            if (healthInfo.currentlyIll === 'yes') {
                illnessElement.textContent = 'Currently experiencing illness symptoms';
                illnessElement.style.color = '#dc3545';
                illnessElement.style.fontWeight = '600';
            } else if (healthInfo.currentlyIll === 'no') {
                illnessElement.textContent = 'No current illness reported';
                illnessElement.style.color = '#28a745';
            } else {
                illnessElement.textContent = 'Not specified';
            }
        }

        this.showHealthAlert();
    }

    showHealthAlert() {
        const healthAlert = document.getElementById('health-alert');
        if (!healthAlert) return;
        
        const healthInfo = this.bookingData.healthInfo;
        let showAlert = false;

        if (healthInfo.medicalConditions && healthInfo.medicalConditions.length > 0) {
            const concerningConditions = ['heart', 'diabetes', 'surgery', 'skin'];
            showAlert = healthInfo.medicalConditions.some(condition => concerningConditions.includes(condition));
        }

        if (healthInfo.pregnancy === 'yes') {
            showAlert = true;
        }

        if (healthInfo.currentlyIll === 'yes') {
            showAlert = true;
        }

        if (healthInfo.allergies && healthInfo.allergies.length > 0 && !healthInfo.allergies.includes('none')) {
            showAlert = true;
        }

        healthAlert.style.display = showAlert ? 'block' : 'none';
    }

    getRoomDisplayName() {
        const roomNames = {
            'single': 'Single Treatment Room',
            'couples': 'Couples Room',
            'vip': 'VIP Suite'
        };
        return roomNames[this.bookingData.roomType] || '';
    }

    calculateTotalPrice() {
        let totalPrice = this.bookingData.service.price || 0;
        
        switch (this.bookingData.roomType) {
            case 'single': totalPrice += 600; break;
            case 'couples': totalPrice += 1000; break;
            case 'vip': totalPrice += 2000; break;
        }
        
        this.bookingData.totalPrice = `₱${totalPrice.toLocaleString()}`;
    }
}
    </script>
</body>

</html>