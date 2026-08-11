<?php
// 尝试引入配置文件
$config = [];
if (file_exists(__DIR__ . '/config/local.php')) {
    $config = require __DIR__ . '/config/local.php';
} else {
    die("错误：找不到 config/local.php 配置文件！请检查文件路径。");
}

$host = $config['DB_HOST'] ?? '127.0.0.1';
$user = $config['DB_USER'] ?? 'root';
$pass = $config['DB_PASS'] ?? '';
$dbname = $config['DB_NAME'] ?? 'parcel_deliver';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<h2 style='color:green;'>数据库连接成功！</h2>";
    
    // 检查 users 表是否存在
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "users 表已建立。";
    } else {
        echo "<h3 style='color:orange;'>警告：数据库连接成功，但表还没导入，请先运行 SQL 脚本！</h3>";
    }
} catch (PDOException $e) {
    echo "<h2 style='color:red;'>连接失败，具体错误信息如下：</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}