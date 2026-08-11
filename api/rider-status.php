<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['rider']);

if (request_method() !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

verify_csrf_token();

$riderIdStmt = db()->prepare('SELECT id, status FROM riders WHERE user_id = :user_id LIMIT 1');
$riderIdStmt->execute(['user_id' => $_SESSION['user_id']]);
$rider = $riderIdStmt->fetch();

if (!$rider) {
    json_response(['success' => false, 'message' => 'Rider profile not found.'], 404);
}

$newStatus = ($_POST['status'] ?? $rider['status']) === 'online' ? 'online' : 'offline';

$stmt = db()->prepare('UPDATE riders SET status = :status, updated_at = NOW() WHERE id = :id');
$stmt->execute(['status' => $newStatus, 'id' => $rider['id']]);

activity_log((int) $_SESSION['user_id'], 'rider_status', 'Rider switched to ' . $newStatus);

json_response(['success' => true, 'message' => 'Status updated.', 'status' => $newStatus]);
