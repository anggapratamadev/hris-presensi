<?php
require __DIR__.'/config/bootstrap.php';$user=require_login();$name=basename((string)($_GET['f']??''));
if(!preg_match('/^\d+_\d{8}_\d{6}_[a-f0-9]{12}\.jpg$/',$name)){http_response_code(404);exit;}
$s=$pdo->prepare("SELECT user_id FROM presensi WHERE foto_masuk=? OR foto_pulang=? LIMIT 1");$s->execute([$name,$name]);$owner=$s->fetchColumn();
if(!$owner||($user['role']!=='admin'&&(int)$owner!==(int)$user['id'])){http_response_code(404);exit;}
$path=__DIR__.'/storage/attendance/'.$name;if(!is_file($path)){http_response_code(404);exit;}
header('Content-Type: image/jpeg');header('Content-Length: '.filesize($path));header('Cache-Control: private, max-age=300');readfile($path);
