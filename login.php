<?php
require __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
if (current_user()) redirect('/hris-presensi/dashboard.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string)($_POST['password'] ?? '');
    $stmt = $pdo->prepare("SELECT id,nip,nama,email,password,role,jabatan,status_kerja FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$email ?: '']);
    $account = $stmt->fetch();
    if ($account && $account['status_kerja'] === 'aktif' && password_verify($password, $account['password'])) {
        session_regenerate_id(true);
        unset($account['password']);
        $_SESSION['user'] = $account;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        redirect('/hris-presensi/dashboard.php');
    }
    $error = 'Email atau kata sandi tidak cocok, atau akun tidak aktif.';
}
page_header('Masuk');
?>
<section class="login-shell">
  <div class="login-side mb-3"><div class="login-brand mb-3"><i class="fa-solid fa-location-dot me-2"></i>PresensiKu</div><h1 class="h4 fw-bold">Presensi kerja lebih sederhana.</h1><p class="mb-0 opacity-75">Kelola kehadiran, pengajuan, dan slip gaji dalam satu tempat.</p></div>
  <div class="panel"><h2 class="h4 fw-bold mb-1">Selamat datang</h2><p class="text-secondary mb-4">Masuk ke akun HRIS Anda.</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <label class="form-label" for="email">Email</label><input class="form-control mb-3" id="email" type="email" name="email" required autocomplete="username">
      <label class="form-label" for="password">Kata sandi</label><input class="form-control mb-4" id="password" type="password" name="password" required autocomplete="current-password">
      <button class="btn btn-primary w-100 py-2 fw-semibold">Masuk</button>
    </form>
    <div class="small text-secondary mt-3">Akun demo tersedia pada bagian instalasi README.</div>
  </div>
</section>
<?php page_footer(); ?>
