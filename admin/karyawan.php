<?php
require dirname(__DIR__).'/config/bootstrap.php';
require_once dirname(__DIR__).'/includes/layout.php';
$admin=require_role('admin');

$validDate=static function($value):bool {
    if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value))return false;
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);$errors=DateTimeImmutable::getLastErrors();
    return $date!==false&&($errors===false||($errors['warning_count']===0&&$errors['error_count']===0))&&$date->format('Y-m-d')===$value;
};
$money=static function($value):float|false {
    if(!is_scalar($value)||!is_numeric($value))return false;
    $amount=(float)$value;
    return is_finite($amount)&&$amount>=0&&$amount<=9999999999.99?$amount:false;
};

if($_SERVER['REQUEST_METHOD']==='POST'){
    require_csrf();
    $action=$_POST['action']??'';
    $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
    try{
        if($action==='toggle'&&$id&&in_array($_POST['status']??'', ['aktif','nonaktif'],true)){
            $stmt=$pdo->prepare('UPDATE users SET status_kerja=? WHERE id=? AND role=\'karyawan\'');
            $stmt->execute([$_POST['status'],$id]);
            if($stmt->rowCount())audit_event($pdo,(int)$admin['id'],'employee.status_changed','user',$id,['status'=>$_POST['status']]);
            flash($stmt->rowCount()?'success':'warning',$stmt->rowCount()?'Status karyawan diperbarui.':'Status tidak berubah atau akun tidak ditemukan.');
            redirect('/hris-presensi/admin/karyawan.php');
        }
        if(!in_array($action,['create','update'],true))throw new DomainException('Aksi tidak valid.');
        $nip=trim((string)($_POST['nip']??''));$name=trim((string)($_POST['nama']??''));
        $email=filter_var(trim((string)($_POST['email']??'')),FILTER_VALIDATE_EMAIL);
        $job=trim((string)($_POST['jabatan']??'Staf'));$division=trim((string)($_POST['divisi']??'Operasional'));
        $startDate=$_POST['tanggal_masuk']??'';$salary=$money($_POST['gaji_pokok']??null);
        $meal=$money($_POST['tunjangan_makan']??null);$transport=$money($_POST['tunjangan_transport']??null);
        $overtimeRate=$money($_POST['tarif_lembur_per_jam']??null);$lateRate=$money($_POST['potongan_terlambat_per_jam']??null);$bpjs=$money($_POST['potongan_bpjs_per_bulan']??null);
        $password=(string)($_POST['password']??'');
        if($nip===''||strlen($nip)>30||$name===''||strlen($name)>100||!$email||strlen($email)>100||$job===''||strlen($job)>80||$division===''||strlen($division)>80||!$validDate($startDate)||$salary===false||$meal===false||$transport===false||$overtimeRate===false||$lateRate===false||$bpjs===false){
            throw new DomainException('Periksa kembali identitas, tanggal mulai kerja, dan nilai komponen gaji.');
        }
        if($action==='update'&&$password!==''&&strlen($password)<10)throw new DomainException('Kata sandi baru minimal 10 karakter.');
        if($action==='create'){
            if(strlen($password)<10)throw new DomainException('Kata sandi karyawan minimal 10 karakter.');
            $stmt=$pdo->prepare("INSERT INTO users (nip,nama,email,password,role,jabatan,divisi,gaji_pokok,tunjangan_makan,tunjangan_transport,tarif_lembur_per_jam,potongan_terlambat_per_jam,potongan_bpjs_per_bulan,tanggal_masuk) VALUES (?,?,?,?,'karyawan',?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$nip,$name,$email,password_hash($password,PASSWORD_DEFAULT),$job,$division,$salary,$meal,$transport,$overtimeRate,$lateRate,$bpjs,$startDate]);
            audit_event($pdo,(int)$admin['id'],'employee.created','user',(int)$pdo->lastInsertId());
            flash('success','Akun karyawan berhasil dibuat.');
        }else{
            if(!$id)throw new DomainException('ID karyawan tidak valid.');
            $stmt=$pdo->prepare("UPDATE users SET nip=?,nama=?,email=?,jabatan=?,divisi=?,gaji_pokok=?,tunjangan_makan=?,tunjangan_transport=?,tarif_lembur_per_jam=?,potongan_terlambat_per_jam=?,potongan_bpjs_per_bulan=?,tanggal_masuk=? WHERE id=? AND role='karyawan'");
            $stmt->execute([$nip,$name,$email,$job,$division,$salary,$meal,$transport,$overtimeRate,$lateRate,$bpjs,$startDate,$id]);
            if($password!==''){
                $pw=$pdo->prepare("UPDATE users SET password=? WHERE id=? AND role='karyawan'");$pw->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
            }
            audit_event($pdo,(int)$admin['id'],'employee.updated','user',(int)$id,['tanggal_masuk'=>$startDate]);
            flash('success','Data karyawan berhasil diperbarui.');
        }
    }catch(DomainException $e){flash('danger',$e->getMessage());}
    catch(PDOException $e){flash('danger','NIP atau email sudah digunakan, atau data gagal disimpan.');}
    redirect('/hris-presensi/admin/karyawan.php');
}

$rows=$pdo->query("SELECT id,nip,nama,email,jabatan,divisi,gaji_pokok,tunjangan_makan,tunjangan_transport,tarif_lembur_per_jam,potongan_terlambat_per_jam,potongan_bpjs_per_bulan,status_kerja,COALESCE(tanggal_masuk,DATE(created_at)) AS tanggal_masuk FROM users WHERE role='karyawan' ORDER BY nama")->fetchAll();
page_header('Data karyawan'); ?>
<div class="mb-4"><div class="eyebrow">Manajemen SDM</div><h1 class="page-title">Data karyawan</h1><p class="page-subtitle">Kelola akun, status kerja, tanggal mulai, dan komponen gaji.</p></div>
<div class="row g-4"><div class="col-lg-5"><form method="post" class="panel"><h2 class="h5 fw-bold mb-3">Tambah karyawan</h2><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create">
<label class="form-label">NIP</label><input class="form-control mb-3" name="nip" maxlength="30" required><label class="form-label">Nama lengkap</label><input class="form-control mb-3" name="nama" maxlength="100" required><label class="form-label">Email</label><input class="form-control mb-3" type="email" name="email" maxlength="100" required>
<div class="row g-2"><div class="col"><label class="form-label">Jabatan</label><input class="form-control mb-3" name="jabatan" maxlength="80" value="Staf" required></div><div class="col"><label class="form-label">Divisi</label><input class="form-control mb-3" name="divisi" maxlength="80" value="Operasional" required></div></div><label class="form-label">Tanggal mulai kerja</label><input class="form-control mb-3" type="date" name="tanggal_masuk" value="<?=e(date('Y-m-d'))?>" required>
<label class="form-label">Gaji pokok / bulan</label><input class="form-control mb-3" type="number" min="0" step="1000" name="gaji_pokok" value="4500000" required><div class="row g-2"><div class="col"><label class="form-label">Makan / hari hadir</label><input class="form-control mb-3" type="number" min="0" step="1000" name="tunjangan_makan" value="0" required></div><div class="col"><label class="form-label">Transport / hari hadir</label><input class="form-control mb-3" type="number" min="0" step="1000" name="tunjangan_transport" value="0" required></div></div><div class="row g-2"><div class="col"><label class="form-label">Tarif lembur / jam</label><input class="form-control mb-3" type="number" min="0" name="tarif_lembur_per_jam" value="35000" required></div><div class="col"><label class="form-label">Potongan terlambat / jam</label><input class="form-control mb-3" type="number" min="0" name="potongan_terlambat_per_jam" value="25000" required></div></div><label class="form-label">Potongan BPJS / bulan (nominal manual)</label><input class="form-control mb-3" type="number" min="0" name="potongan_bpjs_per_bulan" value="0" required>
<label class="form-label">Kata sandi awal</label><input class="form-control mb-4" type="password" name="password" autocomplete="new-password" minlength="10" required><button class="btn btn-primary w-100">Buat akun</button></form></div>
<div class="col-lg-7"><section class="panel"><h2 class="h5 fw-bold mb-3">Daftar karyawan</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Karyawan</th><th>Status / mulai</th><th>Pengelolaan</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td class="fw-semibold"><?=e($r['nama'])?><div class="small text-secondary"><?=e($r['nip'])?> · <?=e($r['email'])?></div><div class="small text-secondary"><?=e($r['jabatan'])?> · <?=e($r['divisi'])?></div></td><td><span class="badge text-bg-<?=$r['status_kerja']==='aktif'?'success':'secondary'?>"><?=e(ucfirst($r['status_kerja']))?></span><div class="small mt-1"><?=e($r['tanggal_masuk'])?></div></td><td><details><summary class="btn btn-outline-primary btn-sm">Edit data</summary><form method="post" class="panel mt-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?=(int)$r['id']?>">
<label class="form-label">NIP</label><input class="form-control mb-2" name="nip" maxlength="30" value="<?=e($r['nip'])?>" required><label class="form-label">Nama</label><input class="form-control mb-2" name="nama" maxlength="100" value="<?=e($r['nama'])?>" required><label class="form-label">Email</label><input class="form-control mb-2" type="email" name="email" maxlength="100" value="<?=e($r['email'])?>" required><label class="form-label">Jabatan</label><input class="form-control mb-2" name="jabatan" maxlength="80" value="<?=e($r['jabatan'])?>" required><label class="form-label">Divisi</label><input class="form-control mb-2" name="divisi" maxlength="80" value="<?=e($r['divisi'])?>" required><label class="form-label">Tanggal mulai kerja</label><input class="form-control mb-2" type="date" name="tanggal_masuk" value="<?=e($r['tanggal_masuk'])?>" required>
<label class="form-label">Gaji pokok</label><input class="form-control mb-2" type="number" min="0" step="1000" name="gaji_pokok" value="<?=e($r['gaji_pokok'])?>" required><label class="form-label">Tunjangan makan / hari</label><input class="form-control mb-2" type="number" min="0" name="tunjangan_makan" value="<?=e($r['tunjangan_makan'])?>" required><label class="form-label">Tunjangan transport / hari</label><input class="form-control mb-2" type="number" min="0" name="tunjangan_transport" value="<?=e($r['tunjangan_transport'])?>" required><label class="form-label">Tarif lembur / jam</label><input class="form-control mb-2" type="number" min="0" name="tarif_lembur_per_jam" value="<?=e($r['tarif_lembur_per_jam'])?>" required><label class="form-label">Potongan terlambat / jam</label><input class="form-control mb-2" type="number" min="0" name="potongan_terlambat_per_jam" value="<?=e($r['potongan_terlambat_per_jam'])?>" required><label class="form-label">Potongan BPJS / bulan</label><input class="form-control mb-2" type="number" min="0" name="potongan_bpjs_per_bulan" value="<?=e($r['potongan_bpjs_per_bulan'])?>" required><label class="form-label">Kata sandi baru (opsional)</label><input class="form-control mb-3" type="password" name="password" autocomplete="new-password" minlength="10"><button class="btn btn-primary btn-sm w-100">Simpan perubahan</button></form></details>
<form method="post" class="mt-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><input type="hidden" name="status" value="<?=$r['status_kerja']==='aktif'?'nonaktif':'aktif'?>"><button class="btn btn-sm <?=$r['status_kerja']==='aktif'?'btn-outline-danger':'btn-outline-success'?>" data-confirm="<?=$r['status_kerja']==='aktif'?'Nonaktifkan':'Aktifkan'?> akun ini?"><?=$r['status_kerja']==='aktif'?'Nonaktifkan':'Aktifkan'?></button></form></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="3" class="text-center text-secondary py-4">Belum ada karyawan.</td></tr><?php endif; ?></tbody></table></div></section></div></div>
<?php page_footer(); ?>
