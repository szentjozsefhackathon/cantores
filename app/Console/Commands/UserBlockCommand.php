<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserBlockCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:block {email : The email of the user to block}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Block a user by email, preventing them from logging in.';

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

        if ($user->blocked) {
            $this->info("User {$email} is already blocked.");

            return;
        }

        $user->update([
            'blocked' => true,
            'blocked_at' => now(),
        ]);

        $this->info("User {$email} has been blocked successfully.");
    }
}
