<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:prune-expired')->everyMinute()->withoutOverlapping();
// ETL inkremental ke data warehouse; dijalankan tiap jam agar antrean aktif tercatat sebelum dibersihkan (24 jam).
Schedule::command('dw:etl')->hourly()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
