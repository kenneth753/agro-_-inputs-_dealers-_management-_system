<?php
declare(strict_types=1);
session_start();

const DB_HOST = 'localhost';
const DB_NAME = 'agro_input_db';
const DB_USER = 'root';
const DB_PASS = '';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    // Auto-add avatar column if missing (safe to run every request)
    try { $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(500) DEFAULT NULL"); } catch (PDOException $e) { /* already exists */ }
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Check config.php and import database.sql.');
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(float|int|string $amount): string {
    return 'UGX ' . number_format((float)$amount, 0);
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Your form session expired. Go back, refresh the page and try again.');
    }
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void {
    if (!current_user() || current_user()['role'] !== 'admin') {
        http_response_code(403);
        exit('Access denied. Administrator access is required.');
    }
}

function flash(string $key, ?string $message = null): ?string {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function cart_count(): int {
    return array_sum(array_map('intval', $_SESSION['cart'] ?? []));
}

function user_avatar(?string $avatar, string $name = 'U', int $size = 40): string {
    if ($avatar && file_exists(__DIR__ . '/' . $avatar)) {
        return '<img src="' . htmlspecialchars($avatar) . '" alt="' . htmlspecialchars($name) . '" style="width:'.$size.'px;height:'.$size.'px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.3);">';
    }
    $initial = strtoupper(mb_substr($name, 0, 1));
    return '<div style="width:'.$size.'px;height:'.$size.'px;border-radius:50%;background:linear-gradient(135deg,#1565c0,#42a5f5);display:inline-flex;align-items:center;justify-content:center;color:#fff;font:700 '.round($size*.4).'px Inter,sans-serif;border:2px solid rgba(255,255,255,.3);">'.$initial.'</div>';
}
