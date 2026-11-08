<?php
declare(strict_types=1);

function cfg(): array { static $c = null; return $c ??= require __DIR__ . '/../config/config.php'; }
date_default_timezone_set(cfg()['timezone'] ?? 'Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_name('MTSESS');
    session_start();
}
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

set_exception_handler(function (Throwable $t) {
    error_log('[MaggotTrack] ' . $t);
    http_response_code(500);
    echo 'Terjadi kesalahan pada server. Pastikan MySQL aktif dan konfigurasi database benar.';
});

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $c = cfg();
        $pdo = new PDO("mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4", $c['db_user'], $c['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $u) { header('Location: ' . $u); exit; }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) { http_response_code(419); exit('Sesi tidak valid. Muat ulang halaman.'); }
}

function uid(): int { return (int)($_SESSION['uid'] ?? 0); }
function is_admin(): bool { return ($_SESSION['role'] ?? '') === 'admin'; }
function require_login(): void { if (!uid()) redirect('login.php'); }

function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function take_flash(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

/** Pembatasan akses: admin melihat semua, staff hanya batch miliknya. */
function batch_scope(string $alias = 'b'): array {
    return is_admin() ? ['1=1', []] : ["$alias.user_id = ?", [uid()]];
}

function notifications(): array {
    [$w, $p] = batch_scope('b');
    $s = db()->prepare("SELECT b.batch_code, b.name, DATEDIFF(b.target_harvest_date, CURDATE()) AS days_left
        FROM batches b WHERE $w AND b.status IN ('aktif','siap_panen') AND b.target_harvest_date IS NOT NULL
        AND DATEDIFF(b.target_harvest_date, CURDATE()) <= 3 ORDER BY days_left");
    $s->execute($p);
    return $s->fetchAll();
}

function fmt_date(?string $d): string { return $d ? date('d M Y', strtotime($d)) : '-'; }
function fmt_num($n, int $dec = 2): string { return number_format((float)$n, $dec, ',', '.'); }
