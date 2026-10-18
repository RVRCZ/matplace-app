<?php

namespace App\Console\Commands;

use App\Domain\Account\UnverifiedAccounts;
use Illuminate\Console\Command;

/**
 * Accounts nobody confirmed and nobody used, older than --days (App\Domain\Account\UnverifiedAccounts): listed always,
 * deleted only with --force. --dry-run says so explicitly and wins over --force.
 */
class PruneUnverifiedUsers extends Command
{
    protected $signature = 'users:prune-unverified {--days=7 : only accounts older than this} {--dry-run : list, delete nothing} {--force : delete what is listed}';

    protected $description = 'List (and with --force delete) unconfirmed accounts without orders, credit, models or Google/Facebook sign-in';

    public function handle(UnverifiedAccounts $accounts): int
    {
        $days = max(1, (int) $this->option('days'));
        $found = $accounts->query($days)->orderBy('id')->get(['id', 'name', 'email', 'created_at']);
        $this->table(['id', 'name', 'e-mail', 'registered'], $found->map(fn ($u) => [$u->id, $u->name, $u->email, $u->created_at->format('Y-m-d H:i')])->all());
        $this->line("{$found->count()} unconfirmed accounts older than {$days} days without orders, credit, models or linked sign-ins.");

        if ($this->option('dry-run') || ! $this->option('force')) {
            $this->info('Nothing deleted'.($this->option('dry-run') ? ' (--dry-run).' : '; add --force to delete them.'));

            return self::SUCCESS;
        }
        $gone = $accounts->prune($days);
        $this->info("Deleted {$gone} accounts.");

        return self::SUCCESS;
    }
}
