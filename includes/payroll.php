<?php
declare(strict_types=1);

function weekdays_in_period(string $start,string $end,array $holidays=[]): int
{
    $count=0;
    for($date=new DateTimeImmutable($start);$date<=new DateTimeImmutable($end);$date=$date->modify('+1 day')){
        $key=$date->format('Y-m-d');
        if((int)$date->format('N')<6&&!isset($holidays[$key]))$count++;
    }
    return $count;
}

function calculate_payroll_components(array $employee,int $present,int $leave,int $lateMinutes,float $overtimeHours,int $employeeWorkdays,int $monthWorkdays): array
{
    $base=$monthWorkdays>0?(float)$employee['gaji_pokok']*$employeeWorkdays/$monthWorkdays:0.0;
    $meal=$present*(float)$employee['tunjangan_makan'];
    $transport=$present*(float)$employee['tunjangan_transport'];
    $overtime=$overtimeHours*(float)$employee['tarif_lembur_per_jam'];
    $lateHours=(int)ceil(max(0,$lateMinutes)/60);
    $lateDeduction=$lateHours*(float)$employee['potongan_terlambat_per_jam'];
    $alpha=max(0,$employeeWorkdays-$present-$leave);
    $alphaDeduction=$employeeWorkdays>0?($base/$employeeWorkdays)*$alpha:0.0;
    $bpjs=$monthWorkdays>0?(float)$employee['potongan_bpjs_per_bulan']*$employeeWorkdays/$monthWorkdays:0.0;
    $total=max(0,$base+$meal+$transport+$overtime-$lateDeduction-$alphaDeduction-$bpjs);
    return ['base'=>$base,'meal'=>$meal,'transport'=>$transport,'overtime_pay'=>$overtime,'late_hours'=>$lateHours,'late_deduction'=>$lateDeduction,'alpha_days'=>$alpha,'alpha_deduction'=>$alphaDeduction,'bpjs_deduction'=>$bpjs,'total'=>round($total,2)];
}
