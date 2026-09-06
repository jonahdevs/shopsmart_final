<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Enforces the retention windows on LegalSettings. Nightly and off-peak: it is
| a bulk delete over two tables that grow with traffic, and nothing depends on
| it having run at any particular moment.
*/
Schedule::command('privacy:prune')->dailyAt('03:15');
