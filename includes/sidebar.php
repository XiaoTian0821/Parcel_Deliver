<?php $role = current_role(); ?>
<aside class="sidebar">
    <div class="brand-block">
        <div class="brand-mark">PD</div>
        <div>
            <h2>Parcel Delivery</h2>
            <p>Management System</p>
        </div>
    </div>
    <nav class="sidebar-nav">
        <?php if ($role === 'admin'): ?>
            <a href="<?= escape(base_url('index.php?page=admin-dashboard')) ?>">Dashboard</a>
            <a href="<?= escape(base_url('index.php?page=admin-riders')) ?>">Riders</a>
            <a href="<?= escape(base_url('index.php?page=admin-parcels')) ?>">Parcels</a>
            <a href="<?= escape(base_url('index.php?page=admin-tracking')) ?>">Tracking</a>
            <a href="https://freeroute.org/#pricing" target="_blank" rel="noopener">Route Tracking</a>
            <a href="<?= escape(base_url('index.php?page=admin-reports')) ?>">Reports</a>
            <a href="<?= escape(base_url('index.php?page=admin-profile')) ?>">Profile</a>
        <?php else: ?>
            <a href="<?= escape(base_url('index.php?page=rider-dashboard')) ?>">Dashboard</a>
            <a href="<?= escape(base_url('index.php?page=rider-parcels')) ?>">Assigned Parcels</a>
            <a href="<?= escape(base_url('index.php?page=rider-history')) ?>">History</a>
            <a href="<?= escape(base_url('index.php?page=rider-profile')) ?>">Profile</a>
        <?php endif; ?>
    </nav>
</aside>
