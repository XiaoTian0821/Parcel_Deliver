<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['rider']);

if (request_method() !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

verify_csrf_token();

$parcelId = int_value($_POST['parcel_id'] ?? 0);
$status = $_POST['status'] ?? 'pending';
$remarks = sanitize_text($_POST['remarks'] ?? '');

if ($parcelId <= 0 || !in_array($status, ['pending', 'out_for_delivery', 'delivered', 'failed_delivery'], true)) {
    json_response(['success' => false, 'message' => 'Invalid parcel update.'], 422);
}

$riderStmt = db()->prepare('SELECT id FROM riders WHERE user_id = :user_id LIMIT 1');
$riderStmt->execute(['user_id' => $_SESSION['user_id']]);
$riderId = (int) ($riderStmt->fetchColumn() ?: 0);

if ($riderId <= 0) {
    json_response(['success' => false, 'message' => 'Rider profile not found.'], 404);
}

$photoPath = null;
if (!empty($_FILES['delivery_photo']['name'])) {
    $upload = compress_and_store_image($_FILES['delivery_photo'], PROOF_UPLOAD_PATH);
    if (!$upload['success']) {
        json_response(['success' => false, 'message' => $upload['message']], 422);
    }

    $photoPath = 'uploads/proof/' . $upload['file_name'];

    $photoStmt = db()->prepare('INSERT INTO delivery_photos (parcel_id, rider_id, photo_path, remarks, captured_at) VALUES (:parcel_id, :rider_id, :photo_path, :remarks, NOW())');
    $photoStmt->execute([
        'parcel_id' => $parcelId,
        'rider_id' => $riderId,
        'photo_path' => $photoPath,
        'remarks' => $remarks,
    ]);
}

$updateParcel = db()->prepare("UPDATE parcels SET status = :status, updated_at = NOW(), delivered_at = CASE WHEN :status = 'delivered' THEN NOW() ELSE delivered_at END WHERE id = :id");
$updateParcel->execute(['status' => $status, 'id' => $parcelId]);

$historyStmt = db()->prepare('INSERT INTO parcel_status_history (parcel_id, rider_id, status, remarks, created_at) VALUES (:parcel_id, :rider_id, :status, :remarks, NOW())');
$historyStmt->execute([
    'parcel_id' => $parcelId,
    'rider_id' => $riderId,
    'status' => $status,
    'remarks' => $remarks,
]);

activity_log((int) $_SESSION['user_id'], 'parcel_status', 'Parcel status updated to ' . $status);

json_response(['success' => true, 'message' => 'Parcel status updated.', 'photo_path' => $photoPath]);
