<?php

declare(strict_types=1);

require_role(['admin']);

$riders = db()->query(
    "SELECT r.id, u.full_name
     FROM riders r
     INNER JOIN users u ON u.id = r.user_id
     ORDER BY u.full_name ASC"
)->fetchAll();

$selectedRiderId = int_value($_GET['rider_id'] ?? ($riders[0]['id'] ?? 0));
$selectedRider = null;

if ($selectedRiderId > 0) {
    $stmt = db()->prepare(
        "SELECT r.id, r.status, r.current_latitude, r.current_longitude, r.last_location_update, u.full_name
         FROM riders r
         INNER JOIN users u ON u.id = r.user_id
         WHERE r.id = :id LIMIT 1"
    );
    $stmt->execute(['id' => $selectedRiderId]);
    $selectedRider = $stmt->fetch() ?: null;
}

$historyStmt = db()->prepare(
    "SELECT latitude, longitude, accuracy, speed, heading, recorded_at
     FROM rider_locations
     WHERE rider_id = :rider_id
     ORDER BY recorded_at DESC
     LIMIT 50"
);
$historyStmt->execute(['rider_id' => $selectedRiderId]);
$locationHistory = $historyStmt->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<section class="panel split-panel">
    <div>
        <div class="panel-head">
            <h2>Track Rider</h2>
            <form method="get" class="inline-search">
                <input type="hidden" name="page" value="admin-tracking">
                <select name="rider_id">
                    <?php foreach ($riders as $rider): ?>
                        <option value="<?= escape((string) $rider['id']) ?>" <?= ((int) $rider['id'] === $selectedRiderId) ? 'selected' : '' ?>><?= escape($rider['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" type="submit">View Route</button>
            </form>
        </div>
        <div class="map-canvas" id="routeMap" data-map="route" data-rider-id="<?= escape((string) $selectedRiderId) ?>" data-endpoint="<?= escape(base_url('api/rider-history.php')) ?>"></div>
    </div>
    <div>
        <div class="panel-head">
            <h2>Rider Snapshot</h2>
        </div>
        <div class="detail-grid">
            <div><span>Name</span><strong><?= escape($selectedRider['full_name'] ?? '-') ?></strong></div>
            <div><span>Status</span><strong><?= escape(ucfirst($selectedRider['status'] ?? '-')) ?></strong></div>
            <div><span>Latitude</span><strong><?= $selectedRider && $selectedRider['current_latitude'] !== null ? escape((string) $selectedRider['current_latitude']) : '-' ?></strong></div>
            <div><span>Longitude</span><strong><?= $selectedRider && $selectedRider['current_longitude'] !== null ? escape((string) $selectedRider['current_longitude']) : '-' ?></strong></div>
            <div><span>Last Update</span><strong><?= escape(format_datetime($selectedRider['last_location_update'] ?? null)) ?></strong></div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Location History</h2>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Recorded</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Accuracy</th>
                    <th>Speed</th>
                    <th>Heading</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($locationHistory as $location): ?>
                    <tr>
                        <td><?= escape(format_datetime($location['recorded_at'])) ?></td>
                        <td><?= escape((string) $location['latitude']) ?></td>
                        <td><?= escape((string) $location['longitude']) ?></td>
                        <td><?= escape($location['accuracy'] !== null ? (string) $location['accuracy'] . ' m' : '-') ?></td>
                        <td><?= escape($location['speed'] !== null ? (string) $location['speed'] . ' m/s' : '-') ?></td>
                        <td><?= escape($location['heading'] !== null ? (string) $location['heading'] . '°' : '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
