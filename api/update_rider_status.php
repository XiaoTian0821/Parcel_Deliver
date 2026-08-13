<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$status = strtolower(trim($input['status'] ?? ''));

// 校验只允许 online 或 offline
if (!in_array($status, ['online', 'offline'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;

try {
    $config = require __DIR__ . '/../config/local.php';
    $dsn = "mysql:host=" . ($config['DB_HOST'] ?? '127.0.0.1') . ";dbname=" . ($config['DB_NAME'] ?? 'parcel_deliver') . ";charset=utf8mb4";
    $pdo = new PDO($dsn, $config['DB_USER'] ?? 'root', $config['DB_PASS'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 更新当前骑手的状态
    $stmt = $pdo->prepare("UPDATE riders SET status = :status WHERE user_id = :user_id");
    $stmt->execute([
        ':status' => $status,
        ':user_id' => $user_id
    ]);

    echo json_encode([
        'success' => true,
        'status' => $status,
        'message' => 'Status updated successfully'
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}