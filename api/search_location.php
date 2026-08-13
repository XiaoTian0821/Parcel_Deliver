<?php

declare(strict_types=1);

// 开启错误提示（开发测试用）
ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// 1. 读取数据库配置并连接 PDO
$config = require __DIR__ . '/../config/local.php';

try {
    $dsn = "mysql:host=" . ($config['DB_HOST'] ?? '127.0.0.1') . ";dbname=" . ($config['DB_NAME'] ?? 'parcel_deliver') . ";charset=utf8mb4";
    $pdo = new PDO($dsn, $config['DB_USER'] ?? 'root', $config['DB_PASS'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 2. 获取用户传入的搜索关键词
    $query = trim($_GET['q'] ?? '');

    /* ========================================================================= */
    /*  👇【第四步的代码放在这里】：当 $query 为空（Admin Dashboard 刚加载地图时） 👇 */
    /* ========================================================================= */
    if ($query === '') {
        // 查出数据库里的所有骑手及其坐标
        $stmt = $pdo->query("
            SELECT 
                r.id AS rider_id,
                u.full_name,
                r.employee_code,
                r.status,
                r.current_latitude,
                r.current_longitude,
                r.last_location_update
            FROM riders r
            INNER JOIN users u ON r.user_id = u.id
        ");
        $riders = $stmt->fetchAll();
        
        $results = [];
        foreach ($riders as $rider) {
            // 只要骑手有坐标，就加进返回列表中
            if (!empty($rider['current_latitude']) && !empty($rider['current_longitude'])) {
                $results[] = [
                    'type' => 'rider',
                    'title' => 'Rider: ' . $rider['full_name'] . ' (' . $rider['employee_code'] . ')',
                    'status' => $rider['status'],
                    'lat' => (float)$rider['current_latitude'],
                    'lng' => (float)$rider['current_longitude'],
                    'updated_at' => $rider['last_location_update'] ?? 'N/A'
                ];
            }
        }

        // 直接返回所有骑手数据并结束脚本
        echo json_encode(['success' => true, 'data' => $results]);
        exit;
    }
    /* ========================================================================= */


    /* ========================================================================= */
    /*  👇 下面是用户输入了关键词 ($query 不为空) 时的搜索逻辑  👇 */
    /* ========================================================================= */
    $results = [];

    // A. 按姓名或工号搜索骑手
    $stmt = $pdo->prepare("
        SELECT 
            r.id AS rider_id,
            u.full_name,
            r.employee_code,
            r.status,
            r.current_latitude,
            r.current_longitude,
            r.last_location_update
        FROM riders r
        INNER JOIN users u ON r.user_id = u.id
        WHERE u.full_name LIKE :query 
           OR r.employee_code LIKE :query
    ");
    
    $searchTerm = "%{$query}%";
    $stmt->execute([':query' => $searchTerm]);
    $riders = $stmt->fetchAll();

    foreach ($riders as $rider) {
        if (!empty($rider['current_latitude']) && !empty($rider['current_longitude'])) {
            $results[] = [
                'type' => 'rider',
                'title' => 'Rider: ' . $rider['full_name'] . ' (' . $rider['employee_code'] . ')',
                'status' => $rider['status'],
                'lat' => (float)$rider['current_latitude'],
                'lng' => (float)$rider['current_longitude'],
                'updated_at' => $rider['last_location_update'] ?? 'N/A'
            ];
        }
    }

    // B. 使用 OpenStreetMap 解析地名关键词
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: ParcelDeliverApp/1.0\r\n"
        ]
    ];
    $context = stream_context_create($opts);
    $osmUrl = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($query) . "&limit=3";
    $osmResponse = @file_get_contents($osmUrl, false, $context);

    if ($osmResponse) {
        $places = json_decode($osmResponse, true);
        if (is_array($places)) {
            foreach ($places as $place) {
                $results[] = [
                    'type' => 'place',
                    'title' => '地点: ' . $place['display_name'],
                    'lat' => (float)$place['lat'],
                    'lng' => (float)$place['lon']
                ];
            }
        }
    }

    // 返回搜索到的特定骑手或地点
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '数据库错误: ' . $e->getMessage()
    ]);
}