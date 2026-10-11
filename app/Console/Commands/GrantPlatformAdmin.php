<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * The only way in.
 *
 * Platform administration is the widest permission the system has — it reads
 * every band. Granting it from the console means there is no screen, no form
 * and no endpoint that can be tricked into handing it out: you need the
 * server.
 */
class GrantPlatformAdmin extends Command
{
    protected $signature = 'admin:grant {email} {--revoke : Take the permission away instead}';

    protected $description = 'Grant or revoke platform administration for a user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (!$user) {
            $this->error("No user with that email.");
            return self::FAILURE;
        }

        $revoking = (bool) $this->option('revoke');

        $user->forceFill(['is_platform_admin' => !$revoking])->save();

        $this->info($revoking
            ? "Revoked platform administration from {$user->name}."
            : "{$user->name} can now open /admin.");

        return self::SUCCESS;
    }
}
