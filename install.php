<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// 1. 获取数据库连接（直接读取 config/local.php 配置）
function get_install_db(): PDO {
    $config = [];
    $configFile = __DIR__ . '/config/local.php';
    
    if (file_exists($configFile)) {
        $config = require $configFile;
    } else {
        die("<h1>配置缺失</h1><p>找不到 <code>config/local.php</code> 配置文件，请先配置数据库。</p>");
    }

    $host = $config['DB_HOST'] ?? '127.0.0.1';
    $dbname = $config['DB_NAME'] ?? 'parcel_deliver';
    $user = $config['DB_USER'] ?? 'root';
    $pass = $config['DB_PASS'] ?? '';

    try {
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        die("<h1>数据库连接失败</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>");
    }
}

$db = get_install_db();

// 2. 检查 users 表中是否已经存在管理员
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$adminCount = (int) $stmt->fetchColumn();

if ($adminCount > 0) {
    die("<h1>系统已初始化</h1><p>管理员账号已存在，无法重复创建。<a href='index.php?page=login'>返回登录页</a></p>");
}

// 3. 处理表单提交，创建管理员
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($fullName && $email && $password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
        if ($stmt->execute([$fullName, $email, $hash])) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['flash_message'] = 'Admin account created successfully! Please log in.';
            $_SESSION['flash_type'] = 'success';
            header('Location: index.php?page=login');
            exit;
        } else {
            $message = 'Failed to create admin account.';
        }
    } else {
        $message = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - Create First Admin</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 40px 20px; background: #f4f6f9; margin: 0; }
        .card { max-width: 400px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .card h2 { margin-top: 0; font-size: 20px; color: #333; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; color: #555; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        .btn { background: #007bff; color: #fff; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 15px; font-weight: bold; }
        .btn:hover { background: #0056b3; }
        .error { color: #d9534f; background: #fdf7f7; border: 1px solid #d9534f; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; }
    </style>
</head>
<body>
<div class="card">
    <h2>Create First Admin Account</h2>
    <?php if ($message): ?>
        <div class="error"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" required placeholder="System Admin">
        </div>
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" required placeholder="admin@example.com">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="••••••••">
        </div>
        <button type="submit" class="btn">Create Admin</button>
    </form>
</div>
</body>
</html>