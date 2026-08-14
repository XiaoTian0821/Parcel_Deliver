<?php

declare(strict_types=1);

require_role(['admin']);

// 1. 获取选中的 parcel_id (如有)，默认取一个分配了 Rider 且未送达的包裹
$selectedParcelId = (int) ($_GET['parcel_id'] ?? 0);

// 查询所有包含地理坐标、已指派 Rider 的在途包裹
$parcelsStmt = db()->query(
    "SELECT 
        p.id AS parcel_id, 
        p.tracking_number, 
        p.recipient_name, 
        p.pickup_address, 
        p.delivery_address, 
        p.destination_latitude, 
        p.destination_longitude, 
        r.id AS rider_id, 
        r.employee_code, 
        r.current_latitude AS rider_lat, 
        r.current_longitude AS rider_lng, 
        r.last_location_update,
        u.full_name AS rider_name
     FROM parcels p
     INNER JOIN riders r ON r.id = p.assigned_rider_id
     INNER JOIN users u ON u.id = r.user_id
     WHERE p.status IN ('out_for_delivery', 'pending')
       AND r.current_latitude IS NOT NULL 
       AND r.current_longitude IS NOT NULL
     ORDER BY p.id DESC"
);
$trackingList = $parcelsStmt->fetchAll();

// 筛选当前在地图上重点展示的记录
$activeTracking = null;
if (!empty($trackingList)) {
    if ($selectedParcelId > 0) {
        foreach ($trackingList as $item) {
            if ((int) $item['parcel_id'] === $selectedParcelId) {
                $activeTracking = $item;
                break;
            }
        }
    }
    if (!$activeTracking) {
        $activeTracking = $trackingList[0]; // 默认选中第一条
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<!-- 引入 Leaflet 地图与 Leaflet Routing Machine 路线库 (免费开源) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />

<style>
    .tracking-container { display: flex; gap: 20px; min-height: 550px; }
    .tracking-sidebar { width: 320px; background: #fff; border-radius: 8px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
    .tracking-map-wrap { flex: 1; border-radius: 8px; overflow: hidden; background: #e5e3df; position: relative; }
    #trackingMap { width: 100%; height: 100%; min-height: 550px; }
    .parcel-card { padding: 12px; border: 1px solid #eee; border-radius: 6px; margin-bottom: 10px; cursor: pointer; transition: all 0.2s; }
    .parcel-card:hover, .parcel-card.active { border-color: #007bff; background: #f0f7ff; }
</style>

<section class="panel">
    <div class="panel-head">
        <h2>Live Rider Tracking</h2>
    </div>

    <?php if ($activeTracking): ?>
        <div class="tracking-container">
            <!-- 左侧：正在配送的包裹列表 -->
            <div class="tracking-sidebar">
                <h3>Active Deliveries (<?= count($trackingList) ?>)</h3>
                <div class="parcel-list">
                    <?php foreach ($trackingList as $item): ?>
                        <div class="parcel-card <?= $item['parcel_id'] === $activeTracking['parcel_id'] ? 'active' : '' ?>" 
                             onclick="window.location.href='?page=admin-rider-location&parcel_id=<?= $item['parcel_id'] ?>'">
                            <div><strong><?= escape($item['tracking_number']) ?></strong></div>
                            <small>🛵 Rider: <?= escape($item['rider_name']) ?></small><br>
                            <small>👤 Recipient: <?= escape($item['recipient_name']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 右侧：地图渲染区域 -->
            <div class="tracking-map-wrap">
                <div id="trackingMap"></div>
            </div>
        </div>
    <?php else: ?>
        <div style="padding: 40px; text-align: center; color: #777;">
            Currently no active riders with available GPS coordinates.
        </div>
    <?php endif; ?>
</section>

<?php if ($activeTracking): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. 初始化坐标点 (Rider 当前点 & Parcels 目的地点)
    const riderLat = <?= (float) $activeTracking['rider_lat'] ?>;
    const riderLng = <?= (float) $activeTracking['rider_lng'] ?>;
    
    // 如果没有设置目的地 Lat/Lng，默认用骑手坐标微调作为终点备用
    const destLat = <?= $activeTracking['destination_latitude'] !== null ? (float) $activeTracking['destination_latitude'] : 'riderLat + 0.01' ?>;
    const destLng = <?= $activeTracking['destination_longitude'] !== null ? (float) $activeTracking['destination_longitude'] : 'riderLng + 0.01' ?>;

    // 2. 初始化地图
    const map = L.map('trackingMap').setView([riderLat, riderLng], 13);

    // 加载 OpenStreetMap 地图瓦片
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // 3. 自定义图标
    const riderIcon = L.icon({
        iconUrl: 'https://cdn-icons-png.flaticon.com/512/2972/2972185.png', // 摩托车/骑手图标
        iconSize: [40, 40],
        iconAnchor: [20, 20]
    });

    const destinationIcon = L.icon({
        iconUrl: 'https://cdn-icons-png.flaticon.com/512/684/684908.png', // 目的地标记图标
        iconSize: [35, 35],
        iconAnchor: [17, 35]
    });

    // 4. 放置 Rider Marker
    L.marker([riderLat, riderLng], { icon: riderIcon })
        .addTo(map)
        .bindPopup("<b>Rider: <?= escape($activeTracking['rider_name']) ?></b><br>Code: <?= escape($activeTracking['rider_code']) ?>")
        .openPopup();

    // 5. 绘制 Route 路线 (Rider 当前位置 -> Delivery Destination)
    L.Routing.control({
        waypoints: [
            L.latLng(riderLat, riderLng),
            L.latLng(destLat, destLng)
        ],
        lineOptions: {
            styles: [{ color: '#007bff', weight: 6 }]
        },
        createMarker: function(i, wp, nWps) {
            if (i === 1) {
                return L.marker(wp.latLng, { icon: destinationIcon })
                        .bindPopup("<b>Delivery Target</b><br><?= escape($activeTracking['delivery_address']) ?>");
            }
            return null; // 骑手图标已经在上方初始化，不再重复生成起点 Marker
        },
        addWaypoints: false,
        draggableWaypoints: false,
        fitSelectedRoutes: true,
        show: false // 隐藏右侧路由步骤文字面板
    }).addTo(map);
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>