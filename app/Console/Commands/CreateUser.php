<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

#[Signature('nook:user')]
#[Description('Create the nook user')]
class CreateUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $validator = Validator::make([
            'name' => $this->ask('Name'),
            'email' => Str::lower(trim((string) $this->ask('Email'))),
            'password' => $this->secret('Password'),
            'password_confirmation' => $this->secret('Confirm password'),
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user = User::create($validator->validated());

        $this->components->info("Created the user {$user->email}.");

        return self::SUCCESS;
    }
}
