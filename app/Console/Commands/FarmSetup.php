<?php

namespace App\Console\Commands;

use App\Models\FarmAgent;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * First steps on a server, without touching the database by hand:
 *
 *   php artisan farm:setup --admin=roman@example.com     give an existing account the admin role (opens /admin/farm)
 *   php artisan farm:setup --agent="Garage Pi"           create an agent and print its token (shown once)
 */
class FarmSetup extends Command
{
    protected $signature = 'farm:setup {--admin= : e-mail of the account that becomes a farm admin} {--agent= : name of a new farm-agent}';

    protected $description = 'Grant the admin role and/or issue a farm-agent token';

    public function handle(): int
    {
        if ($email = $this->option('admin')) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                $this->error("No account with e-mail {$email}. Register it first.");

                return self::FAILURE;
            }
            $user->setRole(User::ROLE_ADMIN, true);
            $this->info("{$email} is an admin now: ".url('/admin/farm'));
        }
        if ($name = $this->option('agent')) {
            [$agent, $token] = FarmAgent::issue($name);
            $this->info("Agent #{$agent->id} \"{$name}\" created. Token (shown only now, put it into the agent's config):");
            $this->line($token);
        }
        if (! $this->option('admin') && ! $this->option('agent')) {
            $this->line('Nothing to do. See: php artisan help farm:setup');
        }

        return self::SUCCESS;
    }
}
