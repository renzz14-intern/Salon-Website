<?php
require_once 'config.php';
header('Content-Type: application/json');

try {
    $date = isset($_GET['date']) ? $_GET['date'] : null;
    $time = isset($_GET['time']) ? $_GET['time'] : null;
    $serviceType = isset($_GET['service']) ? $_GET['service'] : null;

    // Simulated therapist data - Replace with actual database query
    $therapists = [
        [
            'id' => 1,
            'name' => 'Sarah Johnson',
            'speciality' => 'Swedish Massage',
            'experience' => '5 years',
            'available' => true
        ],
        [
            'id' => 2,
            'name' => 'Michael Chen',
            'speciality' => 'Deep Tissue',
            'experience' => '8 years',
            'available' => true
        ],
        [
            'id' => 3,
            'name' => 'Emma Wilson',
            'speciality' => 'Thai Massage',
            'experience' => '6 years',
            'available' => true
        ],
        [
            'id' => 4,
            'name' => 'James Rodriguez',
            'speciality' => 'Hot Stone Massage',
            'experience' => '4 years',
            'available' => false
        ]
    ];

    // Filter available therapists
    $available_therapists = array_filter($therapists, function($therapist) {
        return $therapist['available'] === true;
    });

    echo json_encode([
        'success' => true,
        'therapists' => array_values($available_therapists)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
