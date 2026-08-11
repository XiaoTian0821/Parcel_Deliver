<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="alert alert-<?= escape($_SESSION['flash_type'] ?? 'info') ?>">
        <?= escape($_SESSION['flash_message']) ?>
    </div>
    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
<?php endif; ?>
