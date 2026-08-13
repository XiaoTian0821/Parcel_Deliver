<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

// 1. 初始化 PDO 数据库连接
$config = require __DIR__ . '/../../config/local.php';
try {
    $dsn = "mysql:host=" . ($config['DB_HOST'] ?? '127.0.0.1') . ";dbname=" . ($config['DB_NAME'] ?? 'parcel_deliver') . ";charset=utf8mb4";
    $pdo = new PDO($dsn, $config['DB_USER'] ?? 'root', $config['DB_PASS'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'] ?? 0;

// 2. 获取当前骑手信息
$stmtRider = $pdo->prepare("SELECT * FROM riders WHERE user_id = :user_id");
$stmtRider->execute([':user_id' => $user_id]);
$rider = $stmtRider->fetch();

// 当前骑手状态（默认为 offline）
$currentStatus = strtolower($rider['status'] ?? 'offline');

// 3. 查询分配给该骑手且未完成送达的包裹
$parcel = null;
if ($rider) {
    try {
        $stmtParcel = $pdo->prepare("
            SELECT * FROM parcels 
            WHERE rider_id = :rider_id AND status != 'delivered'
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmtParcel->execute([':rider_id' => $rider['id']]);
        $parcel = $stmtParcel->fetch() ?: null;
    } catch (PDOException $e) {
        // 兼容处理：若 parcels 表尚未添加 rider_id 字段，展示最新一条
        $stmtParcel = $pdo->query("SELECT * FROM parcels WHERE status != 'delivered' ORDER BY id DESC LIMIT 1");
        $parcel = $stmtParcel->fetch() ?: null;
    }
}

$pageTitle = 'Rider Dashboard';
require __DIR__ . '/../../includes/header.php';
?>

<style>
    .dashboard-container { max-width: 800px; margin: 20px auto; padding: 15px; }
    .card { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .card h3 { margin-top: 0; color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
    .info-group { margin-bottom: 12px; font-size: 15px; display: flex; align-items: center; gap: 10px; }
    .info-group strong { color: #555; }
    
    /* 状态标签样式 */
    .status-badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 13px; font-weight: bold; text-transform: uppercase; }
    .status-badge.online { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .status-badge.offline { background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
    
    /* 开关切换按钮样式 */
    .status-switch-btn { padding: 6px 14px; border: none; border-radius: 20px; cursor: pointer; font-size: 13px; font-weight: bold; transition: all 0.2s ease; }
    .status-switch-btn.to-offline { background-color: #dc3545; color: #fff; }
    .status-switch-btn.to-offline:hover { background-color: #bd2130; }
    .status-switch-btn.to-online { background-color: #28a745; color: #fff; }
    .status-switch-btn.to-online:hover { background-color: #218838; }

    .btn-nav { display: inline-block; background-color: #007bff; color: #fff; padding: 12px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 10px; }
    .btn-nav:hover { background-color: #0056b3; color: #fff; }
    .location-box { font-size: 13px; color: #666; background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 10px; border-left: 4px solid #007bff; }
</style>

<div class="dashboard-container">
    <h2>Rider Dashboard (骑手工作台)</h2>

    <!-- 骑手状态控制卡片 -->
    <div class="card">
        <h3>Rider Status (个人状态控制)</h3>
        <div class="info-group">
            <strong>Employee Code:</strong> <?= htmlspecialchars($rider['employee_code'] ?? 'N/A') ?>
        </div>
        
        <div class="info-group">
            <strong>Current Status:</strong> 
            <!-- 动态显示当前状态标签 -->
            <span id="status-badge" class="status-badge <?= $currentStatus ?>">
                <?= strtoupper($currentStatus) ?>
            </span>

            <!-- 切换按钮 -->
            <button id="toggle-status-btn" 
                    class="status-switch-btn <?= $currentStatus === 'online' ? 'to-offline' : 'to-online' ?>"
                    onclick="toggleRiderStatus()">
                <?= $currentStatus === 'online' ? 'Switch to Offline 🔴' : 'Switch to Online 🟢' ?>
            </button>
        </div>

        <div class="location-box" id="location-status">
            📍 GPS Location: Syncing location...
        </div>
    </div>

    <!-- 当前派送包裹信息卡片 -->
    <div class="card">
        <h3>Active Delivery Mission (当前派送任务)</h3>

        <?php if ($parcel): ?>
            <div class="info-group">
                <strong>Tracking #:</strong> <?= htmlspecialchars($parcel['tracking_number'] ?? 'N/A') ?>
            </div>
            
            <div class="info-group">
                <strong>Recipient:</strong> 
                <?= htmlspecialchars($parcel['recipient_name'] ?? 'N/A') ?> 
                (<?= htmlspecialchars($parcel['recipient_phone'] ?? 'N/A') ?>)
            </div>

            <div class="info-group">
                <strong>Pickup Address:</strong> 
                <?= htmlspecialchars($parcel['pickup_address'] ?? 'N/A') ?>
            </div>
            
            <div class="info-group">
                <strong>Delivery Address:</strong> 
                <?= htmlspecialchars($parcel['delivery_address'] ?? 'N/A') ?>
            </div>

            <div class="info-group">
                <strong>Mission Status:</strong> 
                <span class="status-badge online"><?= htmlspecialchars(strtoupper($parcel['status'] ?? 'PENDING')) ?></span>
            </div>

            <!-- Google Maps 导航按钮 -->
            <div style="margin-top: 15px;">
                <?php if (!empty($parcel['destination_latitude']) && !empty($parcel['destination_longitude'])): ?>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $parcel['destination_latitude'] ?>,<?= $parcel['destination_longitude'] ?>" 
                       target="_blank" 
                       class="btn-nav">
                        📍 Navigate to Destination (GPS Precise)
                    </a>
                <?php else: ?>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($parcel['delivery_address'] ?? '') ?>" 
                       target="_blank" 
                       class="btn-nav">
                        📍 Navigate via Address
                    </a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <p style="color: #6c757d; margin: 0;">目前没有分配给您的待派送包裹。</p>
        <?php endif; ?>
    </div>
</div>

<script>
// 当前骑手状态全局变量
let currentRiderStatus = '<?= $currentStatus ?>';

// 切换 Online / Offline 的前端异步函数
function toggleRiderStatus() {
    const nextStatus = currentRiderStatus === 'online' ? 'offline' : 'online';
    const btn = document.getElementById('toggle-status-btn');
    const badge = document.getElementById('status-badge');

    btn.disabled = true;
    btn.innerText = 'Updating...';

    fetch('api/update_rider_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status: nextStatus })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        if (data.success) {
            currentRiderStatus = nextStatus;
            
            // 1. 更新 UI 状态徽章
            badge.className = `status-badge ${nextStatus}`;
            badge.innerText = nextStatus.toUpperCase();

            // 2. 更新按钮文字与样式
            if (nextStatus === 'online') {
                btn.className = 'status-switch-btn to-offline';
                btn.innerText = 'Switch to Offline 🔴';
            } else {
                btn.className = 'status-switch-btn to-online';
                btn.innerText = 'Switch to Online 🟢';
            }
        } else {
            alert('修改状态失败: ' + (data.message || '未知错误'));
            btn.innerText = currentRiderStatus === 'online' ? 'Switch to Offline 🔴' : 'Switch to Online 🟢';
        }
    })
    .catch(err => {
        btn.disabled = false;
        console.error('Status Update Error:', err);
        alert('网络请求失败，请检查连接');
    });
}

// 自动上报 GPS 坐标
function updateRiderLocation() {
    if ("geolocation" in navigator) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            document.getElementById('location-status').innerHTML = 
                `📍 Live GPS: Lat ${lat.toFixed(5)}, Lng ${lng.toFixed(5)} (Updated: ${new Date().toLocaleTimeString()})`;

            // 自动发坐标给后端
            fetch('api/update_location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ latitude: lat, longitude: lng })
            }).catch(err => console.error("GPS Sync Error:", err));
        }, function(error) {
            document.getElementById('location-status').innerHTML = "⚠️ Unable to retrieve GPS position.";
        }, { enableHighAccuracy: true });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateRiderLocation();
    setInterval(updateRiderLocation, 30000); // 每 30 秒上报一次 GPS
});
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>