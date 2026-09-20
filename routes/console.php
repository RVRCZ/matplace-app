<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('matplace:expire-inquiries')->hourly();
Schedule::command('queue:prune-failed --hours=168')->daily();
