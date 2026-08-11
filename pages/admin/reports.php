<?php

declare(strict_types=1);

require_role(['admin']);

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$summaryStmt = db()->prepare(
    "SELECT
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'failed_delivery' THEN 1 ELSE 0 END) AS failed_count,
        COUNT(*) AS total_count
     FROM parcels
     WHERE DATE(created_at) BETWEEN :from_date AND :to_date"
);
$summaryStmt->execute(['from_date' => $from, 'to_date' => $to]);
$summary = $summaryStmt->fetch() ?: ['delivered_count' => 0, 'pending_count' => 0, 'failed_count' => 0, 'total_count' => 0];

$logsStmt = db()->prepare(
    "SELECT a.action_type, a.description, a.created_at, u.full_name
     FROM activity_logs a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC
     LIMIT 40"
);
$logsStmt->execute();
$logs = $logsStmt->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<section class="panel">
    <form class="inline-search" method="get">
        <input type="hidden" name="page" value="admin-reports">
        <label>From <input type="date" name="from" value="<?= escape($from) ?>"></label>
        <label>To <input type="date" name="to" value="<?= escape($to) ?>"></label>
        <button class="btn btn-primary" type="submit">Generate</button>
    </form>
</section>

<section class="grid stats-grid">
    <article class="stat-card accent-blue"><span>Total Parcels</span><strong><?= number_format((int) $summary['total_count']) ?></strong></article>
    <article class="stat-card accent-emerald"><span>Delivered</span><strong><?= number_format((int) $summary['delivered_count']) ?></strong></article>
    <article class="stat-card accent-amber"><span>Pending</span><strong><?= number_format((int) $summary['pending_count']) ?></strong></article>
    <article class="stat-card accent-rose"><span>Failed</span><strong><?= number_format((int) $summary['failed_count']) ?></strong></article>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Activity Logs</h2>
        <p>Audit trail of rider and admin actions.</p>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= escape(format_datetime($log['created_at'])) ?></td>
                        <td><?= escape($log['full_name'] ?? 'System') ?></td>
                        <td><?= escape(ucwords(str_replace('_', ' ', $log['action_type']))) ?></td>
                        <td><?= escape($log['description']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
