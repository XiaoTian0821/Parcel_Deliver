<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin']);

$payload = [
    'success' => true,
    'total_riders' => (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'rider'")->fetchColumn(),
    'online_riders' => (int) db()->query("SELECT COUNT(*) FROM riders WHERE status = 'online' AND is_active = 1")->fetchColumn(),
    'offline_riders' => (int) db()->query("SELECT COUNT(*) FROM riders WHERE status = 'offline' OR is_active = 0")->fetchColumn(),
    'total_parcels' => (int) db()->query('SELECT COUNT(*) FROM parcels')->fetchColumn(),
    'delivered_parcels' => (int) db()->query("SELECT COUNT(*) FROM parcels WHERE status = 'delivered'")->fetchColumn(),
    'pending_parcels' => (int) db()->query("SELECT COUNT(*) FROM parcels WHERE status IN ('pending', 'out_for_delivery')")->fetchColumn(),
];

json_response($payload);
