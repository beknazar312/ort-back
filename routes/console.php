<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Streak notification schedules (Asia/Bishkek timezone = UTC+6)
Schedule::command('streak:notify --scenario=evening')
    ->dailyAt('19:00')
    ->timezone('Asia/Bishkek')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('streak:notify --scenario=last_chance')
    ->dailyAt('22:30')
    ->timezone('Asia/Bishkek')
    ->withoutOverlapping()
    ->runInBackground();
