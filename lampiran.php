<?php
require __DIR__.'/config/bootstrap.php';$user=require_login();$filename=basename((string)($_GET['f']??''));
if(!preg_match('/^\d+_[a-f0-9]{24}\.(pdf|jpg|png)$/',$filename,$match)){http_response_code(404);exit;}
$s=$pdo->prepare('SELECT user_id FROM pengajuan WHERE berkas_lampiran=? LIMIT 1');$s->execute([$filename]);$owner=$s->fetchColumn();
if(!$owner||($user['role']!=='admin'&&(int)$owner!==(int)$user['id'])){http_response_code(404);exit;}
$path=__DIR__.'/storage/attachments/'.$filename;if(!is_file($path)){http_response_code(404);exit;}
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);if(!in_array($mime,['application/pdf','image/jpeg','image/png'],true)){http_response_code(404);exit;}
header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('Content-Disposition: inline; filename="lampiran.'.pathinfo($filename,PATHINFO_EXTENSION).'"');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, max-age=300');readfile($path);
