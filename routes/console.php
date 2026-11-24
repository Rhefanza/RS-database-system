<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:prune-expired')->everyMinute()->withoutOverlapping();
// ETL inkremental ke data warehouse, sekali sehari pukul 00:05 WIB.
// Antrean aktif dihapus aplikasi 24 jam setelah jam tutup jadwal, sehingga pada 00:05 seluruh antrean
// hari sebelumnya masih ada dan ikut tercatat sebelum dibersihkan.
Schedule::command('dw:etl')->dailyAt('00:05')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
