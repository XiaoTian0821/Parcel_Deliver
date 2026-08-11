<?php

declare(strict_types=1);

require_role(['rider']);

$user = current_user();
$riderStmt = db()->prepare('SELECT employee_code, vehicle_type, status FROM riders WHERE user_id = :user_id LIMIT 1');
$riderStmt->execute(['user_id' => $_SESSION['user_id']]);
$rider = $riderStmt->fetch() ?: null;

require __DIR__ . '/../../includes/header.php';
?>
<section class="panel split-panel">
    <div>
        <div class="panel-head">
            <h2>Rider Profile</h2>
        </div>
        <form class="stack-form ajax-form" method="post" enctype="multipart/form-data" action="<?= escape(base_url('api/profile-save.php')) ?>">
            <?= csrf_field() ?>
            <label>Full Name
                <input type="text" name="full_name" value="<?= escape($user['full_name']) ?>" required>
            </label>
            <label>Email Address
                <input type="email" name="email" value="<?= escape($user['email']) ?>" required>
            </label>
            <label>Phone Number
                <input type="text" name="phone" value="<?= escape($user['phone'] ?? '') ?>">
            </label>
            <label>Vehicle Type
                <input type="text" name="vehicle_type" value="<?= escape($rider['vehicle_type'] ?? '') ?>">
            </label>
            <label>New Password
                <input type="password" name="password" minlength="8" placeholder="Leave blank to keep current password">
            </label>
            <label>Avatar
                <input type="file" name="avatar" accept="image/*">
            </label>
            <button class="btn btn-primary" type="submit">Save Profile</button>
            <div class="form-message"></div>
        </form>
    </div>
    <div class="profile-summary card-note">
        <h3>Rider Details</h3>
        <p><strong>Employee Code:</strong> <?= escape($rider['employee_code'] ?? '-') ?></p>
        <p><strong>Status:</strong> <?= escape(ucfirst($rider['status'] ?? 'offline')) ?></p>
        <p><strong>Joined:</strong> <?= escape(format_datetime($user['created_at'])) ?></p>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
