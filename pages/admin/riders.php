<?php

declare(strict_types=1);

require_role(['admin']);

$search = trim((string) ($_GET['q'] ?? ''));
$params = [];
$where = '';

if ($search !== '') {
    $where = "WHERE u.full_name LIKE :search OR u.email LIKE :search OR r.employee_code LIKE :search";
    $params['search'] = '%' . $search . '%';
}

$stmt = db()->prepare(
    "SELECT r.id, r.employee_code, r.status, r.current_latitude, r.current_longitude, r.last_location_update, r.vehicle_type, u.full_name, u.email, u.phone
     FROM riders r
     INNER JOIN users u ON u.id = r.user_id
     {$where}
     ORDER BY u.full_name ASC"
);
$stmt->execute($params);
$riders = $stmt->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<section class="panel">
    <div class="panel-head">
        <h2>Rider Directory</h2>
        <form class="inline-search" method="get">
            <input type="hidden" name="page" value="admin-riders">
            <input type="search" name="q" value="<?= escape($search) ?>" placeholder="Search rider">
            <button class="btn btn-primary" type="submit">Search</button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Last Update</th>
                </tr>
            </thead>
            <tbody id="ridersTableBody">
                <?php foreach ($riders as $rider): ?>
                    <tr>
                        <td><?= escape($rider['full_name']) ?></td>
                        <td><?= escape($rider['employee_code']) ?></td>
                        <td><?= escape($rider['email']) ?></td>
                        <td><span class="badge badge-<?= escape($rider['status']) ?>"><?= escape(ucfirst($rider['status'])) ?></span></td>
                        <td>
                            <?= $rider['current_latitude'] !== null ? escape(number_format((float) $rider['current_latitude'], 6) . ', ' . number_format((float) $rider['current_longitude'], 6)) : 'No location yet' ?>
                        </td>
                        <td><?= escape(format_datetime($rider['last_location_update'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
