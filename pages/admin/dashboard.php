<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

if (!is_admin()) {
    redirect('index.php?page=rider-dashboard');
}

$pageTitle = 'Admin Dashboard & Rider Tracking';

// 数据库连接获取概览数据
$config = require __DIR__ . '/../../config/local.php';
$dsn = "mysql:host=" . ($config['DB_HOST'] ?? '127.0.0.1') . ";dbname=" . ($config['DB_NAME'] ?? 'parcel_deliver') . ";charset=utf8mb4";
$pdo = new PDO($dsn, $config['DB_USER'] ?? 'root', $config['DB_PASS'] ?? '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// 统计数据
$totalParcels = $pdo->query("SELECT COUNT(*) FROM parcels")->fetchColumn();
$activeRiders = $pdo->query("SELECT COUNT(*) FROM riders WHERE status = 'online'")->fetchColumn();
$pendingParcels = $pdo->query("SELECT COUNT(*) FROM parcels WHERE status = 'pending'")->fetchColumn();
$deliveringParcels = $pdo->query("SELECT COUNT(*) FROM parcels WHERE status = 'out_for_delivery'")->fetchColumn();

require __DIR__ . '/../../includes/header.php';
?>

<!-- Leaflet 地图 CSS 与 JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
  .dashboard-overview { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 20px; }
  .stat-card { background: #fff; padding: 18px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
  .stat-card h3 { margin: 0; font-size: 14px; color: #666; text-transform: uppercase; }
  .stat-card p { margin: 10px 0 0; font-size: 28px; font-weight: bold; color: #2c3e50; }
  
  .map-container { position: relative; width: 100%; height: 580px; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
  #adminRiderMap { width: 100%; height: 100%; }

  .map-overlay-panel {
    position: absolute; top: 15px; left: 15px; z-index: 1000;
    background: #ffffff; padding: 12px; border-radius: 8px; width: 300px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  }
  .search-input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; margin-bottom: 8px; }
  .search-results { max-height: 180px; overflow-y: auto; background: #fff; border: 1px solid #eee; display: none; }
  .search-result-item { padding: 8px; border-bottom: 1px solid #eee; cursor: pointer; font-size: 13px; }
  .search-result-item:hover { background: #f0f7ff; }
</style>

<div class="dashboard-container">
    <h2>Admin Dashboard Overview</h2>

    <!-- 顶栏指标统计卡片 -->
    <div class="dashboard-overview">
        <div class="stat-card">
            <h3>Total Parcels</h3>
            <p><?= (int)$totalParcels ?></p>
        </div>
        <div class="stat-card">
            <h3>Active Riders (Online)</h3>
            <p style="color: #27ae60;"><?= (int)$activeRiders ?></p>
        </div>
        <div class="stat-card">
            <h3>Pending Delivery</h3>
            <p style="color: #e67e22;"><?= (int)$pendingParcels ?></p>
        </div>
        <div class="stat-card">
            <h3>Out For Delivery</h3>
            <p style="color: #2980b9;"><?= (int)$deliveringParcels ?></p>
        </div>
    </div>

    <!-- 实时交互式 Rider 定位地图 -->
    <h3>Real-time Rider Location Tracking</h3>
    <div class="map-container">
        <div class="map-overlay-panel">
            <input type="text" id="mapSearchInput" class="search-input" placeholder="Search Rider or Location..." onkeyup="filterRiderOrLocation()">
            <div id="searchResults" class="search-results"></div>
            <div style="font-size: 12px; color: #666; margin-top: 5px;">
                🟢 Online Rider | 🔴 Offline Rider | 🔄 Auto refresh every 10s
            </div>
        </div>
        <div id="adminRiderMap"></div>
    </div>
</div>

<script>
  // 1. 初始化地图（默认吉隆坡中心点）
  const map = L.map('adminRiderMap').setView([3.1390, 101.6869], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  let riderMarkers = {};

  // 2. 轮询获取所有骑手的实时位置并渲染到地图上
  function fetchActiveRiders() {
      fetch('api/search_location.php?q=') // 不带关键词请求全部骑手
          .then(res => res.json())
          .then(res => {
              if (res.success && res.data) {
                  res.data.forEach(rider => {
                      if (rider.type === 'rider') {
                          const key = rider.title;
                          const isOnline = rider.status === 'online';
                          
                          // 自定义 Icon（在线绿色/离线灰色）
                          const markerColor = isOnline ? 'green' : 'gray';
                          
                          if (riderMarkers[key]) {
                              riderMarkers[key].setLatLng([rider.lat, rider.lng]);
                          } else {
                              const marker = L.marker([rider.lat, rider.lng])
                                  .addTo(map)
                                  .bindPopup(`
                                      <b>${rider.title}</b><br>
                                      Status: <b style="color:${isOnline ? 'green':'red'}">${rider.status}</b><br>
                                      Updated: ${rider.updated_at}
                                  `);
                              riderMarkers[key] = marker;
                          }
                      }
                  });
              }
          })
          .catch(err => console.error("Map refresh error:", err));
  }

  // 3. 页面载入即拉取位置，并每 10 秒自动刷新一次
  fetchActiveRiders();
  setInterval(fetchActiveRiders, 10000);

  // 4. 地图搜索过滤功能
  function filterRiderOrLocation() {
      const q = document.getElementById('mapSearchInput').value.trim();
      const resultsDropdown = document.getElementById('searchResults');
      
      if (q.length < 2) {
          resultsDropdown.style.display = 'none';
          return;
      }

      fetch(`api/search_location.php?q=${encodeURIComponent(q)}`)
          .then(res => res.json())
          .then(res => {
              resultsDropdown.innerHTML = '';
              if (res.success && res.data && res.data.length > 0) {
                  resultsDropdown.style.display = 'block';
                  res.data.forEach(item => {
                      const div = document.createElement('div');
                      div.className = 'search-result-item';
                      div.innerHTML = `<strong>[${item.type}]</strong> ${item.title}`;
                      div.onclick = () => {
                          map.flyTo([item.lat, item.lng], 16, { duration: 1.5 });
                          L.marker([item.lat, item.lng]).addTo(map).bindPopup(item.title).openPopup();
                          resultsDropdown.style.display = 'none';
                      };
                      resultsDropdown.appendChild(div);
                  });
              }
          });
  }
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>