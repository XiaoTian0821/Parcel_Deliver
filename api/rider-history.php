<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin', 'rider']);

$riderId = int_value($_GET['rider_id'] ?? 0);

if (is_rider() && $riderId <= 0) {
    $stmt = db()->prepare('SELECT id FROM riders WHERE user_id = :user_id LIMIT 1');
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $riderId = (int) ($stmt->fetchColumn() ?: 0);
}

$locationsStmt = db()->prepare(
    'SELECT latitude, longitude, accuracy, speed, heading, recorded_at FROM rider_locations WHERE rider_id = :rider_id ORDER BY recorded_at ASC LIMIT 100'
);
$locationsStmt->execute(['rider_id' => $riderId]);

$parcelStmt = db()->prepare(
    'SELECT parcel_id, status, remarks, created_at FROM parcel_status_history WHERE rider_id = :rider_id ORDER BY created_at DESC LIMIT 50'
);
$parcelStmt->execute(['rider_id' => $riderId]);

json_response([
    'success' => true,
    'locations' => $locationsStmt->fetchAll(),
    'history' => $parcelStmt->fetchAll(),
]);
