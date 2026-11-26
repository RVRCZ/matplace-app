<?php

namespace App\Console\Commands;

use App\Domain\Mail\MailboxWatch;
use App\Engines\Mail\MailboxFailed;
use Illuminate\Console\Command;

/**
 * One pass of the mailbox watch (docs/AU.md): people's unread mails the owner has not heard about go to the owner's
 * address, our own notifications and reports leave the inbox. Every five minutes from the scheduler.
 *
 *   php artisan matplace:watch-inbox
 */
class WatchInbox extends Command
{
    protected $signature = 'matplace:watch-inbox';

    protected $description = 'Sort the shared inbox and tell the owner about people\'s mails';

    public function handle(MailboxWatch $watch): int
    {
        try {
            $r = $watch->run();
        } catch (MailboxFailed $e) {
            $this->error('Mailbox: '.$e->getMessage());

            return self::FAILURE;
        }
        if (! $r['available']) {
            $this->warn('The mailbox is not set up; nothing to watch.');

            return self::SUCCESS;
        }
        $this->info(sprintf('people unread %d, new %d%s, archived %d, reminded %d', $r['unread_human'], count($r['new']), $r['alerted'] ? ' (owner mailed)' : '', $r['archived'], $r['reminded']));
        foreach ($r['new'] as $m) {
            $this->line('  '.$m['from'].' | '.$m['subject']);
        }

        return self::SUCCESS;
    }
}
