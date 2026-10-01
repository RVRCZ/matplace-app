<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('matplace:expire-inquiries')->hourly();
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('matplace:prune')->dailyAt('03:30');
Schedule::command('farm:watch')->everyMinute()->withoutOverlapping();
Schedule::command('youtube:stats')->dailyAt('06:10');
Schedule::command('matplace:sitemap')->dailyAt('04:40');
// events older than 13 months are folded into a daily summary and deleted
Schedule::command('matplace:events-rollup')->monthlyOn(2, '03:50');
// who carries parcels where changes rarely; a failed download keeps last week's list
Schedule::command('matplace:packeta-carriers')->weeklyOn(1, '04:20');
