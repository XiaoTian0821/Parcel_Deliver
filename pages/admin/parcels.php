<?php

declare(strict_types=1);

require_role(['admin']);

$editId = int_value($_GET['edit'] ?? 0);

$riders = db()->query(
    "SELECT r.id, u.full_name
     FROM riders r
     INNER JOIN users u ON u.id = r.user_id
     WHERE r.is_active = 1
     ORDER BY u.full_name ASC"
)->fetchAll();

$editingParcel = null;
if ($editId > 0) {
    $stmt = db()->prepare('SELECT * FROM parcels WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingParcel = $stmt->fetch() ?: null;
}

$parcels = db()->query(
    "SELECT p.*, u.full_name AS rider_name
     FROM parcels p
     LEFT JOIN riders r ON r.id = p.assigned_rider_id
     LEFT JOIN users u ON u.id = r.user_id
     ORDER BY p.created_at DESC, p.id DESC"
)->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<section class="panel">
    <div class="panel-head">
        <h2><?= $editingParcel ? 'Edit Parcel' : 'Create Parcel' ?></h2>
        <?php if ($editingParcel): ?>
            <a class="text-link" href="<?= escape(base_url('index.php?page=admin-parcels')) ?>">Cancel edit</a>
        <?php endif; ?>
    </div>
    <form class="stack-form grid-form ajax-form" method="post" action="<?= escape(base_url('api/parcel-save.php')) ?>" data-success-target="parcelMessage">
        <?= csrf_field() ?>
        <input type="hidden" name="parcel_id" value="<?= escape((string) ($editingParcel['id'] ?? '')) ?>">
        <div class="grid form-grid-3">
            <label>Recipient Name
                <input type="text" name="recipient_name" value="<?= escape($editingParcel['recipient_name'] ?? '') ?>" required>
            </label>
            <label>Recipient Phone
                <input type="text" name="recipient_phone" value="<?= escape($editingParcel['recipient_phone'] ?? '') ?>" required>
            </label>
            <label>Status
                <select name="status" required>
                    <?php foreach (['pending', 'out_for_delivery', 'delivered', 'failed_delivery'] as $status): ?>
                        <option value="<?= escape($status) ?>" <?= (($editingParcel['status'] ?? 'pending') === $status) ? 'selected' : '' ?>><?= escape(ucwords(str_replace('_', ' ', $status))) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>Delivery Address
            <input type="text" name="delivery_address" value="<?= escape($editingParcel['delivery_address'] ?? '') ?>" data-autocomplete="address" required>
        </label>
        <div class="grid form-grid-3">
            <label>City
                <input type="text" name="city" value="<?= escape($editingParcel['city'] ?? '') ?>">
            </label>
            <label>State
                <input type="text" name="state" value="<?= escape($editingParcel['state'] ?? '') ?>">
            </label>
            <label>Postal Code
                <input type="text" name="postal_code" value="<?= escape($editingParcel['postal_code'] ?? '') ?>">
            </label>
        </div>
        <label>Pickup Address
            <input type="text" name="pickup_address" value="<?= escape($editingParcel['pickup_address'] ?? '') ?>">
        </label>
        <label>Delivery Instructions
            <textarea name="delivery_instructions" rows="3"><?= escape($editingParcel['delivery_instructions'] ?? '') ?></textarea>
        </label>
        <div class="grid form-grid-2">
            <label>Assign Rider
                <select name="assigned_rider_id">
                    <option value="">Unassigned</option>
                    <?php foreach ($riders as $rider): ?>
                        <option value="<?= escape((string) $rider['id']) ?>" <?= ((int) ($editingParcel['assigned_rider_id'] ?? 0) === (int) $rider['id']) ? 'selected' : '' ?>><?= escape($rider['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="form-actions aligned-end">
                <button class="btn btn-primary" type="submit"><?= $editingParcel ? 'Update Parcel' : 'Create Parcel' ?></button>
            </div>
        </div>
        <div class="form-message" id="parcelMessage"></div>
    </form>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>All Parcels</h2>
        <input type="search" class="live-search" data-search-type="parcels" placeholder="Search parcels">
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking</th>
                    <th>Recipient</th>
                    <th>Rider</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="parcelsTableBody">
                <?php foreach ($parcels as $parcel): ?>
                    <tr>
                        <td><?= escape($parcel['tracking_number']) ?></td>
                        <td><?= escape($parcel['recipient_name']) ?></td>
                        <td><?= escape($parcel['rider_name'] ?? 'Unassigned') ?></td>
                        <td><span class="badge badge-<?= escape(str_replace('_', '-', $parcel['status'])) ?>"><?= escape(str_replace('_', ' ', ucfirst($parcel['status']))) ?></span></td>
                        <td><?= escape(format_datetime($parcel['created_at'])) ?></td>
                        <td class="action-cell">
                            <a class="btn btn-small btn-ghost" href="<?= escape(base_url('index.php?page=admin-parcels&edit=' . (int) $parcel['id'])) ?>">Edit</a>
                            <form class="inline-form ajax-form" method="post" action="<?= escape(base_url('api/parcel-save.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="parcel_id" value="<?= escape((string) $parcel['id']) ?>">
                                <button class="btn btn-small btn-danger" type="submit" data-confirm="Delete this parcel?">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
