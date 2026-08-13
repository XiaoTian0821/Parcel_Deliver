<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
?>

<!-- 引入 Leaflet 地图样式与 JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
  /* 地图区域样式 */
  .map-wrapper {
    position: relative;
    width: 100%;
    height: 650px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  }
  #map {
    width: 100%;
    height: 100%;
  }

  /* 悬浮搜索组件 */
  .map-search-panel {
    position: absolute;
    top: 15px;
    left: 15px;
    z-index: 1000;
    width: 320px;
    background: #ffffff;
    border-radius: 8px;
    padding: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
  }
  .search-form-group {
    display: flex;
    gap: 6px;
  }
  .search-input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    outline: none;
  }
  .search-input:focus {
    border-color: #007bff;
  }
  .search-btn {
    padding: 8px 14px;
    background-color: #007bff;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
  }
  .search-btn:hover {
    background-color: #0056b3;
  }

  /* 下拉搜索结果列表 */
  .search-results-dropdown {
    margin-top: 8px;
    max-height: 220px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #eee;
    border-radius: 4px;
    display: none;
  }
  .search-item {
    padding: 10px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    font-size: 13px;
    line-height: 1.4;
  }
  .search-item:last-child {
    border-bottom: none;
  }
  .search-item:hover {
    background-color: #eef6ff;
  }
  .badge-rider {
    display: inline-block;
    background: #007bff;
    color: #fff;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
  }
  .badge-place {
    display: inline-block;
    background: #28a745;
    color: #fff;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
  }
</style>

<div class="map-wrapper">
    <!-- 地图上的悬浮搜索面板 -->
    <div class="map-search-panel">
        <div class="search-form-group">
            <input type="text" id="mapSearchInput" class="search-input" placeholder="输入 Rider 姓名/工号 或地点..." onkeypress="handleKeyPress(event)">
            <button type="button" class="search-btn" onclick="searchLocationOrRider()">搜索</button>
        </div>
        <div id="searchResults" class="search-results-dropdown"></div>
    </div>

    <!-- 地图容器 -->
    <div id="map"></div>
</div>

<script>
  // 1. 初始化地图，默认聚焦吉隆坡坐标 [3.1390, 101.6869]
  const map = L.map('map').setView([3.1390, 101.6869], 12);

  // 2. 加载 OpenStreetMap 免费开源图层
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  let currentMarker = null; // 记录当前搜索选中的高亮图层

  // 支持按下回车搜索
  function handleKeyPress(e) {
      if (e.key === 'Enter') {
          searchLocationOrRider();
      }
  }

  // 3. 执行搜索请求
  function searchLocationOrRider() {
      const keyword = document.getElementById('mapSearchInput').value.trim();
      const resultsDropdown = document.getElementById('searchResults');

      if (!keyword) {
          alert('请输入搜索内容');
          return;
      }

      fetch(`api/search_location.php?q=${encodeURIComponent(keyword)}`)
          .then(res => res.json())
          .then(res => {
              resultsDropdown.innerHTML = '';

              if (!res.success || !res.data || res.data.length === 0) {
                  resultsDropdown.style.display = 'block';
                  resultsDropdown.innerHTML = '<div class="search-item" style="color:#999;">未找到相关地点或 Rider</div>';
                  return;
              }

              resultsDropdown.style.display = 'block';

              // 循环渲染搜索结果列表
              res.data.forEach(item => {
                  const itemDiv = document.createElement('div');
                  itemDiv.className = 'search-item';

                  const badgeClass = item.type === 'rider' ? 'badge-rider' : 'badge-place';
                  const badgeText = item.type === 'rider' ? 'Rider' : '地点';

                  itemDiv.innerHTML = `
                      <div><span class="${badgeClass}">${badgeText}</span> <strong>${item.title}</strong></div>
                      ${item.type === 'rider' ? `<small style="color:#666;">状态: ${item.status} | 更新时间: ${item.updated_at}</small>` : ''}
                  `;

                  // 点击搜索项绑定跳转定位
                  itemDiv.onclick = () => {
                      focusMapOnTarget(item);
                  };

                  resultsDropdown.appendChild(itemDiv);
              });
          })
          .catch(err => {
              console.error('搜索请求异常:', err);
          });
  }

  // 4. 在地图上聚焦并高亮指定点
  function focusMapOnTarget(data) {
      // 收起下拉列表
      document.getElementById('searchResults').style.display = 'none';

      const lat = data.lat;
      const lng = data.lng;

      // 视场滑行过渡放大（Level 16）
      map.flyTo([lat, lng], 16, {
          duration: 1.5,
          easeLinearity: 0.25
      });

      // 移除旧标记
      if (currentMarker) {
          map.removeLayer(currentMarker);
      }

      // 弹出框卡片内容
      let popupContent = `<b>${data.title}</b><br>坐标: ${lat.toFixed(5)}, ${lng.toFixed(5)}`;
      if (data.type === 'rider') {
          popupContent += `<br><b>在线状态:</b> <span style="color:${data.status === 'online' ? 'green' : 'red'}">${data.status}</span>`;
          popupContent += `<br><b>最近更新:</b> ${data.updated_at}`;
      }

      // 添加新的 Marker 并弹出提示
      currentMarker = L.marker([lat, lng]).addTo(map);
      currentMarker.bindPopup(popupContent).openPopup();
  }
</script>