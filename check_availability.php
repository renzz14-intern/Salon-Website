<?php
/**
 * check_availability.php — Levy Branzuela Salon
 *
 * GET  ?date=YYYY-MM-DD
 *      → {"booked": ["10:00:00", "14:00:00", ...]}
 *        Used by booking_script.js to grey out taken time slots.
 *
 * POST (JSON body)
 *      → Legacy / admin therapist-availability checks.
 *        Supports three modes:
 *        1. { date, therapists[], time }  — multi-therapist check (couples room)
 *        2. { date, time, therapist }     — single-therapist check
 *        3. { date }                      — general slot availability map
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require_once 'config.php';

    // ── GET: simple booked-times array for the booking page ──────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $date = trim($_GET['date'] ?? '');

        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['booked' => []]);
            exit;
        }

        $booked = [];

        // Block slots that are pending OR confirmed on this date
        $stmt = $conn->prepare(
            "SELECT booking_time
             FROM   bookings
             WHERE  booking_date = ?
               AND  status IN ('pending', 'confirmed')"
        );
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $t = $row['booking_time'];
            if (strlen($t) === 5) $t .= ':00';   // "10:00" → "10:00:00"
            $booked[] = $t;
        }
        $stmt->close();

        echo json_encode(['booked' => array_values(array_unique($booked))]);
        exit;
    }

    // ── POST: therapist / slot availability checks ────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only GET and POST methods are allowed');
    }

    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) throw new Exception('No data received');

    $data = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }

    if (!isset($data['date']) || empty($data['date'])) {
        throw new Exception('Date is required');
    }

    $selectedDate = mysqli_real_escape_string($conn, trim($data['date']));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
        throw new Exception('Invalid date format');
    }

    // Ensure table + optional columns exist
    $conn->query("
        CREATE TABLE IF NOT EXISTS bookings (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            booking_reference VARCHAR(50)   NOT NULL UNIQUE,
            user_id           INT           NULL,
            full_name         VARCHAR(255)  NOT NULL,
            email             VARCHAR(255)  NOT NULL,
            phone             VARCHAR(20)   NOT NULL,
            service_type      VARCHAR(100)  NOT NULL DEFAULT '',
            service_name      VARCHAR(255)  NOT NULL,
            service_price     DECIMAL(10,2) NOT NULL DEFAULT 0,
            service_duration  INT           NOT NULL DEFAULT 0,
            booking_date      DATE          NOT NULL,
            booking_time      TIME          NOT NULL,
            room_type         VARCHAR(50)   NOT NULL DEFAULT 'salon',
            room_price        DECIMAL(10,2) DEFAULT 0.00,
            total_price       DECIMAL(10,2) DEFAULT 0.00,
            therapist_id      VARCHAR(50)   NULL,
            therapist_id_2    VARCHAR(50)   NULL,
            payment_method    VARCHAR(50)   NOT NULL DEFAULT 'cash',
            health_info       TEXT          NULL,
            status            ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending',
            created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_therapist_id   (therapist_id),
            INDEX idx_therapist_id_2 (therapist_id_2)
        )
    ");

    // Add therapist_id_2 if missing on older installs
    $chkCol = $conn->query("SHOW COLUMNS FROM bookings LIKE 'therapist_id_2'");
    if ($chkCol && $chkCol->num_rows === 0) {
        $conn->query("ALTER TABLE bookings ADD COLUMN therapist_id_2 VARCHAR(50) NULL AFTER therapist_id");
    }

    // ── MODE 1: multi-therapist check (couples room) ──────────────────────────
    if (isset($data['therapists']) && is_array($data['therapists'])) {
        $selectedTime = isset($data['time']) ? trim($data['time']) : null;
        if (!$selectedTime) throw new Exception('Time is required when checking therapist availability');

        $time24Hour  = convertTo24Hour($selectedTime);
        $unavailable = [];

        foreach ($data['therapists'] as $therapistId) {
            if ($therapistId === 'no-preference') continue;

            $tEsc = mysqli_real_escape_string($conn, $therapistId);

            $stmt = $conn->prepare(
                "SELECT id FROM bookings
                 WHERE  booking_date = ?
                   AND  booking_time = ?
                   AND  (therapist_id = ? OR therapist_id_2 = ?)
                   AND  status NOT IN ('cancelled','completed')
                 LIMIT 1"
            );
            $stmt->bind_param('ssss', $selectedDate, $time24Hour, $tEsc, $tEsc);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $unavailable[] = $therapistId;
            $stmt->close();
        }

        echo json_encode([
            'success'               => true,
            'booked'                => count($unavailable) > 0,
            'unavailableTherapists' => $unavailable
        ]);

    // ── MODE 2: single therapist + time check ─────────────────────────────────
    } elseif (isset($data['time']) && isset($data['therapist'])) {
        $selectedTime      = trim($data['time']);
        $selectedTherapist = trim($data['therapist']);
        $time24Hour        = convertTo24Hour($selectedTime);

        if ($selectedTherapist !== 'no-preference') {
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS cnt
                 FROM   bookings
                 WHERE  booking_date = ?
                   AND  booking_time = ?
                   AND  (therapist_id = ? OR therapist_id_2 = ?)
                   AND  status NOT IN ('cancelled','completed')"
            );
            $stmt->bind_param('ssss', $selectedDate, $time24Hour, $selectedTherapist, $selectedTherapist);
        } else {
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS cnt
                 FROM   bookings
                 WHERE  booking_date = ?
                   AND  booking_time = ?
                   AND  status NOT IN ('cancelled','completed')"
            );
            $stmt->bind_param('ss', $selectedDate, $time24Hour);
        }

        $stmt->execute();
        $row      = $stmt->get_result()->fetch_assoc();
        $maxSlots = ($selectedTherapist === 'no-preference') ? 5 : 1;
        $isBooked = ($row['cnt'] >= $maxSlots);
        $stmt->close();

        echo json_encode([
            'date'      => $selectedDate,
            'time'      => $selectedTime,
            'therapist' => $selectedTherapist,
            'available' => !$isBooked,
            'booked'    => $isBooked
        ]);

    // ── MODE 3: general date slot map ─────────────────────────────────────────
    } else {
        $stmt = $conn->prepare(
            "SELECT booking_time, service_name, full_name, therapist_id, therapist_id_2
             FROM   bookings
             WHERE  booking_date = ?
               AND  status NOT IN ('cancelled','completed')
             ORDER  BY booking_time"
        );
        $stmt->bind_param('s', $selectedDate);
        $stmt->execute();
        $result = $stmt->get_result();

        $bookedSlots          = [];
        $timeSlotAvailability = [];

        while ($row = $result->fetch_assoc()) {
            $time = $row['booking_time'];

            $bookedSlots[] = [
                'booking_time'   => $time,
                'service_name'   => $row['service_name'],
                'client_name'    => $row['full_name'],
                'therapist_id'   => $row['therapist_id'],
                'therapist_id_2' => $row['therapist_id_2']
            ];

            if (!isset($timeSlotAvailability[$time])) $timeSlotAvailability[$time] = [];

            if (!empty($row['therapist_id']) && $row['therapist_id'] !== 'no-preference') {
                $timeSlotAvailability[$time][] = $row['therapist_id'];
            }
            if (!empty($row['therapist_id_2'])) {
                $timeSlotAvailability[$time][] = $row['therapist_id_2'];
            }
        }
        $stmt->close();

        echo json_encode([
            'success'              => true,
            'date'                 => $selectedDate,
            'bookedSlots'          => $bookedSlots,
            'timeSlotAvailability' => $timeSlotAvailability
        ]);
    }

} catch (Exception $e) {
    error_log('Availability check error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}

// ── Helper ────────────────────────────────────────────────────────────────────
function convertTo24Hour($time12) {
    $time = trim($time12);

    // 12-hour: "9:00 AM", "2:30 PM", "9 AM"
    if (preg_match('/^(\d{1,2}):?(\d{0,2})\s*(AM|PM)$/i', $time, $m)) {
        $h   = intval($m[1]);
        $min = $m[2] !== '' ? intval($m[2]) : 0;
        $per = strtoupper($m[3]);
        if ($per === 'AM' && $h === 12) $h = 0;
        elseif ($per === 'PM' && $h !== 12) $h += 12;
        return sprintf('%02d:%02d:00', $h, $min);
    }

    // Already HH:MM:SS
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) return $time;

    // HH:MM — pad seconds
    if (preg_match('/^\d{2}:\d{2}$/', $time)) return $time . ':00';

    return '00:00:00';
}
?>