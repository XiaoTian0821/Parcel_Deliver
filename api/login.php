<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/bootstrap.php';

if (request_method() !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    verify_csrf_token();

    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        json_response(['success' => false, 'message' => 'Enter a valid email and password.'], 422);
    }

    $result = attempt_login($email, $password);

    if (!$result['success']) {
        json_response($result, 401);
    }

    json_response($result);
} catch (Throwable $exception) {
    error_log('Login failed: ' . $exception->getMessage());
    json_response([
        'success' => false,
        'message' => 'Login failed on the server. Check the database connection and server error log.',
    ], 500);
}
