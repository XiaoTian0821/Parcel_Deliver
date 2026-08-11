<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$pageTitle = 'Secure Login';

if (is_logged_in()) {
    redirect(is_admin() ? 'index.php?page=admin-dashboard' : 'index.php?page=rider-dashboard');
}

if (request_method() === 'POST') {
    verify_csrf_token();

    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $_SESSION['flash_message'] = 'Enter a valid email and password.';
        $_SESSION['flash_type'] = 'danger';
    } else {
        $result = attempt_login($email, $password);

        if ($result['success']) {
            redirect($result['role'] === 'admin' ? 'index.php?page=admin-dashboard' : 'index.php?page=rider-dashboard');
        }

        $_SESSION['flash_message'] = $result['message'];
        $_SESSION['flash_type'] = 'danger';
    }
}

require __DIR__ . '/../includes/auth-header.php';
?>
<section class="auth-card login-card">
    <div class="auth-copy">
        <p class="eyebrow">Courier operations</p>
        <h1>Sign in to Parcel Delivery.</h1>
        <p>Use your admin or rider account to manage deliveries, track locations, and update parcel status in real time.</p>
        <!-- 使用直接链接路径，防止 base_url 拼错 -->
        <p style="margin-top: 15px;">
            <a class="text-link" href="index.php?page=install" style="position: relative; z-index: 10; cursor: pointer;">
                Create the first admin account
            </a>
        </p>
    </div>
    <form class="stack-form ajax-form" method="post" action="<?= escape(base_url('api/login.php')) ?>" data-redirect-admin="<?= escape(base_url('index.php?page=admin-dashboard')) ?>" data-redirect-rider="<?= escape(base_url('index.php?page=rider-dashboard')) ?>">
        <?= csrf_field() ?>
        <label>Email Address
            <input type="email" name="email" required autocomplete="email">
        </label>
        <label>Password
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="btn btn-primary" type="submit">Login</button>
        <p class="form-note">GPS updates and parcel changes require the page to remain open while the rider is online.</p>
    </form>
</section>
<?php require __DIR__ . '/../includes/auth-footer.php'; ?>