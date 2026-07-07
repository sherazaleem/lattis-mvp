<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Runs every minute; DispatchDueRssFeedsCommand itself only dispatches a
// source whose fetch_frequency_minutes interval has actually elapsed
// (RssSource::isDueForFetch()) — this tick rate is just the polling
// granularity, not the fetch frequency.
Schedule::command('atlas:dispatch-due-feeds')->everyMinute()->withoutOverlapping();
