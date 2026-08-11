<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_logged_in() || request_method() !== 'POST') {
    json_response(['success' => false, 'message' => 'Unauthorized.'], 401);
}

verify_csrf_token();

$user = current_user();
$fullName = sanitize_text($_POST['full_name'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
$phone = sanitize_text($_POST['phone'] ?? '');
$password = (string) ($_POST['password'] ?? '');
$vehicleType = sanitize_text($_POST['vehicle_type'] ?? '');

if ($fullName === '' || $email === '') {
    json_response(['success' => false, 'message' => 'Full name and email are required.'], 422);
}

$avatarPath = $user['avatar'] ?? null;
if (!empty($_FILES['avatar']['name'])) {
    $upload = compress_and_store_image($_FILES['avatar'], AVATAR_UPLOAD_PATH);
    if (!$upload['success']) {
        json_response(['success' => false, 'message' => $upload['message']], 422);
    }

    $avatarPath = 'uploads/avatars/' . $upload['file_name'];
}

$params = [
    'full_name' => $fullName,
    'email' => $email,
    'phone' => $phone,
    'avatar' => $avatarPath,
    'id' => $user['id'],
];

$sql = 'UPDATE users SET full_name = :full_name, email = :email, phone = :phone, avatar = :avatar, updated_at = NOW()';

if ($password !== '') {
    if (strlen($password) < 8) {
        json_response(['success' => false, 'message' => 'Password must be at least 8 characters.'], 422);
    }

    $sql .= ', password_hash = :password_hash';
    $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
}

$sql .= ' WHERE id = :id';

$stmt = db()->prepare($sql);
$stmt->execute($params);

if (is_rider()) {
    $riderStmt = db()->prepare('SELECT id FROM riders WHERE user_id = :user_id LIMIT 1');
    $riderStmt->execute(['user_id' => $user['id']]);
    $riderId = (int) ($riderStmt->fetchColumn() ?: 0);

    if ($riderId > 0) {
        $updateRider = db()->prepare('UPDATE riders SET vehicle_type = :vehicle_type, updated_at = NOW() WHERE id = :id');
        $updateRider->execute(['vehicle_type' => $vehicleType, 'id' => $riderId]);
    }
}

activity_log((int) $user['id'], 'profile_update', 'Profile updated');

json_response(['success' => true, 'message' => 'Profile saved successfully.']);
