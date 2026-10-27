<?php
// Output buffering MUST come first — prevents any accidental whitespace/output
// from config.php or other includes from breaking session_start()
ob_start();

// session_start() MUST come before any require/include that might output content
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// Guard: if session has no user_id, redirect to login
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

$user_id      = (int)$_SESSION['user_id'];
$appointments = [];

try {
    $stmt = $conn->prepare("
        SELECT id, booking_reference, service_name, services_json,
               booking_date, booking_time, total_price,
               COALESCE(total_duration, service_duration, 0) AS total_duration,
               payment_method, created_at, status
        FROM   bookings
        WHERE  user_id = ?
        ORDER  BY booking_date DESC, booking_time DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Appointments error: " . $e->getMessage());
}

function fmtDate($d) { return date('F j, Y', strtotime($d)); }
function fmtTime($t) { return date('g:i A',  strtotime($t)); }

function statusBadge($status) {
    $map = [
        'confirmed' => ['bg'=>'#d1fae5','color'=>'#065f46','label'=>'Confirmed'],
        'pending'   => ['bg'=>'#fef3c7','color'=>'#92400e','label'=>'Pending'],
        'completed' => ['bg'=>'#dbeafe','color'=>'#1e40af','label'=>'Completed'],
        'cancelled' => ['bg'=>'#fee2e2','color'=>'#991b1b','label'=>'Cancelled'],
    ];
    $s = $map[$status] ?? $map['pending'];
    return "<span style='background:{$s['bg']};color:{$s['color']};padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;'>{$s['label']}</span>";
}

function statusMessage($status) {
    $msgs = [
        'pending'   => ['icon'=>'🕐','text'=>'Your booking is under review. We will confirm your appointment shortly.','color'=>'#856404','bg'=>'#fffbeb'],
        'confirmed' => ['icon'=>'✅','text'=>'Your appointment is confirmed! Please arrive 10 minutes before your scheduled time.','color'=>'#065f46','bg'=>'#d1fae5'],
        'completed' => ['icon'=>'⭐','text'=>'Thank you for visiting us! We hope you enjoyed your experience.','color'=>'#1e40af','bg'=>'#dbeafe'],
        'cancelled' => ['icon'=>'❌','text'=>'This appointment was cancelled. Please contact us if you have questions.','color'=>'#991b1b','bg'=>'#fee2e2'],
    ];
    $m = $msgs[$status] ?? $msgs['pending'];
    return "<div style='background:{$m['bg']};color:{$m['color']};padding:10px 14px;border-radius:6px;margin-top:10px;font-size:13px;'>{$m['icon']} {$m['text']}</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Levy Branzuela Salon</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="images/Untitled.png">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family:'Poppins',sans-serif;
            background:linear-gradient(135deg,#f5f7fa 0%,#e9ecef 100%);
            min-height:100vh; padding:20px; color:#333;
        }
        .page-header {
            background:linear-gradient(135deg,#f7f8f7 0%,#f19e99 40%);
            padding:1.2rem 2rem; border-radius:12px; margin-bottom:24px;
            display:flex; align-items:center; justify-content:space-between;
            box-shadow:0 4px 20px rgba(0,0,0,.1);
        }
        .page-header h1 { color:white; font-size:1.6rem; font-weight:600; }
        .back-btn {
            background:rgba(255,255,255,.2); color:white; border:none;
            padding:.6rem 1.2rem; border-radius:30px; cursor:pointer;
            font-size:.9rem; font-family:'Poppins',sans-serif;
            display:flex; align-items:center; gap:.5rem;
        }
        .back-btn:hover { background:rgba(255,255,255,.3); }

        .container { max-width:860px; margin:0 auto; }

        /* ── Toast notification ── */
        .toast {
            position:fixed; top:20px; right:20px; z-index:9999;
            padding:14px 20px; border-radius:10px; font-size:.9rem;
            font-family:'Poppins',sans-serif; font-weight:500;
            box-shadow:0 4px 20px rgba(0,0,0,.15);
            display:flex; align-items:center; gap:10px;
            transform:translateY(-80px); opacity:0;
            transition:transform .35s ease, opacity .35s ease;
            max-width:380px;
        }
        .toast.show  { transform:translateY(0); opacity:1; }
        .toast.success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
        .toast.error   { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

        /* ── Appointment cards ── */
        .appt-card {
            background:white; border-radius:12px; padding:22px 24px;
            margin-bottom:18px; box-shadow:0 2px 12px rgba(0,0,0,.07);
            border:1px solid #f0e8e8;
            transition: opacity .4s ease, transform .4s ease;
        }
        .appt-card.cancelling { opacity:.4; pointer-events:none; }

        .appt-top {
            display:flex; justify-content:space-between; align-items:flex-start;
            margin-bottom:14px; flex-wrap:wrap; gap:8px;
        }
        .appt-ref {
            font-family:monospace; background:#fef0ef; color:#c0615d;
            padding:3px 10px; border-radius:6px; font-size:13px; font-weight:600;
        }
        .appt-date { font-size:.95rem; color:#555; margin-bottom:12px; }
        .appt-date strong { color:#333; }

        .services-list { margin-bottom:12px; }
        .services-list-title { font-size:.8rem; text-transform:uppercase; letter-spacing:.05em; color:#999; margin-bottom:6px; }
        .service-line {
            display:flex; justify-content:space-between; align-items:center;
            padding:7px 0; border-bottom:1px solid #f5f5f5; font-size:.9rem;
        }
        .service-line:last-child { border-bottom:none; }
        .service-line .svc-name { color:#444; }
        .service-line .svc-price { color:#f19e99; font-weight:600; }

        .appt-footer {
            display:flex; justify-content:space-between; align-items:center;
            margin-top:14px; padding-top:14px; border-top:1px solid #f0f0f0;
            flex-wrap:wrap; gap:10px;
        }
        .appt-meta { font-size:.82rem; color:#888; }
        .appt-total { font-size:1.1rem; font-weight:700; color:#f19e99; }

        /* ── Cancel button ── */
        .cancel-btn {
            background:#fff1f1; color:#dc2626; border:1.5px solid #fca5a5;
            padding:.45rem 1.1rem; border-radius:30px; cursor:pointer;
            font-size:.82rem; font-family:'Poppins',sans-serif; font-weight:600;
            display:inline-flex; align-items:center; gap:6px;
            transition:background .2s, border-color .2s;
        }
        .cancel-btn:hover { background:#fee2e2; border-color:#dc2626; }

        /* ── Confirmation Modal ── */
        .modal-overlay {
            display:none; position:fixed; top:0; left:0;
            width:100%; height:100%; background:rgba(0,0,0,.45);
            z-index:1000; align-items:center; justify-content:center;
        }
        .modal-overlay.active { display:flex; }
        .modal-box {
            background:white; border-radius:14px; padding:2rem;
            max-width:420px; width:90%; box-shadow:0 8px 40px rgba(0,0,0,.18);
            animation:popIn .25s ease;
        }
        @keyframes popIn {
            from { transform:scale(.9); opacity:0; }
            to   { transform:scale(1);  opacity:1; }
        }
        .modal-icon { font-size:2.5rem; text-align:center; margin-bottom:.8rem; }
        .modal-title {
            font-size:1.1rem; font-weight:700; color:#333;
            text-align:center; margin-bottom:.5rem;
        }
        .modal-body {
            font-size:.88rem; color:#666; text-align:center;
            line-height:1.6; margin-bottom:1.4rem;
        }
        .modal-ref {
            font-family:monospace; background:#fef0ef; color:#c0615d;
            padding:2px 8px; border-radius:5px; font-weight:700;
        }
        .modal-actions { display:flex; gap:10px; justify-content:center; }
        .modal-actions button {
            padding:.6rem 1.6rem; border-radius:30px; border:none;
            font-family:'Poppins',sans-serif; font-size:.88rem; font-weight:600;
            cursor:pointer; transition:background .2s;
        }
        .btn-confirm-cancel {
            background:#dc2626; color:white;
        }
        .btn-confirm-cancel:hover { background:#b91c1c; }
        .btn-keep {
            background:#f0f0f0; color:#555;
        }
        .btn-keep:hover { background:#e0e0e0; }

        /* ── Empty state ── */
        .empty-state {
            background:white; border-radius:12px; padding:60px 20px; text-align:center;
            box-shadow:0 2px 12px rgba(0,0,0,.07);
        }
        .empty-state i { font-size:3rem; color:#f19e99; margin-bottom:16px; }
        .empty-state h3 { color:#555; margin-bottom:8px; }
        .empty-state p  { color:#999; }
        .book-now-btn {
            display:inline-block; margin-top:20px;
            background:linear-gradient(135deg,#f7f8f7 0%,#f19e99 40%);
            color:white; padding:.8rem 2rem; border-radius:30px;
            text-decoration:none; font-weight:500; font-size:.95rem;
        }
    </style>
</head>
<body>

    <!-- Toast notification -->
    <div class="toast" id="toast">
        <i class="fas fa-check-circle" id="toastIcon"></i>
        <span id="toastMsg"></span>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div class="modal-overlay" id="cancelModal">
        <div class="modal-box">
            <div class="modal-title">Cancel Appointment?</div>
            <div class="modal-body">
                Are you sure you want to cancel booking
                <span class="modal-ref" id="modalRef"></span>?<br>
                This action <strong>cannot be undone</strong>.
            </div>
            <div class="modal-actions">
                <button class="btn-keep" onclick="closeModal()">
                    <i class="fas fa-arrow-left"></i> Keep It
                </button>
                <button class="btn-confirm-cancel" id="confirmCancelBtn">
                    <i class="fas fa-times-circle"></i> Yes, Cancel
                </button>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-calendar-check" style="margin-right:10px;"></i>My Appointments</h1>
            <button class="back-btn" onclick="window.location.href='index.html'">
                <i class="fas fa-arrow-left"></i> Back to Home
            </button>
        </div>

        <?php if (empty($appointments)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Appointments Yet</h3>
                <p>You haven't made any bookings yet. Ready to treat yourself?</p>
                <a href="booking.php" class="book-now-btn">Book an Appointment</a>
            </div>
        <?php else: ?>
            <?php foreach ($appointments as $apt): ?>
                <?php
                    $servicesData = !empty($apt['services_json'])
                        ? json_decode($apt['services_json'], true)
                        : null;
                    $cancellable = ($apt['status'] ?? 'pending') === 'pending';
                ?>
                <div class="appt-card" id="card-<?php echo (int)$apt['id']; ?>">
                    <div class="appt-top">
                        <span class="appt-ref"><?php echo htmlspecialchars($apt['booking_reference']); ?></span>
                        <span id="badge-<?php echo (int)$apt['id']; ?>">
                            <?php echo statusBadge($apt['status'] ?? 'pending'); ?>
                        </span>
                    </div>

                    <div class="appt-date">
                        <strong><?php echo fmtDate($apt['booking_date']); ?></strong>
                        at <strong><?php echo fmtTime($apt['booking_time']); ?></strong>
                        &nbsp;·&nbsp; <?php echo (int)$apt['total_duration']; ?> mins total
                    </div>

                    <!-- Services list -->
                    <div class="services-list">
                        <div class="services-list-title">Services</div>
                        <?php if ($servicesData && is_array($servicesData)): ?>
                            <?php foreach ($servicesData as $svc): ?>
                                <div class="service-line">
                                    <span class="svc-name"><?php echo htmlspecialchars($svc['name']); ?></span>
                                    <span class="svc-price">₱<?php echo number_format($svc['price'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="service-line">
                                <span class="svc-name"><?php echo htmlspecialchars($apt['service_name']); ?></span>
                                <span class="svc-price">₱<?php echo number_format($apt['total_price'], 2); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Status message -->
                    <div id="statusMsg-<?php echo (int)$apt['id']; ?>">
                        <?php echo statusMessage($apt['status'] ?? 'pending'); ?>
                    </div>

                    <div class="appt-footer">
                        <span class="appt-meta">
                            <i class="fas fa-money-bill-wave" style="color:#f19e99;margin-right:4px;"></i>
                            Pay at Salon &nbsp;·&nbsp;
                            Booked <?php echo fmtDate($apt['created_at']); ?>
                        </span>
                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <?php if ($cancellable): ?>
                                <button
                                    class="cancel-btn"
                                    onclick="openCancelModal(<?php echo (int)$apt['id']; ?>, '<?php echo htmlspecialchars($apt['booking_reference'], ENT_QUOTES); ?>')"
                                >
                                    <i class="fas fa-times-circle"></i> Cancel Appointment
                                </button>
                            <?php endif; ?>
                            <span class="appt-total">₱<?php echo number_format($apt['total_price'], 2); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<script>
    // ── Modal state ──────────────────────────────────────────────────────────
    let pendingBookingId  = null;
    let pendingBookingRef = null;

    function openCancelModal(bookingId, bookingRef) {
        pendingBookingId  = bookingId;
        pendingBookingRef = bookingRef;
        document.getElementById('modalRef').textContent = bookingRef;
        document.getElementById('cancelModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('cancelModal').classList.remove('active');
        pendingBookingId  = null;
        pendingBookingRef = null;
    }

    // Close modal if user clicks the overlay background
    document.getElementById('cancelModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // ── Confirm cancellation ─────────────────────────────────────────────────
    document.getElementById('confirmCancelBtn').addEventListener('click', function () {
        if (!pendingBookingId) return;

        const bookingId = pendingBookingId;
        closeModal();

        // Grey out the card while the request is in flight
        const card = document.getElementById('card-' + bookingId);
        if (card) card.classList.add('cancelling');

        const formData = new FormData();
        formData.append('booking_id', bookingId);

        fetch('cancel_booking.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (card) card.classList.remove('cancelling');

            if (data.success) {
                // Update the badge and status message in-place
                const badge = document.getElementById('badge-' + bookingId);
                if (badge) {
                    badge.innerHTML = "<span style='background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;'>Cancelled</span>";
                }

                const statusMsg = document.getElementById('statusMsg-' + bookingId);
                if (statusMsg) {
                    statusMsg.innerHTML = "<div style='background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:6px;margin-top:10px;font-size:13px;'>❌ This appointment was cancelled. Please contact us if you have questions.</div>";
                }

                // Remove the cancel button since it's no longer needed
                const cancelBtn = card ? card.querySelector('.cancel-btn') : null;
                if (cancelBtn) cancelBtn.remove();

                showToast(data.message, 'success');
            } else {
                showToast(data.message || 'Could not cancel the booking. Please try again.', 'error');
            }
        })
        .catch(() => {
            if (card) card.classList.remove('cancelling');
            showToast('A network error occurred. Please try again.', 'error');
        });
    });

    // ── Toast helper ─────────────────────────────────────────────────────────
    let toastTimer = null;
    function showToast(message, type) {
        const toast   = document.getElementById('toast');
        const icon    = document.getElementById('toastIcon');
        const msgSpan = document.getElementById('toastMsg');

        toast.classList.remove('success', 'error', 'show');
        icon.className  = 'fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle');
        msgSpan.textContent = message;

        void toast.offsetWidth; // force reflow so transition fires
        toast.classList.add(type, 'show');

        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 5000);
    }
</script>
</body>
</html>