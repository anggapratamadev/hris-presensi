<?php
require __DIR__.'/config/bootstrap.php'; require_once __DIR__.'/includes/layout.php';
$user=require_login();
if($_SERVER['REQUEST_METHOD']==='POST'){
  require_csrf();
  $kind=$_POST['jenis']??''; $allowed=['cuti','izin','sakit','lembur'];
  $start=$_POST['tanggal_mulai']??''; $end=$_POST['tanggal_selesai']??''; $note=trim((string)($_POST['keterangan']??''));
  $hours=(int)($_POST['jam_lembur']??0); $fileName=null;
  try{
    $parseDate=static function(string $value): ?DateTimeImmutable {
      if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value))return null;
      $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);$errors=DateTimeImmutable::getLastErrors();
      if(!$date||($errors!==false&&($errors['warning_count']>0||$errors['error_count']>0))||$date->format('Y-m-d')!==$value)return null;
      return $date;
    };
    $startDate=$parseDate((string)$start);$endDate=$parseDate((string)$end);
    if(!in_array($kind,$allowed,true)||!$startDate||!$endDate||$endDate<$startDate||$note==='') throw new DomainException('Lengkapi jenis, tanggal, dan keterangan dengan benar.');
    if($startDate->diff($endDate)->days>120) throw new DomainException('Rentang pengajuan maksimal 120 hari kalender.');
    if($kind==='lembur' && ($start!==$end || $hours<1 || $hours>12)) throw new DomainException('Pengajuan lembur harus untuk satu tanggal, antara 1 sampai 12 jam.');
    if($kind==='sakit'&&(!isset($_FILES['lampiran'])||$_FILES['lampiran']['error']===UPLOAD_ERR_NO_FILE)) throw new DomainException('Lampiran surat dokter wajib disertakan untuk pengajuan sakit.');
    if($kind!=='lembur' && strtotime($start)<strtotime(date('Y-m-d'))) throw new DomainException('Tanggal pengajuan tidak boleh di masa lalu.');
    $days=0; for($d=$startDate;$d<=$endDate;$d=$d->modify('+1 day')) { if((int)$d->format('N')<6)$days++; if($days>60)throw new DomainException('Maksimal pengajuan adalah 60 hari kerja.'); }
    if($kind!=='lembur' && $days<1) throw new DomainException('Rentang pengajuan tidak memuat hari kerja.');
    if(isset($_FILES['lampiran'])&&$_FILES['lampiran']['error']!==UPLOAD_ERR_NO_FILE){
      $f=$_FILES['lampiran']; if($f['error']!==UPLOAD_ERR_OK||$f['size']>5*1024*1024) throw new DomainException('Lampiran gagal diunggah atau melebihi 5 MB.');
      $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']); $ext=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$mime]??null;
      if(!$ext) throw new DomainException('Lampiran hanya boleh PDF, JPG, atau PNG.');
      $folder=__DIR__.'/storage/attachments'; if(!is_dir($folder))mkdir($folder,0750,true);
      $fileName=$user['id'].'_'.bin2hex(random_bytes(12)).'.'.$ext;
      if(!move_uploaded_file($f['tmp_name'],$folder.'/'.$fileName)) throw new RuntimeException('Lampiran tidak dapat disimpan.');
    }
    $stmt=$pdo->prepare('INSERT INTO pengajuan (user_id,jenis,tanggal_mulai,tanggal_selesai,jumlah_hari,jam_lembur,keterangan,berkas_lampiran) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$user['id'],$kind,$start,$end,$kind==='lembur'?0:$days,$kind==='lembur'?$hours:0,$note,$fileName]);
    audit_event($pdo,(int)$user['id'],'request.created','pengajuan',(int)$pdo->lastInsertId(),['jenis'=>$kind,'tanggal_mulai'=>$start,'tanggal_selesai'=>$end]);
    flash('success','Pengajuan berhasil dikirim untuk ditinjau HR.'); redirect('/hris-presensi/pengajuan.php');
  }catch(DomainException $e){if($fileName)@unlink(__DIR__.'/storage/attachments/'.$fileName);$error=$e->getMessage();}catch(Throwable $e){if($fileName)@unlink(__DIR__.'/storage/attachments/'.$fileName);$error='Pengajuan gagal disimpan. Periksa data dan coba kembali.';}
}
$stmt=$pdo->prepare('SELECT * FROM pengajuan WHERE user_id=? ORDER BY created_at DESC LIMIT 50');$stmt->execute([$user['id']]);$requests=$stmt->fetchAll();
page_header('Pengajuan');
?>
<div class="mb-4"><div class="eyebrow">Cuti · izin · lembur</div><h1 class="page-title mb-1">Pengajuan saya</h1><p class="page-subtitle">Kirim permohonan dan pantau status persetujuan dari HR.</p></div>
<?php if(isset($error)): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<div class="row g-4"><div class="col-lg-5"><section class="panel"><h2 class="h5 fw-bold mb-3">Buat pengajuan</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
<label class="form-label">Jenis pengajuan</label><select class="form-select mb-3" name="jenis" id="request-kind" required><option value="">Pilih jenis</option><option value="cuti">Cuti</option><option value="izin">Izin</option><option value="sakit">Sakit</option><option value="lembur">Lembur</option></select>
<div class="row g-2 mb-3"><div class="col"><label class="form-label">Tanggal mulai</label><input class="form-control" type="date" name="tanggal_mulai" required></div><div class="col" id="end-date-wrap"><label class="form-label">Tanggal selesai</label><input class="form-control" type="date" name="tanggal_selesai" required></div></div>
<div id="overtime-wrap" class="mb-3 d-none"><label class="form-label">Durasi lembur (jam)</label><input class="form-control" type="number" min="1" max="12" name="jam_lembur" value="1"></div>
<label class="form-label">Keterangan</label><textarea class="form-control mb-3" name="keterangan" rows="3" maxlength="2000" required></textarea>
<label class="form-label" id="attachment-label">Lampiran (opsional, PDF/JPG/PNG, maks 5 MB)</label><input class="form-control mb-4" type="file" id="attachment-input" name="lampiran" accept=".pdf,.jpg,.jpeg,.png">
<button class="btn btn-primary w-100">Kirim pengajuan</button></form></section></div>
<div class="col-lg-7"><section class="panel"><h2 class="h5 fw-bold mb-3">Riwayat</h2><div class="table-responsive"><table class="table"><thead><tr><th>Jenis</th><th>Tanggal</th><th>Durasi</th><th>Status</th><th>Catatan HR</th></tr></thead><tbody>
<?php foreach($requests as $r): ?><tr><td><?=e(ucfirst($r['jenis']))?><?php if($r['berkas_lampiran']):?><div class="small"><a href="/hris-presensi/lampiran.php?f=<?=rawurlencode($r['berkas_lampiran'])?>" target="_blank">Lihat lampiran</a></div><?php endif;?></td><td><?=e(date('d M',strtotime($r['tanggal_mulai'])))?><?= $r['tanggal_selesai']!==$r['tanggal_mulai']?' – '.e(date('d M Y',strtotime($r['tanggal_selesai']))):'' ?></td><td><?= $r['jenis']==='lembur'?(int)$r['jam_lembur'].' jam':(int)$r['jumlah_hari'].' hari' ?></td><td><span class="badge text-bg-<?= $r['status']==='disetujui'?'success':($r['status']==='ditolak'?'danger':'warning') ?>"><?=e(ucfirst($r['status']))?></span></td><td class="small text-secondary"><?=e($r['catatan_admin']?:'—')?></td></tr><?php endforeach; ?>
<?php if(!$requests): ?><tr><td colspan="5" class="text-center text-secondary py-4">Belum ada pengajuan.</td></tr><?php endif; ?></tbody></table></div></section></div></div>
<script>document.getElementById('request-kind').addEventListener('change',e=>{const ot=e.target.value==='lembur',sick=e.target.value==='sakit';document.getElementById('overtime-wrap').classList.toggle('d-none',!ot);document.getElementById('end-date-wrap').classList.toggle('d-none',ot);const end=document.querySelector('[name="tanggal_selesai"]'),attachment=document.getElementById('attachment-input');end.required=!ot;attachment.required=sick;document.getElementById('attachment-label').textContent=sick?'Surat dokter wajib (PDF/JPG/PNG, maks 5 MB)':'Lampiran (opsional, PDF/JPG/PNG, maks 5 MB)';if(ot)end.value=document.querySelector('[name="tanggal_mulai"]').value;});document.querySelector('[name="tanggal_mulai"]').addEventListener('change',()=>{if(document.getElementById('request-kind').value==='lembur')document.querySelector('[name="tanggal_selesai"]').value=document.querySelector('[name="tanggal_mulai"]').value});</script>
<?php page_footer(); ?>
