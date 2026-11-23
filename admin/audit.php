<?php
require dirname(__DIR__).'/config/bootstrap.php';require_once dirname(__DIR__).'/includes/layout.php';require_role('admin');
$page=max(1,(int)($_GET['halaman']??1));$perPage=50;$offset=($page-1)*$perPage;
$total=(int)$pdo->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();
$stmt=$pdo->prepare('SELECT a.*,u.nama AS actor_name FROM audit_log a LEFT JOIN users u ON u.id=a.actor_user_id ORDER BY a.created_at DESC,a.id DESC LIMIT ? OFFSET ?');$stmt->bindValue(1,$perPage,PDO::PARAM_INT);$stmt->bindValue(2,$offset,PDO::PARAM_INT);$stmt->execute();$rows=$stmt->fetchAll();$pages=max(1,(int)ceil($total/$perPage));
page_header('Aktivitas sistem'); ?>
<div class="mb-4"><div class="eyebrow">Keamanan & administrasi</div><h1 class="page-title">Aktivitas sistem</h1><p class="page-subtitle">Jejak perubahan penting, persetujuan, presensi, dan payroll. Data pribadi sensitif tidak dicatat.</p></div>
<section class="panel"><div class="table-responsive"><table class="table"><caption class="visually-hidden">Log aktivitas sistem, halaman <?=$page?> dari <?=$pages?></caption><thead><tr><th scope="col">Waktu</th><th scope="col">Pengguna</th><th scope="col">Tindakan</th><th scope="col">Objek</th><th scope="col">Detail</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td class="text-nowrap"><?=e(date('d-m-Y H:i',strtotime($r['created_at'])))?></td><td><?=e($r['actor_name']??'Akun dihapus')?></td><td><code><?=e($r['action'])?></code></td><td><?=e($r['entity_type'])?><?=$r['entity_id']?' #'.(int)$r['entity_id']:''?></td><td class="small text-secondary"><?=e($r['details']?:'—')?></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="5" class="text-center text-secondary py-4">Belum ada aktivitas yang dicatat.</td></tr><?php endif;?></tbody></table></div>
<?php if($pages>1):?><nav aria-label="Halaman log aktivitas" class="d-flex justify-content-between align-items-center"><span class="small text-secondary">Halaman <?=$page?> dari <?=$pages?></span><div class="d-flex gap-2"><?php if($page>1):?><a class="btn btn-outline-secondary btn-sm" href="?halaman=<?=$page-1?>">Sebelumnya</a><?php endif;?><?php if($page<$pages):?><a class="btn btn-outline-secondary btn-sm" href="?halaman=<?=$page+1?>">Berikutnya</a><?php endif;?></div></nav><?php endif;?></section>
<?php page_footer(); ?>
