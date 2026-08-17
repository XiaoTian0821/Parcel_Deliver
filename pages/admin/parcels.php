<?php
// 1. 数据库连接设置
if (!isset($conn)) {
    $conn = new mysqli("localhost", "root", "123456", "parcel_deliver");
    if ($conn->connect_error) {
        die("Database Connection Failed: " . $conn->connect_error);
    }
}

// ----------------------------------------------------------------------
// 2. 处理 AJAX 请求（拦截所有非 HTML 提交，防止 footer.php 污染 JSON）
// ----------------------------------------------------------------------
if (isset($_REQUEST['action']) && $_REQUEST['action'] !== 'add_parcel') {
    // 清空输出缓冲区，屏蔽非必要的致命报错直接推送到前端
    ob_start();
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    header('Content-Type: application/json; charset=utf-8');

    $response = ['success' => false, 'message' => 'Unknown error occurred'];

    try {
        $action = trim($_REQUEST['action']);

        // A. 切换 / 保存更新包裹状态 (Save Update / Status Change)
        if ($action === 'update_status' || $action === 'save_update_parcel') {
            $parcel_id = isset($_REQUEST['parcel_id']) ? intval($_REQUEST['parcel_id']) : 0;
            $status = $_REQUEST['status'] ?? '';
            $rider_id = !empty($_REQUEST['assigned_rider_id']) ? intval($_REQUEST['assigned_rider_id']) : NULL;

            if ($parcel_id <= 0) {
                throw new Exception("Invalid Parcel ID.");
            }

            if (!empty($status) && $rider_id !== null) {
                // 同时更新骑手与状态
                $stmt = $conn->prepare("UPDATE parcels SET status = ?, assigned_rider_id = ? WHERE id = ?");
                $stmt->bind_param("sii", $status, $rider_id, $parcel_id);
            } elseif (!empty($status)) {
                // 仅更新状态
                $stmt = $conn->prepare("UPDATE parcels SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $status, $parcel_id);
            } else {
                throw new Exception("Missing parameters for update.");
            }

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = "Parcel updated successfully.";
            } else {
                throw new Exception("Database update failed: " . $stmt->error);
            }
            $stmt->close();
        }

        // B. 删除包裹记录
        else if ($action === 'delete_parcel') {
            $parcel_id = isset($_REQUEST['parcel_id']) ? intval($_REQUEST['parcel_id']) : 0;
            if ($parcel_id <= 0) {
                throw new Exception("Invalid Parcel ID.");
            }

            $stmt = $conn->prepare("DELETE FROM parcels WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare SQL failed: " . $conn->error);
            }

            $stmt->bind_param("i", $parcel_id);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = "Parcel deleted successfully.";
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();
        } else {
            throw new Exception("Invalid action type: " . htmlspecialchars($action));
        }

    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
    }

    // 强制清理缓冲区并返回 JSON，立即终止程序
    ob_end_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// ----------------------------------------------------------------------
