<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

if (!is_admin()) {
    redirect('index.php?page=rider-dashboard');
}

$pageTitle = 'Add New Parcel';
$message = '';
$messageType = '';

// 数据库连接
$config = require __DIR__ . '/../../config/local.php';
$dsn = "mysql:host=" . ($config['DB_HOST'] ?? '127.0.0.1') . ";dbname=" . ($config['DB_NAME'] ?? 'parcel_deliver') . ";charset=utf8mb4";
$pdo = new PDO($dsn, $config['DB_USER'] ?? 'root', $config['DB_PASS'] ?? '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

/* ========================================================================= */
/*  👇【后端处理逻辑】：接收 POST 数据并写入 parcels 数据库表 👇               */
/* ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tracking_number  = trim($_POST['tracking_number'] ?? '');
    $recipient_name   = trim($_POST['recipient_name'] ?? '');
    $recipient_phone  = trim($_POST['recipient_phone'] ?? '');
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $pickup_address   = trim($_POST['pickup_address'] ?? '');
    
    // 结合第二步：接收自动补全功能传入的经纬度数据
    $latitude  = !empty($_POST['destination_latitude']) ? (float)$_POST['destination_latitude'] : null;
    $longitude = !empty($_POST['destination_longitude']) ? (float)$_POST['destination_longitude'] : null;

    // 简单校验
    if (empty($recipient_name) || empty($recipient_phone) || empty($delivery_address)) {
        $message = '请填写所有必填字段（收件人姓名、电话及送货地址）';
        $messageType = 'danger';
    } else {
        try {
            // 如果未填写运单号，自动生成一个随机运单号
            if (empty($tracking_number)) {
                $tracking_number = 'PD' . date('YmdHis') . rand(100, 999);
            }

            // 执行数据库 INSERT 插入操作（包含经纬度）
            $stmt = $pdo->prepare("
                INSERT INTO parcels (
                    tracking_number, 
                    recipient_name, 
                    recipient_phone, 
                    delivery_address, 
                    pickup_address, 
                    destination_latitude, 
                    destination_longitude, 
                    status
                ) VALUES (
                    :tracking_number, 
                    :recipient_name, 
                    :recipient_phone, 
                    :delivery_address, 
                    :pickup_address, 
                    :destination_latitude, 
                    :destination_longitude, 
                    'pending'
                )
            ");

            $stmt->execute([
                ':tracking_number'       => $tracking_number,
                ':recipient_name'        => $recipient_name,
                ':recipient_phone'       => $recipient_phone,
                ':delivery_address'      => $delivery_address,
                ':pickup_address'        => $pickup_address ?: null,
                ':destination_latitude'  => $latitude,   // 写入纬度
                ':destination_longitude' => $longitude   // 写入经度
            ]);

            $message = "包裹创建成功！运单号：{$tracking_number}";
            $messageType = 'success';

        } catch (PDOException $e) {
            $message = '保存数据库失败: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<!-- 自动补全建议框样式 -->
<style>
  .form-container { max-width: 650px; margin: 20px auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
  .form-group { margin-bottom: 18px; position: relative; }
  .form-group label { font-weight: bold; margin-bottom: 6px; display: block; }
  .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
  
  /* 下拉自动补全菜单 */
  .autocomplete-suggestions {
    position: absolute; top: 100%; left: 0; right: 0; z-index: 9999;
    background: #ffffff; border: 1px solid #ddd; border-top: none;
    border-radius: 0 0 6px 6px; max-height: 220px; overflow-y: auto;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15); display: none;
  }
  .autocomplete-suggestion-item { padding: 10px 12px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f2f2f2; line-height: 1.4; color: #333; }
  .autocomplete-suggestion-item:last-child { border-bottom: none; }
  .autocomplete-suggestion-item:hover { background-color: #f0f7ff; color: #007bff; }
  .btn-submit { background-color: #007bff; color: #fff; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; width: 100%; }
  .btn-submit:hover { background-color: #0056b3; }
  .alert { padding: 12px; border-radius: 4px; margin-bottom: 15px; }
  .alert-success { background: #d4edda; color: #155724; }
  .alert-danger { background: #f8d7da; color: #721c24; }
</style>

<div class="form-container">
    <h2>Add New Parcel (创建包裹)</h2>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form action="" method="POST" autocomplete="off">
        <div class="form-group">
            <label for="tracking_number">Tracking Number (运单号，留空自动生成):</label>
            <input type="text" id="tracking_number" name="tracking_number" class="form-control" placeholder="例：TRK123456">
        </div>

        <div class="form-group">
            <label for="recipient_name">Recipient Name (收件人姓名) *:</label>
            <input type="text" id="recipient_name" name="recipient_name" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="recipient_phone">Recipient Phone (收件人电话) *:</label>
            <input type="text" id="recipient_phone" name="recipient_phone" class="form-control" required>
        </div>

        <!-- 送货地址 (带地图建议 & 自动抓取经纬度) -->
        <div class="form-group">
            <label for="delivery_address">Delivery Address (送货地址) *:</label>
            <input type="text" 
                   id="delivery_address" 
                   name="delivery_address" 
                   class="form-control" 
                   placeholder="输入地点关键词（例如：Sunway Carnival）..." 
                   required>
            <div id="delivery_address_suggestions" class="autocomplete-suggestions"></div>

            <!-- 隐藏字段：用于保存经纬度传给后端 PDO 处理 -->
            <input type="hidden" id="destination_latitude" name="destination_latitude">
            <input type="hidden" id="destination_longitude" name="destination_longitude">
        </div>

        <!-- 取货地址 -->
        <div class="form-group">
            <label for="pickup_address">Pickup Address (取货地址):</label>
            <input type="text" 
                   id="pickup_address" 
                   name="pickup_address" 
                   class="form-control" 
                   placeholder="输入取货地点关键词...">
            <div id="pickup_address_suggestions" class="autocomplete-suggestions"></div>
        </div>

        <button type="submit" class="btn-submit">Save Parcel (保存包裹)</button>
    </form>
</div>

<!-- 前端地址自动补全脚本 -->
<script>
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

        // 350ms 防抖，当用户停止输入时请求地图数据
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
                    suggestionsEl.innerHTML = '<div class="autocomplete-suggestion-item" style="color:#999;">未找到对应匹配地点</div>';
                    return;
                }

                suggestionsEl.style.display = 'block';

                data.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'autocomplete-suggestion-item';
                    const displayName = item.display_name;
                    div.innerHTML = `📍 <strong>${item.name || query}</strong><br><small style="color:#666;">${displayName}</small>`;

                    div.addEventListener('click', function() {
                        inputEl.value = displayName;
                        
                        // 填入隐藏的经纬度框
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
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>