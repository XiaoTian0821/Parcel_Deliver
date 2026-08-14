<?php

declare(strict_types=1);

require_role(['rider']);

$riderStmt = db()->prepare('SELECT id, status FROM riders WHERE user_id = :user_id LIMIT 1');
$riderStmt->execute(['user_id' => $_SESSION['user_id']]);
$rider = $riderStmt->fetch() ?: null;

$assignedParcelsStmt = db()->prepare(
    "SELECT id, tracking_number, recipient_name, recipient_phone, delivery_address, delivery_instructions, status, updated_at
     FROM parcels
     WHERE assigned_rider_id = :rider_id
     ORDER BY updated_at DESC, id DESC"
);
$assignedParcelsStmt->execute(['rider_id' => $rider['id'] ?? 0]);
$assignedParcels = $assignedParcelsStmt->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<section class="panel">
    <div class="panel-head">
        <h2>Assigned Parcels</h2>
        <p>Update delivery status, add remarks, and upload proof from your phone camera.</p>
    </div>
    <div class="stack-list">
        <?php foreach ($assignedParcels as $parcel): ?>
            <article class="parcel-card">
                <div class="parcel-head">
                    <div>
                        <h3><?= escape($parcel['tracking_number']) ?></h3>
                        <p><?= escape($parcel['recipient_name']) ?> | <?= escape($parcel['recipient_phone']) ?></p>
                    </div>
                    <span class="badge badge-<?= escape(str_replace('_', '-', $parcel['status'])) ?>"><?= escape(str_replace('_', ' ', ucfirst($parcel['status']))) ?></span>
                </div>
                <p class="parcel-address"><?= escape($parcel['delivery_address']) ?></p>
                <p class="muted"><?= escape($parcel['delivery_instructions'] ?: 'No special instructions') ?></p>
                <form class="stack-form ajax-form parcel-status-form" method="post" enctype="multipart/form-data" action="<?= escape(base_url('api/parcel-status.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="parcel_id" value="<?= escape((string) $parcel['id']) ?>">
                    <label>Status
                        <select name="status" required>
                            <?php foreach (['pending', 'out_for_delivery', 'delivered', 'failed_delivery'] as $status): ?>
                                <option value="<?= escape($status) ?>" <?= ($parcel['status'] === $status) ? 'selected' : '' ?>><?= escape(ucwords(str_replace('_', ' ', $status))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Remarks
                        <textarea name="remarks" rows="2" placeholder="Add delivery notes"></textarea>
                    </label>
                    <label>Delivery Proof Photo
                        <input type="file" name="delivery_photo" accept="image/*" capture="environment">
                    </label>
                    <button class="btn btn-primary" type="submit">Save Update</button>
                    <div class="form-message"></div>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>