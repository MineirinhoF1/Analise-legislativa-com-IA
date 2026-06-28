<?php
/**
 * Bootstrap comum: autoload simples das classes do projeto + helpers de JSON.
 */
declare(strict_types=1);

if (!defined('RESUMO_APP_ROOT')) {
    define('RESUMO_APP_ROOT', dirname(__DIR__));
}

function loadEnvFile(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if ($name === '' || getenv($name) !== false) {
            continue;
        }
        $value = trim($value, "\"'");
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

loadEnvFile(RESUMO_APP_ROOT . '/.env');

function envValue(string $name, string $default = ''): string
{
    $value = getenv($name);
    return is_string($value) && $value !== '' ? $value : $default;
}

function storagePath(string $path = ''): string
{
    $base = rtrim(envValue('RESUMO_STORAGE_DIR', RESUMO_APP_ROOT . '/storage/private'), "/\\");
    return $path === '' ? $base : $base . '/' . ltrim($path, "/\\");
}

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

/** Responde JSON e encerra. */
function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Lê o corpo JSON da requisição como array. */
function jsonInput(): array
{
    $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($len > 2 * 1024 * 1024) {
        jsonResponse(['ok' => false, 'erro' => 'Requisição grande demais.'], 413);
    }
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

/** Token simples para proteger ações mutáveis feitas pelo frontend local. */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Exige o token CSRF no header X-CSRF-Token para POST/ações destrutivas. */
function requireCsrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || $sent === '' || !hash_equals(csrfToken(), $sent)) {
        jsonResponse(['ok' => false, 'erro' => 'Sessão expirada ou requisição inválida. Recarregue a página.'], 403);
    }
}
