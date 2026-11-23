<?php
declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function verify_csrf(?string $token): bool { return is_string($token) && hash_equals(csrf_token(), $token); }
function require_csrf(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf(is_string($token) ? $token : null)) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa. Muat ulang halaman dan coba lagi.');
    }
}
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): array
{
    if (!current_user()) { header('Location: /hris-presensi/login.php'); exit; }
    global $pdo;
    $stmt=$pdo->prepare('SELECT id,nip,nama,email,role,jabatan,status_kerja FROM users WHERE id=? LIMIT 1');
    $stmt->execute([current_user()['id']]);$fresh=$stmt->fetch();
    if(!$fresh||$fresh['status_kerja']!=='aktif'){
        $_SESSION=[];session_regenerate_id(true);
        header('Location: /hris-presensi/login.php');exit;
    }
    $_SESSION['user']=$fresh;
    return $fresh;
}
function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) { http_response_code(403); exit('Anda tidak memiliki akses ke halaman ini.'); }
    return $user;
}
function flash(string $type, string $message): void { $_SESSION['flash'] = compact('type', 'message'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }
function rupiah(float|int|string $amount): string { return 'Rp ' . number_format((float)$amount, 0, ',', '.'); }
function setting(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM pengaturan_kantor ORDER BY id LIMIT 1')->fetch() ?: [];
}
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function audit_event(PDO $pdo, ?int $actorId, string $action, string $entityType, ?int $entityId=null, array $details=[]): void
{
    try {
        $stmt=$pdo->prepare('INSERT INTO audit_log (actor_user_id,action,entity_type,entity_id,details,ip_address) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$actorId,$action,$entityType,$entityId,$details?json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,$_SERVER['REMOTE_ADDR']??null]);
    } catch (Throwable $e) {
        error_log('PresensiKu audit log gagal: '.$e->getMessage());
    }
}
