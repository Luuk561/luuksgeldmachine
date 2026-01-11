<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new admin user interactively';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔐 Creating new admin user...');
        $this->newLine();

        // Ask for name
        $name = $this->ask('Name');

        // Ask for email with validation
        do {
            $email = $this->ask('Email');

            $validator = Validator::make(['email' => $email], [
                'email' => 'required|email|unique:users,email'
            ]);

            if ($validator->fails()) {
                $this->error($validator->errors()->first('email'));
                $email = null;
            }
        } while (!$email);

        // Ask for password with confirmation
        do {
            $password = $this->secret('Password (min. 8 characters)');
            $passwordConfirmation = $this->secret('Confirm password');

            if ($password !== $passwordConfirmation) {
                $this->error('Passwords do not match. Please try again.');
                $password = null;
            } elseif (strlen($password) < 8) {
                $this->error('Password must be at least 8 characters.');
                $password = null;
            }
        } while (!$password);

        // Create the user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);

        $this->newLine();
        $this->info("✅ Admin user created successfully!");
        $this->table(
            ['Field', 'Value'],
            [
                ['Name', $user->name],
                ['Email', $user->email],
                ['ID', $user->id],
            ]
        );

        return Command::SUCCESS;
    }
}
