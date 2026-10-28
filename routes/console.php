<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('matplace:expire-inquiries')->hourly();
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('matplace:prune')->dailyAt('03:30');
Schedule::command('farm:watch')->everyMinute()->withoutOverlapping();
Schedule::command('youtube:stats')->hourlyAt(20);   // numbers, and scheduled videos YouTube made public meanwhile (1 quota unit)
Schedule::command('social:videos')->everyFiveMinutes()->withoutOverlapping();   // approved videos to the Facebook page / Instagram at their slot
// "Tento týden na farmě": the week's prints in one video, waiting for the admin in /admin/youtube (never published by itself)
Schedule::command('farm:weekly-video')->weeklyOn(0, '18:00')->timezone('Europe/Prague')->withoutOverlapping()->runInBackground();
Schedule::command('matplace:sitemap')->dailyAt('04:40');
Schedule::command('searchconsole:pull --days=3')->dailyAt('05:20');   // Search Console finishes a day two to three days later (docs/AN.md §14)
// events older than 13 months (pages fetched by robots: 30 days) are folded into a daily summary and deleted
Schedule::command('matplace:events-rollup')->dailyAt('03:50');
// one machine passing for many people: out of the people (docs/O.md "Vlny"). Only visits counted as people's are judged
// (off 24 Oct 2026 for a day: the first version took unconfirmed robots in and found 2 500 "wave" visitors on a day of 19 people)
Schedule::command('matplace:stats-wave')->hourlyAt(5)->withoutOverlapping();
// the week in numbers for the owner: people, sources, funnel, what was published (docs/O.md)
Schedule::command('matplace:stats-report')->weeklyOn(1, '07:00')->timezone('Europe/Prague');
// who carries parcels where changes rarely; a failed download keeps last week's list
Schedule::command('matplace:packeta-carriers')->weeklyOn(1, '04:20');
