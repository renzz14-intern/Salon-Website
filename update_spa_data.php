<?php
require_once 'config.php';

// First, execute the create tables SQL
$createTablesSql = file_get_contents('database/create_spa_tables.sql');
if ($conn->multi_query($createTablesSql)) {
    do {
        // Process all results to free the connection for next query
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
}

// Clear any existing data
$conn->query("DELETE FROM therapists");
$conn->query("DELETE FROM services");

// Reset auto increment
$conn->query("ALTER TABLE therapists AUTO_INCREMENT = 1");
$conn->query("ALTER TABLE services AUTO_INCREMENT = 1");

// Insert therapists
$therapists = [
    ['Sarah Johnson', 'Swedish & Aromatherapy Massage'],
    ['Michael Chen', 'Deep Tissue & Thai Massage'],
    ['Emma Wilson', 'Hot Stone & Reflexology'],
    ['David Rodriguez', 'Sports & Deep Tissue Massage'],
    ['Lisa Patel', 'Aromatherapy & Swedish Massage']
];

$stmt = $conn->prepare("INSERT INTO therapists (name, specialty) VALUES (?, ?)");
foreach ($therapists as $therapist) {
    $stmt->bind_param("ss", $therapist[0], $therapist[1]);
    $stmt->execute();
}
$stmt->close();

// Insert services
$services = [
    ['Swedish Massage', 'massage', 'Gentle, flowing strokes that release tension and improve circulation.', 80.00, 60],
    ['Deep Tissue Massage', 'massage', 'Targeted therapy for deeper muscle layers and connective tissue.', 95.00, 75],
    ['Hot Stone Massage', 'massage', 'Heated stones to melt away stress and tension.', 120.00, 90],
    ['Aromatherapy Massage', 'massage', 'Essential oils combined with therapeutic touch.', 100.00, 75],
    ['Thai Massage', 'massage', 'Yoga stretches and acupressure for total body wellness.', 110.00, 75],
    ['Reflexology', 'massage', 'Healing through targeted pressure points on your feet.', 90.00, 60]
];

$stmt = $conn->prepare("INSERT INTO services (name, type, description, price, duration) VALUES (?, ?, ?, ?, ?)");
foreach ($services as $service) {
    $stmt->bind_param("sssdi", $service[0], $service[1], $service[2], $service[3], $service[4]);
    $stmt->execute();
}
$stmt->close();

echo "Database tables created and populated successfully!";
$conn->close();
?>
