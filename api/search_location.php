<?php

declare(strict_types=1);

// 开启错误提示（开发测试用）
ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// 读取搜索参数
$query = trim($_GET['q'] ?? '');

if ($query === '') {
    echo json_encode(['success' => false, 'message' => '请输入搜索内容']);
    exit;
}

// 引入数据库配置文件
$config = require __DIR__ . '/../config/local.php';

try {
    $dsn = "mysql:host=" . ($config['DB_HOST'] ?? '127.0.0.1') . ";dbname=" . ($config['DB_NAME'] ?? 'parcel_deliver') . ";charset=utf8mb4";
    $pdo = new PDO($dsn, $config['DB_USER'] ?? 'root', $config['DB_PASS'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $results = [];

    // 1. 查询骑手表 (联表查询 users 和 riders)
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
        // 确保骑手有有效坐标
        if (!empty($rider['current_latitude']) && !empty($rider['current_longitude'])) {
            $results[] = [
                'type' => 'rider',
                'title' => '骑手: ' . $rider['full_name'] . ' (' . $rider['employee_code'] . ')',
                'status' => $rider['status'], // online / offline
                'lat' => (float)$rider['current_latitude'],
                'lng' => (float)$rider['current_longitude'],
                'updated_at' => $rider['last_location_update'] ?? '未知'
            ];
        }
    }

    // 2. 使用 OpenStreetMap 解析普通地理位置关键词
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