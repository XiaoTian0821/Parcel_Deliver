<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin']);

if (request_method() !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

verify_csrf_token();

$action = $_POST['action'] ?? 'save';
$parcelId = int_value($_POST['parcel_id'] ?? 0);

if ($action === 'delete') {
    if ($parcelId <= 0) {
        json_response(['success' => false, 'message' => 'Parcel ID is required.'], 422);
    }

    $stmt = db()->prepare('DELETE FROM parcels WHERE id = :id');
    $stmt->execute(['id' => $parcelId]);
    activity_log((int) $_SESSION['user_id'], 'parcel_delete', 'Parcel deleted: ' . $parcelId);
    json_response(['success' => true, 'message' => 'Parcel deleted successfully.']);
}

$recipientName = sanitize_text($_POST['recipient_name'] ?? '');
$recipientPhone = sanitize_text($_POST['recipient_phone'] ?? '');
$deliveryAddress = sanitize_text($_POST['delivery_address'] ?? '');
$city = sanitize_text($_POST['city'] ?? '');
$state = sanitize_text($_POST['state'] ?? '');
$postalCode = sanitize_text($_POST['postal_code'] ?? '');
$pickupAddress = sanitize_text($_POST['pickup_address'] ?? '');
$instructions = sanitize_text($_POST['delivery_instructions'] ?? '');
$status = in_array($_POST['status'] ?? 'pending', ['pending', 'out_for_delivery', 'delivered', 'failed_delivery'], true) ? $_POST['status'] : 'pending';
$assignedRiderId = int_value($_POST['assigned_rider_id'] ?? 0);

if ($recipientName === '' || $recipientPhone === '' || $deliveryAddress === '') {
    json_response(['success' => false, 'message' => 'Recipient name, phone, and delivery address are required.'], 422);
}

if ($parcelId > 0) {
    $stmt = db()->prepare(
        'UPDATE parcels SET recipient_name = :recipient_name, recipient_phone = :recipient_phone, delivery_address = :delivery_address, city = :city, state = :state, postal_code = :postal_code, pickup_address = :pickup_address, delivery_instructions = :delivery_instructions, status = :status, assigned_rider_id = :assigned_rider_id, updated_at = NOW() WHERE id = :id'
    );
    $stmt->execute([
        'recipient_name' => $recipientName,
        'recipient_phone' => $recipientPhone,
        'delivery_address' => $deliveryAddress,
        'city' => $city,
        'state' => $state,
        'postal_code' => $postalCode,
        'pickup_address' => $pickupAddress,
        'delivery_instructions' => $instructions,
        'status' => $status,
        'assigned_rider_id' => $assignedRiderId > 0 ? $assignedRiderId : null,
        'id' => $parcelId,
    ]);

    activity_log((int) $_SESSION['user_id'], 'parcel_update', 'Parcel updated: ' . $parcelId);
    json_response(['success' => true, 'message' => 'Parcel updated successfully.']);
}

$stmt = db()->prepare(
    'INSERT INTO parcels (tracking_number, recipient_name, recipient_phone, delivery_address, city, state, postal_code, pickup_address, delivery_instructions, status, assigned_rider_id, created_at, updated_at) VALUES (:tracking_number, :recipient_name, :recipient_phone, :delivery_address, :city, :state, :postal_code, :pickup_address, :delivery_instructions, :status, :assigned_rider_id, NOW(), NOW())'
);
$stmt->execute([
    'tracking_number' => generate_tracking_number(),
    'recipient_name' => $recipientName,
    'recipient_phone' => $recipientPhone,
    'delivery_address' => $deliveryAddress,
    'city' => $city,
    'state' => $state,
    'postal_code' => $postalCode,
    'pickup_address' => $pickupAddress,
    'delivery_instructions' => $instructions,
    'status' => $status,
    'assigned_rider_id' => $assignedRiderId > 0 ? $assignedRiderId : null,
]);

activity_log((int) $_SESSION['user_id'], 'parcel_create', 'New parcel created');
json_response(['success' => true, 'message' => 'Parcel created successfully.']);
