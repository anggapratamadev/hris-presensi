<?php
require __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
$today = date('Y-m-d');
if ($user['role'] === 'admin') {
    $stats = [
        'Karyawan aktif' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='karyawan' AND status_kerja='aktif'")->fetchColumn(),
    ];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM presensi WHERE tanggal=? AND status IN ('hadir','terlambat')"); $stmt->execute([$today]); $stats['Hadir hari ini'] = (int)$stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COUNT(*) FROM pengajuan WHERE status='pending'"); $stats['Menunggu verifikasi'] = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT p.*,u.nama,u.nip FROM presensi p JOIN users u ON u.id=p.user_id WHERE p.tanggal=? ORDER BY p.jam_masuk DESC LIMIT 8"); $stmt->execute([$today]); $rows=$stmt->fetchAll();
} else {
    $stmt=$pdo->prepare('SELECT * FROM presensi WHERE user_id=? AND tanggal=?'); $stmt->execute([$user['id'],$today]); $todayAttendance=$stmt->fetch();
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM presensi WHERE user_id=? AND DATE_FORMAT(tanggal,'%Y-%m')=? AND status IN ('hadir','terlambat')"); $stmt->execute([$user['id'],date('Y-m')]); $monthPresent=(int)$stmt->fetchColumn();
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM pengajuan WHERE user_id=? AND status='pending'"); $stmt->execute([$user['id']]); $pending=(int)$stmt->fetchColumn();
    $stmt=$pdo->prepare('SELECT * FROM presensi WHERE user_id=? ORDER BY tanggal DESC LIMIT 8'); $stmt->execute([$user['id']]); $rows=$stmt->fetchAll();
}
page_header('Dashboard');
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"><div><div class="eyebrow">Ringkasan</div><h1 class="page-title mb-1">Halo, <?= e(explode(' ', $user['nama'])[0]) ?> 👋</h1><p class="page-subtitle mb-0"><?= e(date('l, d F Y')) ?> · <?= $user['role']==='admin'?'Panel administrasi HR':'Berikut ringkasan aktivitas Anda' ?></p></div>
  <?php if ($user['role']==='karyawan'): ?><a class="btn btn-primary" href="/hris-presensi/presensi.php"><i class="fa-solid fa-location-crosshairs me-2"></i>Presensi sekarang</a><?php endif; ?>
</div>
<?php if ($user['role']==='admin'): ?>
  <div class="row g-3 mb-4"><?php foreach($stats as $label=>$value): ?><div class="col-md-4"><div class="stat-card"><div class="d-flex justify-content-between"><div><div class="stat-label"><?= e($label) ?></div><div class="stat-value"><?= number_format($value) ?></div></div><div class="stat-icon"><i class="fa-solid fa-chart-simple"></i></div></div></div></div><?php endforeach; ?></div>
<?php else: ?>
  <div class="row g-3 mb-4"><div class="col-md-4"><div class="stat-card"><div class="stat-label">Kehadiran bulan ini</div><div class="stat-value"><?= $monthPresent ?> <span class="fs-6 fw-normal text-secondary">hari</span></div></div></div><div class="col-md-4"><div class="stat-card"><div class="stat-label">Pengajuan menunggu</div><div class="stat-value"><?= $pending ?></div></div></div><div class="col-md-4"><div class="stat-card"><div class="stat-label">Status hari ini</div><div class="stat-value fs-5"><?= !$todayAttendance?'Belum presensi':e(ucfirst($todayAttendance['status'])) ?></div><?php if($todayAttendance): ?><div class="small text-secondary mt-2">Masuk <?= e($todayAttendance['jam_masuk']??'—') ?> · Pulang <?= e($todayAttendance['jam_pulang']??'—') ?></div><?php endif; ?></div></div></div>
<?php endif; ?>
<section class="panel"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 fw-bold mb-0"><?= $user['role']==='admin'?'Aktivitas presensi hari ini':'Riwayat presensi terakhir' ?></h2><a class="small fw-semibold text-success text-decoration-none" href="/hris-presensi/presensi.php">Lihat presensi</a></div>
  <div class="table-responsive"><table class="table"><caption class="visually-hidden"><?= $user['role']==='admin'?'Aktivitas presensi hari ini':'Riwayat presensi terakhir' ?></caption><thead><tr><?php if($user['role']==='admin'): ?><th scope="col">Karyawan</th><?php endif; ?><th scope="col">Tanggal</th><th scope="col">Masuk</th><th scope="col">Pulang</th><th scope="col">Status</th><th scope="col">Terlambat</th></tr></thead><tbody>
  <?php foreach($rows as $r): ?><tr><?php if($user['role']==='admin'): ?><td><span class="fw-semibold"><?= e($r['nama']) ?></span><div class="small text-secondary"><?= e($r['nip']) ?></div></td><?php endif; ?><td><?= e(date('d M Y',strtotime($r['tanggal']))) ?></td><td><?= e($r['jam_masuk']??'—') ?></td><td><?= e($r['jam_pulang']??'—') ?></td><td><span class="badge rounded-pill badge-soft"><?= e(ucfirst($r['status'])) ?></span></td><td><?= (int)$r['menit_terlambat'] ?> mnt</td></tr><?php endforeach; ?>
  <?php if(!$rows): ?><tr><td colspan="<?=$user['role']==='admin'?6:5?>" class="text-center py-4 text-secondary">Belum ada catatan presensi.</td></tr><?php endif; ?></tbody></table></div>
</section>
<?php page_footer(); ?>
