<?php

declare(strict_types=1);

require_role(['rider']);

$riderIdStmt = db()->prepare('SELECT id FROM riders WHERE user_id = :user_id LIMIT 1');
$riderIdStmt->execute(['user_id' => $_SESSION['user_id']]);
$riderId = (int) ($riderIdStmt->fetchColumn() ?: 0);

$stmt = db()->prepare(
    "SELECT ph.status, ph.remarks, ph.created_at, p.tracking_number, p.recipient_name, dp.photo_path
     FROM parcel_status_history ph
     INNER JOIN parcels p ON p.id = ph.parcel_id
     LEFT JOIN delivery_photos dp ON dp.parcel_id = p.id AND dp.rider_id = ph.rider_id
     WHERE ph.rider_id = :rider_id
     ORDER BY ph.created_at DESC
     LIMIT 60"
);
$stmt->execute(['rider_id' => $riderId]);
$history = $stmt->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<section class="panel">
    <div class="panel-head">
        <h2>Delivery History</h2>
        <p>Completed, failed, and in-progress updates recorded from the rider account.</p>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Tracking</th>
                    <th>Recipient</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th>Proof</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $row): ?>
                    <tr>
                        <td><?= escape(format_datetime($row['created_at'])) ?></td>
                        <td><?= escape($row['tracking_number']) ?></td>
                        <td><?= escape($row['recipient_name']) ?></td>
                        <td><span class="badge badge-<?= escape(str_replace('_', '-', $row['status'])) ?>"><?= escape(str_replace('_', ' ', ucfirst($row['status']))) ?></span></td>
                        <td><?= escape($row['remarks'] ?? '-') ?></td>
                        <td>
                            <?php if (!empty($row['photo_path'])): ?>
                                <a class="text-link" href="<?= escape(base_url($row['photo_path'])) ?>" target="_blank" rel="noopener">View photo</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
