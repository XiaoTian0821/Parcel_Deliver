<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized.'], 401);
}

$type = $_GET['type'] ?? '';
$query = trim((string) ($_GET['q'] ?? ''));

if ($type === 'riders') {
    $stmt = db()->prepare(
        "SELECT u.full_name, u.email, r.employee_code, r.status, r.last_location_update
         FROM riders r
         INNER JOIN users u ON u.id = r.user_id
         WHERE u.full_name LIKE :query OR u.email LIKE :query OR r.employee_code LIKE :query
         ORDER BY u.full_name ASC
         LIMIT 15"
    );
    $stmt->execute(['query' => '%' . $query . '%']);
    json_response(['success' => true, 'type' => 'riders', 'results' => $stmt->fetchAll()]);
}

if ($type === 'parcels') {
    $stmt = db()->prepare(
        "SELECT p.tracking_number, p.recipient_name, p.delivery_address, p.status, p.updated_at
         FROM parcels p
         WHERE p.tracking_number LIKE :query OR p.recipient_name LIKE :query OR p.delivery_address LIKE :query
         ORDER BY p.updated_at DESC
         LIMIT 15"
    );
    $stmt->execute(['query' => '%' . $query . '%']);
    json_response(['success' => true, 'type' => 'parcels', 'results' => $stmt->fetchAll()]);
}

json_response(['success' => false, 'message' => 'Unknown search type.'], 422);
