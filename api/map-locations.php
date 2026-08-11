<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin']);

$stmt = db()->query(
    "SELECT r.id, r.current_latitude, r.current_longitude, r.last_location_update, r.status, u.full_name
     FROM riders r
     INNER JOIN users u ON u.id = r.user_id
     WHERE r.status = 'online' AND r.current_latitude IS NOT NULL AND r.current_longitude IS NOT NULL
     ORDER BY r.last_location_update DESC"
);

json_response(['success' => true, 'riders' => $stmt->fetchAll()]);
