<?php

declare(strict_types=1);

require_role(['rider']);

$user = current_user();
$riderStmt = db()->prepare(
    "SELECT r.*, u.full_name, u.phone, u.avatar
     FROM riders r
     INNER JOIN users u ON u.id = r.user_id
     WHERE r.user_id = :user_id LIMIT 1"
);
$riderStmt->execute(['user_id' => $_SESSION['user_id']]);
$rider = $riderStmt->fetch() ?: null;

$assignedCount = 0;
$inProgressCount = 0;
$deliveredCount = 0;
$latestLocation = null;

if ($rider) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM parcels WHERE assigned_rider_id = :rider_id');
    $stmt->execute(['rider_id' => $rider['id']]);
    $assignedCount = (int) $stmt->fetchColumn();

    $stmt = db()->prepare("SELECT COUNT(*) FROM parcels WHERE assigned_rider_id = :rider_id AND status = 'out_for_delivery'");
    $stmt->execute(['rider_id' => $rider['id']]);
    $inProgressCount = (int) $stmt->fetchColumn();

    $stmt = db()->prepare("SELECT COUNT(*) FROM parcels WHERE assigned_rider_id = :rider_id AND status = 'delivered'");
    $stmt->execute(['rider_id' => $rider['id']]);
    $deliveredCount = (int) $stmt->fetchColumn();

    $latestLocationStmt = db()->prepare('SELECT latitude, longitude, recorded_at FROM rider_locations WHERE rider_id = :rider_id ORDER BY recorded_at DESC LIMIT 1');
    $latestLocationStmt->execute(['rider_id' => $rider['id']]);
    $latestLocation = $latestLocationStmt->fetch() ?: null;
}

$assignedParcelsStmt = db()->prepare(
    "SELECT id, tracking_number, recipient_name, delivery_address, status, updated_at
     FROM parcels
     WHERE assigned_rider_id = :rider_id
     ORDER BY updated_at DESC, id DESC
     LIMIT 8"
);
$assignedParcelsStmt->execute(['rider_id' => $rider['id'] ?? 0]);
$assignedParcels = $assignedParcelsStmt->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<section class="grid stats-grid">
    <article class="stat-card accent-blue"><span>Assigned Parcels</span><strong><?= number_format($assignedCount) ?></strong></article>
    <article class="stat-card accent-amber"><span>Out for Delivery</span><strong><?= number_format($inProgressCount) ?></strong></article>
    <article class="stat-card accent-emerald"><span>Delivered</span><strong><?= number_format($deliveredCount) ?></strong></article>
    <article class="stat-card accent-slate"><span>Current Status</span><strong class="rider-status-label"><?= escape(ucfirst($rider['status'] ?? 'offline')) ?></strong></article>
</section>

<section class="panel split-panel">
    <div>
        <div class="panel-head">
            <h2>Online Control</h2>
            <p>Keep the page open so GPS tracking can update while you are online.</p>
        </div>
        <div class="rider-control" data-rider-id="<?= escape((string) ($rider['id'] ?? 0)) ?>" data-status-endpoint="<?= escape(base_url('api/rider-status.php')) ?>" data-location-endpoint="<?= escape(base_url('api/rider-location.php')) ?>" data-current-status="<?= escape($rider['status'] ?? 'offline') ?>">
            <button class="btn btn-primary rider-status-toggle" type="button"><?= escape(strtoupper($rider['status'] ?? 'offline')) ?></button>
            <div class="live-location">
                <span>Last recorded location</span>
                <strong><?= isset($latestLocation['latitude']) ? escape((string) $latestLocation['latitude'] . ', ' . (string) $latestLocation['longitude']) : 'No location yet' ?></strong>
                <small><?= escape(format_datetime($latestLocation['recorded_at'] ?? null)) ?></small>
            </div>
        </div>
    </div>
    <div>
        <div class="panel-head">
            <h2>Live Rider Map</h2>
        </div>
        <div class="map-canvas" id="riderMap" data-map="rider" data-rider-id="<?= escape((string) ($rider['id'] ?? 0)) ?>" data-endpoint="<?= escape(base_url('api/rider-history.php')) ?>"></div>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Recent Assigned Parcels</h2>
        <a class="text-link" href="<?= escape(base_url('index.php?page=rider-parcels')) ?>">View all</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking</th>
                    <th>Recipient</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignedParcels as $parcel): ?>
                    <tr>
                        <td><?= escape($parcel['tracking_number']) ?></td>
                        <td><?= escape($parcel['recipient_name']) ?></td>
                        <td><?= escape($parcel['delivery_address']) ?></td>
                        <td><span class="badge badge-<?= escape(str_replace('_', '-', $parcel['status'])) ?>"><?= escape(str_replace('_', ' ', ucfirst($parcel['status']))) ?></span></td>
                        <td><?= escape(format_datetime($parcel['updated_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
