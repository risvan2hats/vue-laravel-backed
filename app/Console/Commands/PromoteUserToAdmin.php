<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:make-admin {email : The email address of the existing account}')]
#[Description('Grant catalog administration access to an existing user')]
class PromoteUserToAdmin extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user exists with that email address.');

            return self::FAILURE;
        }

        if ($user->is_admin) {
            $this->info('That user already has catalog administration access.');

            return self::SUCCESS;
        }

        $user->forceFill(['is_admin' => true])->save();
        $this->info('Catalog administration access granted.');

        return self::SUCCESS;
    }
}
