<?php
function page_header(string $title): void
{
    $user=current_user();$flash=$_SESSION['flash']??null;unset($_SESSION['flash']);
    $current=preg_replace('#^/hris-presensi/#','',(string)($_SERVER['SCRIPT_NAME']??''));
    $navLink=static function(string $href,string $label,array $pages)use($current):void{
        $active=in_array($current,$pages,true); ?><a class="nav-link<?=$active?' active':''?>" href="<?=e($href)?>"<?=$active?' aria-current="page"':''?>><?=e($label)?></a><?php
    };
    ?>
    <!doctype html><html lang="id"><head>
      <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="csrf-token" content="<?=e(csrf_token())?>">
      <meta name="referrer" content="strict-origin-when-cross-origin">
      <title><?=e($title)?> · PresensiKu</title>
      <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" referrerpolicy="no-referrer">
      <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer">
      <link href="/hris-presensi/assets/css/app.css" rel="stylesheet">
    </head><body>
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>
    <?php if($user): ?><header class="app-header"><nav class="navbar navbar-expand-lg app-nav" aria-label="Navigasi utama"><div class="container">
      <a class="navbar-brand" href="/hris-presensi/dashboard.php"><span class="brand-mark" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></span>Presensi<span>Ku</span></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Buka atau tutup navigasi"><span class="navbar-toggler-icon"></span></button>
      <div class="collapse navbar-collapse" id="navMenu"><div class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
        <?php $navLink('/hris-presensi/dashboard.php','Dashboard',['dashboard.php']);$navLink('/hris-presensi/presensi.php','Presensi',['presensi.php']);$navLink('/hris-presensi/pengajuan.php','Pengajuan',['pengajuan.php']);$navLink('/hris-presensi/slip.php','Slip gaji',['slip.php']); ?>
        <?php if($user['role']==='admin'): ?>
          <?php $navLink('/hris-presensi/admin/pengajuan.php','Verifikasi',['admin/pengajuan.php']);$navLink('/hris-presensi/admin/presensi.php','Rekap presensi',['admin/presensi.php']);$navLink('/hris-presensi/admin/payroll.php','Payroll',['admin/payroll.php']);$navLink('/hris-presensi/admin/hari-libur.php','Hari libur',['admin/hari-libur.php']);$navLink('/hris-presensi/admin/audit.php','Aktivitas',['admin/audit.php']);$navLink('/hris-presensi/admin/pengaturan.php','Pengaturan',['admin/pengaturan.php']);$navLink('/hris-presensi/admin/karyawan.php','Karyawan',['admin/karyawan.php']); ?>
        <?php endif; ?>
        <span class="nav-user"><i class="fa-regular fa-circle-user me-1" aria-hidden="true"></i><?=e($user['nama'])?></span>
        <form method="post" action="/hris-presensi/logout.php" class="m-0"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><button class="btn btn-outline-secondary btn-sm">Keluar</button></form>
      </div></div>
    </div></nav></header><?php endif; ?>
    <main id="main-content" class="container py-4 py-lg-5" tabindex="-1">
    <?php if($flash): ?><div class="alert alert-<?=e($flash['type'])?> alert-dismissible fade show" role="<?=$flash['type']==='danger'?'alert':'status'?>" aria-atomic="true"><?=e($flash['message'])?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup pemberitahuan"></button></div><?php endif; ?>
    <?php
}
function page_footer(): void
{
    ?>
    </main><footer class="app-footer"><div class="container">PresensiKu · Presensi kerja yang lebih mudah</div></footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/hris-presensi/assets/js/app.js"></script></body></html>
    <?php
}
