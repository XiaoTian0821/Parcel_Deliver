<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['rider']);

if (request_method() !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

verify_csrf_token();

$latitude = float_value($_POST['latitude'] ?? null);
$longitude = float_value($_POST['longitude'] ?? null);

if ($latitude === null || $longitude === null) {
    json_response(['success' => false, 'message' => 'Latitude and longitude are required.'], 422);
}

$accuracy = float_value($_POST['accuracy'] ?? null);
$speed = float_value($_POST['speed'] ?? null);
$heading = float_value($_POST['heading'] ?? null);

$riderStmt = db()->prepare('SELECT id FROM riders WHERE user_id = :user_id LIMIT 1');
$riderStmt->execute(['user_id' => $_SESSION['user_id']]);
$riderId = (int) ($riderStmt->fetchColumn() ?: 0);

if ($riderId <= 0) {
    json_response(['success' => false, 'message' => 'Rider profile not found.'], 404);
}

$insertLocation = db()->prepare(
    'INSERT INTO rider_locations (rider_id, latitude, longitude, accuracy, speed, heading, recorded_at) VALUES (:rider_id, :latitude, :longitude, :accuracy, :speed, :heading, NOW())'
);
$insertLocation->execute([
    'rider_id' => $riderId,
    'latitude' => $latitude,
    'longitude' => $longitude,
    'accuracy' => $accuracy,
    'speed' => $speed,
    'heading' => $heading,
]);

$updateRider = db()->prepare(
    'UPDATE riders SET current_latitude = :latitude, current_longitude = :longitude, last_location_update = NOW(), updated_at = NOW() WHERE id = :id'
);
$updateRider->execute([
    'latitude' => $latitude,
    'longitude' => $longitude,
    'id' => $riderId,
]);

activity_log((int) $_SESSION['user_id'], 'location_update', 'Rider location updated');

json_response(['success' => true, 'message' => 'Location updated.']);
