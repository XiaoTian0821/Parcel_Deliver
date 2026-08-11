<?php

declare(strict_types=1);

function app_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

    if ($scriptName === '/' || $scriptName === '\\' || $scriptName === '.') {
        $scriptName = '';
    }

    return rtrim($scheme . '://' . $host . $scriptName, '/');
}

function base_url(string $path = ''): string
{
    return app_base_url() . '/' . ltrim($path, '/');
}

function asset_url(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

function escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : base_url($path)));
    exit;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function sanitize_text(?string $value): string
{
    return trim((string) filter_var($value ?? '', FILTER_UNSAFE_RAW));
}

function int_value(mixed $value, int $default = 0): int
{
    return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $default;
}

function float_value(mixed $value, ?float $default = null): ?float
{
    return filter_var($value, FILTER_VALIDATE_FLOAT) !== false ? (float) $value : $default;
}

function json_response(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return date('M d, Y h:i A', strtotime($value));
}

function format_date_only(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return date('M d, Y', strtotime($value));
}

function generate_tracking_number(): string
{
    return 'PD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function activity_log(?int $userId, string $actionType, string $description): void
{
    try {
        $stmt = db()->prepare('INSERT INTO activity_logs (user_id, action_type, description, ip_address, user_agent, created_at) VALUES (:user_id, :action_type, :description, :ip_address, :user_agent, NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'action_type' => $actionType,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } catch (Throwable $exception) {
        error_log('Activity log failed: ' . $exception->getMessage());
    }
}

function ensure_directory(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}
