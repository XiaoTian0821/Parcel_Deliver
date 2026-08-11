<?php
/** @var string $pageTitle */
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= escape(csrf_token()) ?>">
    <title><?= escape($pageTitle ?? APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="<?= escape(asset_url('css/style.css')) ?>">
    <link rel="manifest" href="<?= escape(base_url('manifest.json')) ?>">
    <meta name="theme-color" content="#007bff">
    <link rel="apple-touch-icon" href="<?= escape(asset_url('icons/app-icon.svg')) ?>">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
    <?php if (defined('GOOGLE_MAPS_API_KEY') && GOOGLE_MAPS_API_KEY !== ''): ?>
        <script src="https://maps.googleapis.com/maps/api/js?key=<?= escape(GOOGLE_MAPS_API_KEY) ?>&libraries=places" defer></script>
    <?php endif; ?>
    <script src="<?= escape(asset_url('js/app.js')) ?>" defer></script>
</head>
<body data-page="<?= escape($_GET['page'] ?? '') ?>" data-base-url="<?= escape(base_url()) ?>">
<div class="app-shell">
    <?php if ($user): ?>
        <?php include __DIR__ . '/sidebar.php'; ?>
    <?php endif; ?>
    <div class="app-main">
        <header class="topbar">
            <div>
                <p class="eyebrow"><?= escape(APP_NAME) ?></p>
                <h1><?= escape($pageTitle ?? APP_NAME) ?></h1>
            </div>
            <?php if ($user): ?>
                <div class="topbar-user">
                    <span class="user-name"><?= escape($user['full_name']) ?></span>
                    <span class="user-role"><?= escape(ucfirst($user['role'])) ?></span>
                    <a class="btn btn-ghost" href="<?= escape(base_url('index.php?page=logout')) ?>">Logout</a>
                </div>
            <?php endif; ?>
        </header>
        <main class="page-content">
            <?php include __DIR__ . '/flash.php'; ?>
</body>
</html>
<!-- 引入 Service Worker 注册及手机安装提示脚本 -->
<script>
  // 1. 注册 Service Worker
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('<?= escape(base_url('sw.js')) ?>')
        .then(reg => console.log('Service Worker 注册成功:', reg.scope))
        .catch(err => console.log('Service Worker 注册失败:', err));
    });
  }

  // 2. 捕捉并控制手机端的安装提示 (BeforeInstallPrompt Event)
  let deferredPrompt;
  let installButton;

  function ensureInstallButton() {
    if (installButton) {
      return installButton;
    }

    installButton = document.createElement('button');
    installButton.type = 'button';
    installButton.textContent = 'Install app';
    installButton.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:9999;padding:12px 16px;border:0;border-radius:999px;background:#007bff;color:#fff;font-weight:700;box-shadow:0 10px 25px rgba(0,0,0,.18);cursor:pointer;display:none;';
    installButton.addEventListener('click', async () => {
      if (!deferredPrompt) {
        return;
      }

      deferredPrompt.prompt();
      await deferredPrompt.userChoice;
      deferredPrompt = null;
      installButton.style.display = 'none';
    });

    document.body.appendChild(installButton);
    return installButton;
  }

  window.addEventListener('beforeinstallprompt', (e) => {
    // 阻止浏览器默认直接弹出原生的细微提示条，以便自定义调用
    e.preventDefault();
    deferredPrompt = e;

    const button = ensureInstallButton();
    button.style.display = 'block';
  });

  // 监听安装完成事件
  window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    if (installButton) {
      installButton.style.display = 'none';
    }
    console.log('PWA 已成功安装！');
  });
</script>