// 3. 处理常规表单提交（创建新包裹 Add Parcel）
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_parcel') {
    $tracking_number = !empty($_POST['tracking_number']) ? $_POST['tracking_number'] : 'TRK' . time();
    $recipient_name = $_POST['recipient_name'] ?? '';
    $recipient_phone = $_POST['recipient_phone'] ?? '';
    $delivery_address = $_POST['delivery_address'] ?? '';
    $pickup_address = $_POST['pickup_address'] ?? '';
    $assigned_rider_id = !empty($_POST['assigned_rider_id']) ? intval($_POST['assigned_rider_id']) : NULL;
    $dest_lat = !empty($_POST['destination_latitude']) ? $_POST['destination_latitude'] : NULL;
    $dest_lng = !empty($_POST['destination_longitude']) ? $_POST['destination_longitude'] : NULL;

    $stmt = $conn->prepare("INSERT INTO parcels (tracking_number, recipient_name, recipient_phone, delivery_address, pickup_address, assigned_rider_id, destination_latitude, destination_longitude, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    
    if (!$stmt) {
        $stmt = $conn->prepare("INSERT INTO parcels (tracking_number, recipient_name, recipient_phone, delivery_address, pickup_address, assigned_rider_id, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("sssssi", $tracking_number, $recipient_name, $recipient_phone, $delivery_address, $pickup_address, $assigned_rider_id);
    } else {
        $stmt->bind_param("sssssidd", $tracking_number, $recipient_name, $recipient_phone, $delivery_address, $pickup_address, $assigned_rider_id, $dest_lat, $dest_lng);
    }

    $stmt->execute();
    $stmt->close();
    
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// ----------------------------------------------------------------------
// 4. 获取数据（用于页面显示）
// ----------------------------------------------------------------------
$riders_query = "SELECT r.id AS rider_id, COALESCE(u.full_name, r.name, r.full_name, r.employee_code) AS display_name 
                FROM riders r 
                LEFT JOIN users u ON r.user_id = u.id";
$riders_result = $conn->query($riders_query);
$riders = [];
if ($riders_result && $riders_result->num_rows > 0) {
    while ($row = $riders_result->fetch_assoc()) {
        $riders[] = $row;
    }
}

$parcels_query = "SELECT p.*, COALESCE(u.full_name, r.name, r.full_name, r.employee_code, 'Unassigned') AS rider_name 
                 FROM parcels p 
                 LEFT JOIN riders r ON p.assigned_rider_id = r.id 
                 LEFT JOIN users u ON r.user_id = u.id 
                 ORDER BY p.id DESC";
$parcels_result = $conn->query($parcels_query);
?>

<style>
  .parcels-page-wrapper {
    width: 100%;
    padding: 30px;
    box-sizing: border-box;
    color: #ffffff;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  }

  .page-subtitle {
    font-size: 11px;
    letter-spacing: 1.5px;
    color: #8a99ad;
    text-transform: uppercase;
    margin-bottom: 6px;
    font-weight: 600;
  }

  .page-title {
    font-size: 26px;
    font-weight: 700;
    margin: 0 0 25px 0;
    color: #ffffff;
  }

  .dashboard-card {
    background-color: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 30px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
  }

  .card-header-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 20px;
    color: #ffffff;
  }

  .form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 16px;
  }

  .form-group {
    display: flex;
    flex-direction: column;
    position: relative;
  }

  .form-group.full-width {
    grid-column: 1 / -1;
  }

  .form-group label {
    font-size: 13px;
    color: #94a3b8;
    margin-bottom: 8px;
    font-weight: 500;
  }

  .form-input, .form-select, .form-control {
    width: 100%;
    padding: 12px 14px;
    background-color: #1e293b;
    border: 1px solid #334155;
    border-radius: 8px;
    color: #ffffff;
    font-size: 14px;
    box-sizing: border-box;
  }

  .form-input::placeholder, .form-control::placeholder { color: #64748b; }
  .form-input:focus, .form-select:focus, .form-control:focus {
    outline: none;
    border-color: #38bdf8;
    box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
  }

  .autocomplete-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background-color: #1e293b;
    border: 1px solid #334155;
    border-radius: 8px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 999;
    display: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.5);
  }

  .autocomplete-suggestion-item {
    padding: 10px 14px;
    cursor: pointer;
    border-bottom: 1px solid #334155;
    color: #cbd5e1;
    font-size: 13px;
  }

  .autocomplete-suggestion-item:hover {
    background-color: #334155;
    color: #ffffff;
  }

  .btn-submit {
    margin-top: 20px;
    width: 100%;
    padding: 14px;
    background-color: #0075ff;
    color: #ffffff;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
  }

  .btn-submit:hover { background-color: #0060df; }

  .table-container { overflow-x: auto; }
  .history-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    text-align: left;
  }

  .history-table th {
    background-color: #1e293b;
    color: #94a3b8;
    padding: 14px 16px;
    font-size: 13px;
    font-weight: 600;
    border-bottom: 1px solid #334155;
  }

  .history-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #1e293b;
    font-size: 14px;
    color: #cbd5e1;
    vertical-align: middle;
  }

  .status-select {
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid #334155;
    background-color: #1e293b;
    cursor: pointer;
  }
  .status-pending { color: #fbbf24; border-color: #d97706; }
  .status-out_for_delivery { color: #38bdf8; border-color: #0284c7; }
  .status-delivered { color: #4ade80; border-color: #16a34a; }
  .status-failed_delivery { color: #f87171; border-color: #dc2626; }

  .action-buttons { display: flex; gap: 8px; }
  .btn-act {
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }
  .btn-nav { background-color: #0284c7; color: #ffffff; }
  .btn-nav:hover { background-color: #0369a1; }
  .btn-delete { background-color: #7f1d1d; color: #fca5a5; }
  .btn-delete:hover { background-color: #991b1b; }
</style>

<div class="parcels-page-wrapper">
  
  <div class="page-subtitle">PARCEL DELIVERY MANAGEMENT SYSTEM</div>
  <h1 class="page-title">Parcel Management & Creation</h1>

  <!-- 创建包裹 Form -->
  <div class="dashboard-card">
    <div class="card-header-title">Add New Parcel</div>
    <form action="" method="POST">
      <input type="hidden" name="action" value="add_parcel">

      <div class="form-grid">
        <div class="form-group">
          <label>Tracking Number (留空自动生成)</label>
          <input type="text" name="tracking_number" class="form-input" placeholder="例: TRK123456">
        </div>

        <div class="form-group">
          <label>Recipient Name (收件人姓名) *</label>
          <input type="text" name="recipient_name" class="form-input" required placeholder="输入收件人姓名">
        </div>

        <div class="form-group">
          <label>Recipient Phone (收件人电话) *</label>
          <input type="text" name="recipient_phone" class="form-input" required placeholder="输入收件人电话">
        </div>

        <div class="form-group">
          <label>Assign Rider (分配骑手)</label>
          <select name="assigned_rider_id" class="form-select">
            <option value="">-- Unassigned (未分配) --</option>
            <?php foreach ($riders as $r): ?>
              <option value="<?php echo $r['rider_id']; ?>">
                <?php echo htmlspecialchars($r['display_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group full-width">
            <label for="delivery_address">Delivery Address (送货地址) *:</label>
            <input type="text" 
                   id="delivery_address" 
                   name="delivery_address" 
                   class="form-control" 
                   placeholder="输入地点关键词..." 
                   required>
            <div id="delivery_address_suggestions" class="autocomplete-suggestions"></div>

            <input type="hidden" id="destination_latitude" name="destination_latitude">
            <input type="hidden" id="destination_longitude" name="destination_longitude">
        </div>

        <div class="form-group full-width">
            <label for="pickup_address">Pickup Address (取货地址):</label>
            <input type="text" 
                   id="pickup_address" 
                   name="pickup_address" 
                   class="form-control" 
                   placeholder="输入取货地点关键词...">
            <div id="pickup_address_suggestions" class="autocomplete-suggestions"></div>
        </div>
      </div>

      <button type="submit" class="btn-submit">Save Parcel (保存包裹)</button>
    </form>
  </div>

  <!-- 包裹历史记录 History -->
  <div class="dashboard-card">
    <div class="card-header-title">Parcel History & Active Missions</div>
    
    <div class="table-container">
      <table class="history-table">
        <thead>
          <tr>
            <th>Tracking No</th>
            <th>Recipient</th>
            <th>Phone</th>
            <th>Delivery Address</th>
            <th>Assigned Rider</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($parcels_result && $parcels_result->num_rows > 0): ?>
            <?php while ($row = $parcels_result->fetch_assoc()): ?>
            <tr id="parcel-row-<?php echo $row['id']; ?>">
              <td><strong><?php echo htmlspecialchars($row['tracking_number']); ?></strong></td>
              <td><?php echo htmlspecialchars($row['recipient_name']); ?></td>
              <td><?php echo htmlspecialchars($row['recipient_phone']); ?></td>
              <td><?php echo htmlspecialchars($row['delivery_address']); ?></td>
              <td>👤 <?php echo htmlspecialchars($row['rider_name']); ?></td>

              <td>
                <select class="status-select status-<?php echo $row['status']; ?>" 
                        onchange="updateParcelStatus(<?php echo $row['id']; ?>, this)">
                  <option value="pending" <?php echo $row['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                  <option value="out_for_delivery" <?php echo $row['status'] === 'out_for_delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                  <option value="delivered" <?php echo $row['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                  <option value="failed_delivery" <?php echo $row['status'] === 'failed_delivery' ? 'selected' : ''; ?>>Failed Delivery</option>
                </select>
              </td>

              <td>
                <div class="action-buttons">
                  <!-- 1. 跳转 Google Maps 导航按钮 -->
                  <?php 
                    $nav_address = urlencode($row['delivery_address'] ?? '');
                    $nav_lat = $row['destination_latitude'] ?? '';
                    $nav_lng = $row['destination_longitude'] ?? '';
                  ?>
                  <button type="button" 
                          class="btn-act btn-nav"
                          onclick="navigateToAddress('<?php echo $nav_address; ?>', '<?php echo $nav_lat; ?>', '<?php echo $nav_lng; ?>')">
                    📍 Navigate
                  </button>

                  <!-- 2. 删除按钮 -->
                  <button type="button" class="btn-act btn-delete" onclick="deleteParcel(<?php echo $row['id']; ?>)">Delete</button>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" style="text-align: center; color: #64748b; padding: 30px;">暂无包裹记录</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- JavaScript 逻辑 -->
<script>
// --------------------------------------------------
// 1. Google Maps 自动调起与地址填入函数
// --------------------------------------------------
function navigateToAddress(encodedAddress, lat, lng) {
    let mapsUrl = '';

    // 优先使用经纬度精确导航
    if (lat && lng && lat !== 'null' && lng !== 'null' && lat !== '' && lng !== '') {
        mapsUrl = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
    } 
    // 退回使用 Delivery Address 文本导航
    else if (encodedAddress && encodedAddress !== 'null' && encodedAddress !== '') {
        mapsUrl = `https://www.google.com/maps/dir/?api=1&destination=${encodedAddress}`;
    } 
    else {
        alert("找不到有效的送货地址！");
        return;
    }

    // 在新窗口打开 Google Maps (手机端会自动调起 App)
    window.open(mapsUrl, '_blank');
}

// --------------------------------------------------
// 2. 地理位置 Geolocation 安全捕捉（防止 Block 崩溃）
// --------------------------------------------------
if ("geolocation" in navigator) {
    navigator.geolocation.getCurrentPosition(
        function(position) {
            console.log("GPS Location acquired:", position.coords.latitude, position.coords.longitude);
        },
        function(error) {
            // 被 Block 或拒绝时仅警告，不卡死程序
            console.warn("Geolocation permission error/blocked:", error.message);
        },
        { timeout: 5000 }
    );
}

// --------------------------------------------------
// 3. 地址输入框联想补全逻辑
// --------------------------------------------------
function bindAddressAutocomplete(inputId, suggestionsId, latId = null, lngId = null) {
    const inputEl = document.getElementById(inputId);
    const suggestionsEl = document.getElementById(suggestionsId);
    let debounceTimer = null;

    if (!inputEl || !suggestionsEl) return;

    inputEl.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(debounceTimer);

        if (query.length < 3) {
            suggestionsEl.style.display = 'none';
            suggestionsEl.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(() => {
            const apiUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&addressdetails=1`;

            fetch(apiUrl, {
                headers: { 'Accept-Language': 'en,zh-CN' }
            })
            .then(res => res.json())
            .then(data => {
                suggestionsEl.innerHTML = '';

                if (!data || data.length === 0) {
                    suggestionsEl.style.display = 'block';
                    suggestionsEl.innerHTML = '<div class="autocomplete-suggestion-item" style="color:#999;">未找到匹配地点</div>';
                    return;
                }

                suggestionsEl.style.display = 'block';

                data.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'autocomplete-suggestion-item';
                    const displayName = item.display_name;
                    div.innerHTML = `📍 <strong>${item.name || query}</strong><br><small style="color:#94a3b8;">${displayName}</small>`;

                    div.addEventListener('click', function() {
                        inputEl.value = displayName;
                        
                        if (latId && document.getElementById(latId)) {
                            document.getElementById(latId).value = item.lat;
                        }
                        if (lngId && document.getElementById(lngId)) {
                            document.getElementById(lngId).value = item.lon;
                        }

                        suggestionsEl.style.display = 'none';
                    });

                    suggestionsEl.appendChild(div);
                });
            })
            .catch(err => console.error('Address lookup error:', err));
        }, 350);
    });

    document.addEventListener('click', function(e) {
        if (!inputEl.contains(e.target) && !suggestionsEl.contains(e.target)) {
            suggestionsEl.style.display = 'none';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    bindAddressAutocomplete('delivery_address', 'delivery_address_suggestions', 'destination_latitude', 'destination_longitude');
    bindAddressAutocomplete('pickup_address', 'pickup_address_suggestions');
});

// --------------------------------------------------
// 4. AJAX 状态更新逻辑 (防止 Invalid Server Response)
// --------------------------------------------------
function updateParcelStatus(parcelId, selectElem) {
  const newStatus = selectElem.value;
  const formData = new FormData();
  formData.append('action', 'update_status');
  formData.append('parcel_id', parcelId);
  formData.append('status', newStatus);

  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(res => res.text())
  .then(text => {
    try {
      const data = JSON.parse(text);
      if (data.success) {
        selectElem.className = 'status-select status-' + newStatus;
      } else {
        alert("更新失败: " + data.message);
      }
    } catch (e) {
      console.error("服务器返回非 JSON 内容:\n", text);
      alert("Invalid server response! 请按 F12 检查 Console。");
    }
  })
  .catch(err => {
    console.error("AJAX Error:", err);
    alert("请求发送失败。");
  });
}

// --------------------------------------------------
// 5. AJAX 删除逻辑
// --------------------------------------------------
function deleteParcel(parcelId) {
  if (!confirm("确定要删除此包裹记录吗？")) return;

  const formData = new FormData();
  formData.append('action', 'delete_parcel');
  formData.append('parcel_id', parcelId);

  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(res => res.text())
  .then(text => {
    try {
      const data = JSON.parse(text);
      if (data.success) {
        const row = document.getElementById('parcel-row-' + parcelId);
        if (row) row.remove();
      } else {
        alert("删除失败: " + data.message);
      }
    } catch (e) {
      console.error("服务器返回非 JSON 内容:\n", text);
      alert("Invalid server response!");
    }
  })
  .catch(err => {
    console.error("AJAX Error:", err);
    alert("删除请求发送失败。");
  });
}
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>