<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $instance = null;
    private static ?string $connectionError = null;

    private static function isApiRequest(): bool
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        return str_contains($requestUri, '/api/') || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    private static function failConnection(PDOException $exception): never
    {
        self::$connectionError = $exception->getMessage();

        if (self::isApiRequest()) {
            json_response([
                'success' => false,
                'message' => 'Database connection failed. Check config/local.php or your PARCEL_DB_* environment variables.',
            ], 503);
        }

        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Database Connection Error</title></head><body style="font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;padding:32px;line-height:1.6;">';
        echo '<div style="max-width:760px;margin:0 auto;background:#111827;border:1px solid rgba(148,163,184,.2);border-radius:18px;padding:24px;">';
        echo '<h1 style="margin-top:0;">Database connection failed</h1>';
        echo '<p>The app could not connect to MySQL using the current credentials.</p>';
        echo '<p>Create <strong>config/local.php</strong> and return an array with your local database values, or set the PARCEL_DB_* environment variables.</p>';
        echo '<p>For example: <code>DB_USER</code>, <code>DB_PASS</code>, and <code>DB_HOST</code>. If your MySQL root account has a password, put it there.</p>';
        echo '</div></body></html>';
        exit;
    }

    public static function connect(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        if (DB_SOCKET !== '') {
            $dsn .= ';unix_socket=' . DB_SOCKET;
        }

        try {
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            self::failConnection($exception);
        }

        return self::$instance;
    }

    public static function lastConnectionError(): ?string
    {
        return self::$connectionError;
    }
}

function db(): PDO
{
    return Database::connect();
}
