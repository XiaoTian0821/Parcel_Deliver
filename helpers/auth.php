<?php

declare(strict_types=1);

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array
{
    static $cachedUser = null;

    if ($cachedUser !== null) {
        return $cachedUser;
    }

    if (!is_logged_in()) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, full_name, email, role, avatar, phone, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $cachedUser = $stmt->fetch() ?: null;

    return $cachedUser;
}

function current_role(): ?string
{
    return current_user()['role'] ?? null;
}

function is_admin(): bool
{
    return current_role() === 'admin';
}

function is_rider(): bool
{
    return current_role() === 'rider';
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('index.php?page=login');
    }
}

function require_role(array $roles): void
{
    require_login();

    if (!in_array((string) current_role(), $roles, true)) {
        http_response_code(403);
        exit('Access denied');
    }
}

function login_user(array $user): void
{
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);

    return $stmt->fetch() ?: null;
}

function attempt_login(string $email, string $password): array
{
    $user = find_user_by_email($email);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid login credentials.'];
    }

    login_user($user);
    activity_log((int) $user['id'], 'login', 'User logged in');

    return [
        'success' => true,
        'message' => 'Login successful.',
        'role' => $user['role'],
    ];
}
