<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// index.php 示例结构
$page = $_GET['page'] ?? 'login';

if ($page === '') {
    if (!is_logged_in()) {
        $page = 'login';
    } else {
        $page = is_admin() ? 'admin-dashboard' : 'rider-dashboard';
    }
}

if ($page === 'logout') {
    logout_user();
    $_SESSION['flash_message'] = 'You have been logged out successfully.';
    $_SESSION['flash_type'] = 'success';
    redirect('index.php?page=login');
}

$routes = [
    'login' => __DIR__ . '/pages/login.php',
    'register' => __DIR__ . '/pages/register.php',
    'admin-dashboard' => __DIR__ . '/pages/admin/dashboard.php',
    'admin-riders' => __DIR__ . '/pages/admin/riders.php',
    'admin-parcels' => __DIR__ . '/pages/admin/parcels.php',
    'admin-tracking' => __DIR__ . '/pages/admin/tracking.php',
    'admin-reports' => __DIR__ . '/pages/admin/reports.php',
    'admin-profile' => __DIR__ . '/pages/admin/profile.php',
    'rider-dashboard' => __DIR__ . '/pages/rider/dashboard.php',
    'rider-parcels' => __DIR__ . '/pages/rider/parcels.php',
    'rider-history' => __DIR__ . '/pages/rider/history.php',
    'rider-profile' => __DIR__ . '/pages/rider/profile.php',
    'install' => __DIR__ . '/install.php',
];

if ($page !== 'login' && $page !== 'install' && $page !== 'register') {
    require_login();
}

if ($page === 'login' && is_logged_in()) {
    redirect(is_admin() ? 'index.php?page=admin-dashboard' : 'index.php?page=rider-dashboard');
}

if (!isset($routes[$page])) {
    http_response_code(404);
    $pageTitle = 'Page Not Found';
    require __DIR__ . '/pages/errors/404.php';
    exit;
}

if ($page !== 'login' && $page !== 'install') {
    if (str_starts_with($page, 'admin-')) {
        require_role(['admin']);
    }

    if (str_starts_with($page, 'rider-')) {
        require_role(['rider']);
    }
}

$pageTitle = match ($page) {
    'login' => 'Secure Login',
    'admin-dashboard' => 'Admin Dashboard',
    'admin-riders' => 'Rider Management',
    'admin-parcels' => 'Parcel Management',
    'admin-tracking' => 'Rider Tracking',
    'admin-reports' => 'Reports',
    'admin-profile' => 'Admin Profile',
    'rider-dashboard' => 'Rider Dashboard',
    'rider-parcels' => 'Assigned Parcels',
    'rider-history' => 'Delivery History',
    'rider-profile' => 'Rider Profile',
    'install' => 'Install System',
    default => APP_NAME,
};

require $routes[$page];
