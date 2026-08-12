<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$pageTitle = 'Create Account';

// 如果已登录，直接跳转到对应的 Dashboard
if (is_logged_in()) {
    redirect(is_admin() ? 'index.php?page=admin-dashboard' : 'index.php?page=rider-dashboard');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
    $password = (string) ($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'rider'; // 默认注册为 rider（骑手/配送员）

    if ($fullName === '' || $email === '' || $password === '') {
        $error = 'Please fill in all required fields with a valid email.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // 读取数据库配置连接 PDO
        $config = require __DIR__ . '/../config/local.php';
        try {
            $dsn = "mysql:host=" . ($config['DB_HOST'] ?? '127.0.0.1') . ";dbname=" . ($config['DB_NAME'] ?? 'parcel_deliver') . ";charset=utf8mb4";
            $pdo = new PDO($dsn, $config['DB_USER'] ?? 'root', $config['DB_PASS'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // 检查邮箱是否已被注册
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'This email address is already registered.';
            } else {
                // 写入新用户
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$fullName, $email, $hashedPassword, $role]);

                // 如果是注册 Rider 角色，顺便在 riders 表新增对应记录
                if ($role === 'rider') {
                    $userId = (int)$pdo->lastInsertId();
                    $employeeCode = 'RIDER-' . str_pad((string)$userId, 4, '0', STR_PAD_LEFT);
                    $stmtRider = $pdo->prepare("INSERT INTO riders (user_id, employee_code) VALUES (?, ?)");
                    $stmtRider->execute([$userId, $employeeCode]);
                }

                $_SESSION['flash_message'] = 'Account created successfully! Please sign in.';
                $_SESSION['flash_type'] = 'success';
                redirect('index.php?page=login');
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

require __DIR__ . '/../includes/auth-header.php';
?>

<section class="auth-card login-card">
    <div class="auth-copy">
        <p class="eyebrow">Join the platform</p>
        <h1>Create a new account</h1>
        <p>Fill out the information below to register as a new user in the Parcel Delivery System.</p>
        <p style="margin-top: 15px;">
            Already have an account? 
            <a class="text-link" href="index.php?page=login" style="position: relative; z-index: 10; cursor: pointer; font-weight: bold;">
                Sign in here
            </a>
        </p>
    </div>

    <form class="stack-form" method="post" action="index.php?page=register">
        <?php if ($error): ?>
            <div style="background: #fdf2f2; color: #9b1c1c; padding: 10px; border-radius: 4px; margin-bottom: 12px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <label>Full Name
            <input type="text" name="full_name" required placeholder="John Doe" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
        </label>

        <label>Email Address
            <input type="email" name="email" required placeholder="user@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </label>

        <label>Password
            <input type="password" name="password" required placeholder="At least 6 characters">
        </label>

        <label>Account Role
            <select name="role" style="width:100%; padding: 10px; margin-top: 4px; border: 1px solid #ccc; border-radius: 4px;">
                <option value="rider">Rider / Delivery Driver</option>
                <option value="admin">Administrator</option>
            </select>
        </label>

        <button class="btn btn-primary" type="submit" style="margin-top: 10px;">Register Account</button>
    </form>
</section>

<?php require __DIR__ . '/../includes/auth-footer.php'; ?>