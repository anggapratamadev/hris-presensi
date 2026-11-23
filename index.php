<?php
require __DIR__ . '/config/bootstrap.php';
if (current_user()) redirect('/hris-presensi/dashboard.php');
redirect('/hris-presensi/login.php');
