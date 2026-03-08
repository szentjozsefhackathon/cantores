<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserUnblockCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cantores:user-unblock {email : The email of the user to unblock}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unblock a user by email, allowing them to log in again.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email {$email} not found.");

            return;
        }

        if (! $user->blocked) {
            $this->info("User {$email} is not blocked.");

            return;
        }

        $user->update([
            'blocked' => false,
            'blocked_at' => null,
        ]);

        $this->info("User {$email} has been unblocked successfully.");
    }
}
