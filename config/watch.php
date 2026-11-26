<?php

/*
 * The watch (docs/AU.md): what in the shared mailbox needs no person, and when the server's state is a problem.
 * Both commands, matplace:watch-inbox and matplace:health, mail the owner at farm setting `alert_email`.
 */
return [
    // ── the mailbox ──────────────────────────────────────────────────────────
    // a sender containing one of these never needs a person: bounces, authentication reports, robots, marketing
    'report_senders' => ['mailer-daemon@', 'postmaster@', 'dmarc', 'abuse@', 'dmarcreport', 'no-reply@eu-2.mimecastreport'],
    'robot_senders' => ['no-reply', 'noreply', 'donotreply', 'do-not-reply', 'notifications@', 'notification@', 'newsletter', 'news@',
        'alerts@', 'follow-suggestions@', 'posts-recaps@', 'team@m.', 'mail.instagram.com', 'facebookmail.com', 'hello@adsygo', 'support@kreatyx'],
    // a subject starting with one of these is a report or our own notification, whoever sent it
    'report_subjects' => ['report domain:', 'report-id:', '[preview] report', 'delivery status notification', 'undeliverable', 'mail delivery failed',
        'nedoručitelné', 'zpráva nebyla doručena'],
    'own_subjects' => ['[farm]'],
    // a person's mail still unread after this long is mentioned once a day
    'remind_after_hours' => 24,

    // ── the server ───────────────────────────────────────────────────────────
    'disk_warn_percent' => 85,
    'disk_critical_percent' => 93,
    'failed_jobs_window_minutes' => 60,      // a job that failed this recently is a problem
    'queue_max_minutes' => 15,               // a job waiting longer: the workers are stuck
    'log_window_minutes' => 15,              // ERROR lines this recent in laravel.log are a problem
    'backup_marker' => env('WATCH_BACKUP_MARKER', ''),   // file the backup writes when it ends (empty = no backup check)
    'backup_max_hours' => 30,
    'mailbox_stale_minutes' => 30,           // the inbox watch has not finished for this long
];
