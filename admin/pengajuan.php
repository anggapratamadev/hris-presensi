<?php
require dirname(__DIR__).'/config/bootstrap.php'; require_once dirname(__DIR__).'/includes/layout.php'; $admin=require_role('admin');
if($_SERVER['REQUEST_METHOD']==='POST'){
 require_csrf();$id=(int)($_POST['id']??0);$decision=$_POST['status']??'';$note=trim((string)($_POST['catatan_admin']??''));
 if($id<1||!in_array($decision,['disetujui','ditolak'],true)){flash('danger','Data verifikasi tidak valid.');redirect('/hris-presensi/admin/pengajuan.php');}
 try{$pdo->beginTransaction();$s=$pdo->prepare("SELECT * FROM pengajuan WHERE id=? AND status='pending' FOR UPDATE");$s->execute([$id]);$req=$s->fetch();if(!$req)throw new DomainException('Pengajuan sudah diproses atau tidak ditemukan.');
   if($decision==='disetujui'&&$req['jenis']!=='lembur'){
     $overlap=$pdo->prepare("SELECT id FROM pengajuan WHERE user_id=? AND id<>? AND status='disetujui' AND jenis<>'lembur' AND tanggal_mulai<=? AND tanggal_selesai>=? LIMIT 1 FOR UPDATE");$overlap->execute([$req['user_id'],$id,$req['tanggal_selesai'],$req['tanggal_mulai']]);
     if($overlap->fetch())throw new DomainException('Tidak dapat menyetujui: periode bertumpang tindih dengan pengajuan lain yang sudah disetujui.');
     for($d=new DateTimeImmutable($req['tanggal_mulai']);$d<=new DateTimeImmutable($req['tanggal_selesai']);$d=$d->modify('+1 day')){
       if((int)$d->format('N')>5)continue;$date=$d->format('Y-m-d');$check=$pdo->prepare('SELECT jam_masuk,status FROM presensi WHERE user_id=? AND tanggal=? FOR UPDATE');$check->execute([$req['user_id'],$date]);$existing=$check->fetch();
       if($existing&&($existing['jam_masuk']||$existing['status']!=='hadir'))throw new DomainException('Tidak dapat menyetujui: sudah ada catatan presensi atau izin pada '.$date.'.');
       if($existing){$up=$pdo->prepare('UPDATE presensi SET status=?,catatan=? WHERE user_id=? AND tanggal=?');$up->execute([$req['jenis'],$note?:'Pengajuan disetujui',$req['user_id'],$date]);}
       else{$ins=$pdo->prepare('INSERT INTO presensi (user_id,tanggal,status,catatan) VALUES (?,?,?,?)');$ins->execute([$req['user_id'],$date,$req['jenis'],$note?:'Pengajuan disetujui']);}
     }
   }
   $up=$pdo->prepare('UPDATE pengajuan SET status=?,catatan_admin=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?');$up->execute([$decision,$note?:null,$admin['id'],$id]);audit_event($pdo,(int)$admin['id'],'request.'.$decision,'pengajuan',$id,['jenis'=>$req['jenis']]);$pdo->commit();flash('success','Pengajuan berhasil '.($decision==='disetujui'?'disetujui':'ditolak').'.');
 }catch(DomainException $e){if($pdo->inTransaction())$pdo->rollBack();flash('danger',$e->getMessage());}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('danger','Verifikasi gagal diproses.');}
 redirect('/hris-presensi/admin/pengajuan.php');
}
$filter=$_GET['status']??'pending';$valid=['pending','disetujui','ditolak','semua'];if(!in_array($filter,$valid,true))$filter='pending';
$sql='SELECT a.*,u.nama,u.nip FROM pengajuan a JOIN users u ON u.id=a.user_id';$args=[];if($filter!=='semua'){$sql.=' WHERE a.status=?';$args[]=$filter;}$sql.=' ORDER BY a.created_at DESC LIMIT 100';$s=$pdo->prepare($sql);$s->execute($args);$rows=$s->fetchAll();
page_header('Verifikasi pengajuan'); ?>
<div class="mb-4"><div class="eyebrow">Administrasi HR</div><h1 class="page-title">Verifikasi pengajuan</h1></div>
<div class="d-flex gap-2 mb-3 flex-wrap"><?php foreach($valid as $v): ?><a class="btn btn-sm <?= $filter===$v?'btn-primary':'btn-outline-secondary' ?>" href="?status=<?=e($v)?>"><?=e(ucfirst($v))?></a><?php endforeach; ?></div>
<section class="panel"><div class="table-responsive"><table class="table"><thead><tr><th>Karyawan</th><th>Jenis</th><th>Periode</th><th>Durasi</th><th>Keterangan</th><th>Verifikasi</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td class="fw-semibold"><?=e($r['nama'])?><div class="small text-secondary"><?=e($r['nip'])?></div></td><td><?=e(ucfirst($r['jenis']))?><?php if($r['berkas_lampiran']):?><div class="small"><a href="/hris-presensi/lampiran.php?f=<?=rawurlencode($r['berkas_lampiran'])?>" target="_blank">Lampiran</a></div><?php endif;?></td><td><?=e($r['tanggal_mulai'])?><?= $r['tanggal_selesai']!==$r['tanggal_mulai']?' – '.e($r['tanggal_selesai']):'' ?></td><td><?=$r['jenis']==='lembur'?(int)$r['jam_lembur'].' jam':(int)$r['jumlah_hari'].' hari'?></td><td><?=e($r['keterangan'])?></td><td><?php if($r['status']==='pending'): ?><form method="post" class="d-grid gap-1"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><input class="form-control form-control-sm" name="catatan_admin" placeholder="Catatan (opsional)"><div class="d-flex gap-1"><button name="status" value="disetujui" class="btn btn-success btn-sm">Setujui</button><button name="status" value="ditolak" class="btn btn-outline-danger btn-sm">Tolak</button></div></form><?php else: ?><span class="badge text-bg-<?= $r['status']==='disetujui'?'success':'danger' ?>"><?=e(ucfirst($r['status']))?></span><?php endif; ?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="6" class="text-center text-secondary py-4">Tidak ada pengajuan pada filter ini.</td></tr><?php endif; ?></tbody></table></div></section>
<?php page_footer(); ?>
