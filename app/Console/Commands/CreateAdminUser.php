<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'user:create-admin 
                            {--email= : Admin email address}
                            {--password= : Admin password (not recommended for production)}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Create a new admin user securely. Prompts for email and password if not provided via options.';

    public function handle(): int
    {
        $this->info('Creating Admin User');
        $this->newLine();

        // Check if admin already exists
        $existingAdmin = User::where('is_admin', true)->first();
        if ($existingAdmin && !$this->option('force')) {
            $this->warn("An admin user already exists: {$existingAdmin->email}");
            
            if (!$this->confirm('Do you want to create another admin user?', false)) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        // Get email
        $email = $this->option('email');
        if (!$email) {
            $email = $this->ask('Email address');
        }

        // Validate email
        $emailValidator = Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($emailValidator->fails()) {
            $this->error('Invalid email address: ' . $emailValidator->errors()->first('email'));
            return Command::FAILURE;
        }

        // Check if user with this email already exists
        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            if ($existingUser->is_admin) {
                $this->error("User with email {$email} is already an admin.");
                return Command::FAILURE;
            }

            if (!$this->option('force') && !$this->confirm("User with email {$email} already exists. Make them an admin?", false)) {
                $this->info('Operation cancelled.');
                return Command::FAILURE;
            }

            $existingUser->update(['is_admin' => true]);
            $this->info("✓ User {$email} has been granted admin privileges.");
            return Command::SUCCESS;
        }

        // Get password
        $password = $this->option('password');
        if (!$password) {
            $password = $this->secret('Password');
        }

        // Get password confirmation
        $passwordConfirmation = null;
        if (!$this->option('password')) {
            $passwordConfirmation = $this->secret('Confirm password');
        } else {
            $passwordConfirmation = $password; // Auto-confirm if provided via option
        }

        // Validate password
        if ($password !== $passwordConfirmation) {
            $this->error('Passwords do not match.');
            return Command::FAILURE;
        }

        $passwordValidator = Validator::make(['password' => $password], [
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($passwordValidator->fails()) {
            $this->error('Password validation failed: ' . $passwordValidator->errors()->first('password'));
            $this->warn('Password must be at least 8 characters long.');
            return Command::FAILURE;
        }

        // Show summary
        $this->newLine();
        $this->info('Summary:');
        $this->line("  Email: {$email}");
        $this->line("  Password: " . str_repeat('*', strlen($password)));
        $this->line("  Admin: Yes");
        $this->newLine();

        // Confirm creation
        if (!$this->option('force') && !$this->confirm('Create this admin user?', true)) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        // Create admin user
        try {
            $user = User::create([
                'name' => $this->extractNameFromEmail($email),
                'email' => $email,
                'password' => Hash::make($password),
                'is_admin' => true,
                'email_verified_at' => now(), // Auto-verify for admin users
            ]);

            $this->newLine();
            $this->info('✓ Admin user created successfully!');
            $this->line("  ID: {$user->id}");
            $this->line("  Email: {$user->email}");
            $this->line("  Name: {$user->name}");
            $this->line("  Admin: " . ($user->is_admin ? 'Yes' : 'No'));
            $this->newLine();
            $this->comment('You can now log in with these credentials.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to create admin user: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Extract a name from email address (e.g., admin@example.com -> Admin)
     */
    private function extractNameFromEmail(string $email): string
    {
        $name = explode('@', $email)[0];
        return ucfirst($name);
    }
}

