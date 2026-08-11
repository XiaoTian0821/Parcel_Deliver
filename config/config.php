<?php

declare(strict_types=1);

$localConfig = [];
$localConfigFile = __DIR__ . '/local.php';

if (is_file($localConfigFile)) {
	$localConfig = require $localConfigFile;
	if (!is_array($localConfig)) {
		$localConfig = [];
	}
}

define('APP_NAME', 'Parcel Delivery Management System');
define('APP_TIMEZONE', 'UTC');
define('DB_HOST', $localConfig['DB_HOST'] ?? (getenv('PARCEL_DB_HOST') !== false ? getenv('PARCEL_DB_HOST') : '127.0.0.1'));
define('DB_NAME', $localConfig['DB_NAME'] ?? (getenv('PARCEL_DB_NAME') !== false ? getenv('PARCEL_DB_NAME') : 'parcel_delivery'));
define('DB_USER', $localConfig['DB_USER'] ?? (getenv('PARCEL_DB_USER') !== false ? getenv('PARCEL_DB_USER') : 'root'));
define('DB_PASS', $localConfig['DB_PASS'] ?? (getenv('PARCEL_DB_PASS') !== false ? getenv('PARCEL_DB_PASS') : ''));
define('DB_SOCKET', $localConfig['DB_SOCKET'] ?? (getenv('PARCEL_DB_SOCKET') !== false ? getenv('PARCEL_DB_SOCKET') : ''));

define('UPLOAD_BASE_PATH', __DIR__ . '/../uploads');
define('PROOF_UPLOAD_PATH', UPLOAD_BASE_PATH . '/proof');
define('AVATAR_UPLOAD_PATH', UPLOAD_BASE_PATH . '/avatars');
