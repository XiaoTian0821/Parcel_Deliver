<?php

declare(strict_types=1);

require_role(['admin']);

$stats = [
    'total_riders' => (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'rider'")->fetchColumn(),
    'online_riders' => (int) db()->query("SELECT COUNT(*) FROM riders WHERE status = 'online' AND is_active = 1")->fetchColumn(),
    'offline_riders' => (int) db()->query("SELECT COUNT(*) FROM riders WHERE status = 'offline' OR is_active = 0")->fetchColumn(),
    'total_parcels' => (int) db()->query('SELECT COUNT(*) FROM parcels')->fetchColumn(),
    'delivered_parcels' => (int) db()->query("SELECT COUNT(*) FROM parcels WHERE status = 'delivered'")->fetchColumn(),
    'pending_parcels' => (int) db()->query("SELECT COUNT(*) FROM parcels WHERE status IN ('pending', 'out_for_delivery')")->fetchColumn(),
];

$recentParcels = db()->query(
    "SELECT p.id, p.tracking_number, p.recipient_name, p.status, p.updated_at, u.full_name AS rider_name
     FROM parcels p
     LEFT JOIN riders r ON r.id = p.assigned_rider_id
     LEFT JOIN users u ON u.id = r.user_id
     ORDER BY p.updated_at DESC, p.id DESC
     LIMIT 8"
)->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<section class="grid stats-grid" id="dashboardStats" data-endpoint="<?= escape(base_url('api/dashboard-stats.php')) ?>">
    <article class="stat-card accent-blue" data-stat="total_riders"><span>Total Riders</span><strong><?= number_format($stats['total_riders']) ?></strong></article>
    <article class="stat-card accent-green" data-stat="online_riders"><span>Online Riders</span><strong><?= number_format($stats['online_riders']) ?></strong></article>
    <article class="stat-card accent-slate" data-stat="offline_riders"><span>Offline Riders</span><strong><?= number_format($stats['offline_riders']) ?></strong></article>
    <article class="stat-card accent-amber" data-stat="total_parcels"><span>Total Parcels</span><strong><?= number_format($stats['total_parcels']) ?></strong></article>
    <article class="stat-card accent-emerald" data-stat="delivered_parcels"><span>Delivered</span><strong><?= number_format($stats['delivered_parcels']) ?></strong></article>
    <article class="stat-card accent-rose" data-stat="pending_parcels"><span>Pending</span><strong><?= number_format($stats['pending_parcels']) ?></strong></article>
</section>

<section class="panel split-panel">
    <div>
        <div class="panel-head">
            <h2>Active Riders Map</h2>
            <p>Live GPS positions are refreshed automatically.</p>
        </div>
        <div class="map-canvas" id="adminRiderMap" data-map="riders" data-endpoint="<?= escape(base_url('api/map-locations.php')) ?>"></div>
    </div>
    <div>
        <div class="panel-head">
            <h2>Quick Search</h2>
            <p>Search riders and parcels instantly.</p>
        </div>
        <div class="stack-form compact-form">
            <label>Search Riders
                <input type="search" class="live-search" data-search-type="riders" placeholder="Search rider name or employee code">
            </label>
            <label>Search Parcels
                <input type="search" class="live-search" data-search-type="parcels" placeholder="Search tracking number or recipient">
            </label>
        </div>
        <div class="search-results" id="searchResults"></div>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Recent Parcels</h2>
        <a class="text-link" href="<?= escape(base_url('index.php?page=admin-parcels')) ?>">Manage parcels</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking No.</th>
                    <th>Recipient</th>
                    <th>Rider</th>
                    <th>Status</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentParcels as $parcel): ?>
                    <tr>
                        <td><?= escape($parcel['tracking_number']) ?></td>
                        <td><?= escape($parcel['recipient_name']) ?></td>
                        <td><?= escape($parcel['rider_name'] ?? 'Unassigned') ?></td>
                        <td><span class="badge badge-<?= escape(str_replace('_', '-', $parcel['status'])) ?>"><?= escape(str_replace('_', ' ', ucfirst($parcel['status']))) ?></span></td>
                        <td><?= escape(format_datetime($parcel['updated_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
