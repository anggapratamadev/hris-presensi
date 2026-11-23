<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/payroll.php';

$checks=0;
$assert=static function(bool $condition,string $message)use(&$checks):void{
    $checks++;
    if(!$condition)throw new RuntimeException('GAGAL: '.$message);
};

$holidaySet=['2024-01-01'=>true];
$assert(weekdays_in_period('2024-01-01','2024-01-07')===5,'hitung hari kerja Senin sampai Jumat');
$assert(weekdays_in_period('2024-01-01','2024-01-07',$holidaySet)===4,'hari libur mengurangi hari kerja');

$employee=['gaji_pokok'=>5000000,'tunjangan_makan'=>20000,'tunjangan_transport'=>10000,'tarif_lembur_per_jam'=>50000,'potongan_terlambat_per_jam'=>25000,'potongan_bpjs_per_bulan'=>100000];
$result=calculate_payroll_components($employee,10,1,61,2,11,22);
$assert($result['base']===2500000.0,'gaji pokok diprorata untuk masa kerja setengah bulan');
$assert($result['late_hours']===2,'menit terlambat dibulatkan ke jam');
$assert($result['alpha_days']===0,'hari sebelum mulai kerja tidak menjadi alpha');
$assert($result['bpjs_deduction']===50000.0,'potongan BPJS diprorata untuk masa kerja setengah bulan');
$assert($result['total']===2800000.0,'total gaji menghitung tunjangan dan semua potongan');

$withAlpha=calculate_payroll_components($employee,8,1,0,0,11,22);
$assert($withAlpha['alpha_days']===2,'alpha dihitung setelah hadir dan cuti disetujui');
$assert($withAlpha['alpha_deduction']>0,'alpha menghasilkan potongan');

fwrite(STDOUT,"Lulus: {$checks} pemeriksaan payroll.\n");
