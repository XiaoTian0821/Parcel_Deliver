<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/upload.php';

date_default_timezone_set(APP_TIMEZONE);

ensure_directory(UPLOAD_BASE_PATH);
ensure_directory(PROOF_UPLOAD_PATH);
ensure_directory(AVATAR_UPLOAD_PATH);
