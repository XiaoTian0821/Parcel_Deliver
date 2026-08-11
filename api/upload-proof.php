<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['rider']);

if (request_method() !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

verify_csrf_token();

$parcelId = int_value($_POST['parcel_id'] ?? 0);
$remarks = sanitize_text($_POST['remarks'] ?? '');

if ($parcelId <= 0 || empty($_FILES['delivery_photo']['name'])) {
    json_response(['success' => false, 'message' => 'Parcel ID and delivery photo are required.'], 422);
}

$riderStmt = db()->prepare('SELECT id FROM riders WHERE user_id = :user_id LIMIT 1');
$riderStmt->execute(['user_id' => $_SESSION['user_id']]);
$riderId = (int) ($riderStmt->fetchColumn() ?: 0);

$upload = compress_and_store_image($_FILES['delivery_photo'], PROOF_UPLOAD_PATH);
if (!$upload['success']) {
    json_response(['success' => false, 'message' => $upload['message']], 422);
}

$photoPath = 'uploads/proof/' . $upload['file_name'];
$stmt = db()->prepare('INSERT INTO delivery_photos (parcel_id, rider_id, photo_path, remarks, captured_at) VALUES (:parcel_id, :rider_id, :photo_path, :remarks, NOW())');
$stmt->execute([
    'parcel_id' => $parcelId,
    'rider_id' => $riderId,
    'photo_path' => $photoPath,
    'remarks' => $remarks,
]);

activity_log((int) $_SESSION['user_id'], 'proof_upload', 'Delivery proof uploaded');

json_response(['success' => true, 'message' => 'Proof uploaded successfully.', 'photo_path' => $photoPath]);
