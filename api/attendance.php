<?php
require dirname(__DIR__) . '/config/bootstrap.php';
$user = require_login();
if ($user['role'] !== 'karyawan') json_response(['ok'=>false,'message'=>'Hanya akun karyawan yang dapat melakukan presensi.'],403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'message'=>'Metode tidak diizinkan.'],405);
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf(is_string($csrf) ? $csrf : null)) json_response(['ok'=>false,'message'=>'Sesi formulir kedaluwarsa. Muat ulang halaman dan coba lagi.'],419);
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) json_response(['ok'=>false,'message'=>'Data permintaan tidak valid.'],400);
$action = $input['action'] ?? '';
if (!in_array($action,['masuk','pulang'],true)) json_response(['ok'=>false,'message'=>'Jenis presensi tidak valid.'],422);
$number = static fn($value) => (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) ? (float)$value : false;
$lat = $number($input['latitude'] ?? null);
$lng = $number($input['longitude'] ?? null);
$accuracy = $number($input['accuracy'] ?? null);
if ($lat===false || $lng===false || !is_finite((float)$lat) || !is_finite((float)$lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) json_response(['ok'=>false,'message'=>'Koordinat GPS tidak valid.'],422);
if ($accuracy===false || !is_finite((float)$accuracy) || $accuracy < 0 || $accuracy > 100) json_response(['ok'=>false,'message'=>'Akurasi GPS tidak valid atau terlalu rendah. Coba di area terbuka lalu perbarui lokasi.'],422);
$office = setting($pdo);
if (!$office) json_response(['ok'=>false,'message'=>'Pengaturan kantor belum tersedia.'],500);
$earth=6371000;
$lat1=deg2rad((float)$office['latitude']); $lat2=deg2rad((float)$lat);
$dlat=$lat2-$lat1; $dlng=deg2rad((float)$lng-(float)$office['longitude']);
$a=sin($dlat/2)**2+cos($lat1)*cos($lat2)*sin($dlng/2)**2;
$distance=(int)round($earth*2*atan2(sqrt(max(0,$a)),sqrt(max(0,1-$a))));
if ($distance>(int)$office['radius_meter']) json_response(['ok'=>false,'message'=>"Anda berada {$distance} meter dari kantor, di luar radius presensi {$office['radius_meter']} meter.",'distance'=>$distance],422);
$dataUrl=$input['selfie'] ?? '';
if (!is_string($dataUrl) || strlen($dataUrl)>4*1024*1024 || !preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#',$dataUrl,$m)) json_response(['ok'=>false,'message'=>'Foto selfie JPEG wajib disertakan dan tidak boleh lebih dari 3 MB.'],422);
$image=base64_decode($m[1],true);
$imageInfo=$image===false?false:@getimagesizefromstring($image);
if ($image===false || strlen($image)>3*1024*1024 || $imageInfo===false || ($imageInfo['mime']??'')!=='image/jpeg') json_response(['ok'=>false,'message'=>'Foto tidak valid atau melebihi 3 MB.'],422);
$folder=dirname(__DIR__).'/storage/attendance';
if (!is_dir($folder) && !mkdir($folder,0750,true)) json_response(['ok'=>false,'message'=>'Folder penyimpanan foto tidak dapat dibuat.'],500);
$photoName=$user['id'].'_'.date('Ymd_His').'_'.bin2hex(random_bytes(6)).'.jpg';
$photoPath=$folder.'/'.$photoName;
if (file_put_contents($photoPath,$image,LOCK_EX)===false) json_response(['ok'=>false,'message'=>'Foto gagal disimpan.'],500);
$today=date('Y-m-d'); $now=date('H:i:s');
try {
    $pdo->beginTransaction();
    $stmt=$pdo->prepare('SELECT * FROM presensi WHERE user_id=? AND tanggal=? FOR UPDATE');
    $stmt->execute([$user['id'],$today]); $row=$stmt->fetch();
    if ($action==='masuk') {
        if ($row) throw new DomainException('Presensi hari ini sudah tercatat atau Anda memiliki izin/cuti yang disetujui.');
        $scheduled=strtotime($today.' '.$office['jam_masuk']);
        $lateMinutes=max(0,(int)ceil((time()-$scheduled-(int)$office['toleransi_menit']*60)/60));
        $status=$lateMinutes>0?'terlambat':'hadir';
        $stmt=$pdo->prepare('INSERT INTO presensi (user_id,tanggal,jam_masuk,foto_masuk,lat_masuk,long_masuk,jarak_masuk_meter,menit_terlambat,status) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$user['id'],$today,$now,$photoName,$lat,$lng,$distance,$lateMinutes,$status]);
        audit_event($pdo,(int)$user['id'],'attendance.masuk','presensi',(int)$pdo->lastInsertId(),['tanggal'=>$today,'jarak_meter'=>$distance]);
        $message='Presensi masuk berhasil dicatat pada '.$now.'.';
    } else {
        if (!$row || !$row['jam_masuk']) throw new DomainException('Anda belum melakukan presensi masuk hari ini.');
        if ($row['jam_pulang']) throw new DomainException('Presensi pulang hari ini sudah tercatat.');
        $scheduledEnd=strtotime($today.' '.$office['jam_pulang']);
        $overtime=round(max(0,time()-$scheduledEnd)/3600,2);
        $stmt=$pdo->prepare('UPDATE presensi SET jam_pulang=?,foto_pulang=?,lat_pulang=?,long_pulang=?,jarak_pulang_meter=?,jam_lembur=? WHERE id=?');
        $stmt->execute([$now,$photoName,$lat,$lng,$distance,$overtime,$row['id']]);
        audit_event($pdo,(int)$user['id'],'attendance.pulang','presensi',(int)$row['id'],['tanggal'=>$today,'jarak_meter'=>$distance]);
        $message='Presensi pulang berhasil dicatat pada '.$now.'.';
    }
    $pdo->commit();
    json_response(['ok'=>true,'message'=>$message,'distance'=>$distance]);
} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    @unlink($photoPath);
    json_response(['ok'=>false,'message'=>$e->getMessage()],409);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    @unlink($photoPath);
    json_response(['ok'=>false,'message'=>'Presensi gagal disimpan. Silakan coba lagi.'],500);
}
