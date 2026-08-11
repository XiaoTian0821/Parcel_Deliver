<?php
/** @var string $pageTitle */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= escape(csrf_token()) ?>">
    <title><?= escape($pageTitle ?? APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= escape(asset_url('css/style.css')) ?>">
    <script src="<?= escape(asset_url('js/app.js')) ?>" defer></script>
</head>
<body class="auth-body" data-base-url="<?= escape(base_url()) ?>">
    <main class="auth-wrapper">
