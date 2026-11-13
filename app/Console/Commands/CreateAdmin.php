<?php

namespace App\Console\Commands;

use App\Services\Admin\AdminProvisioningService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('admin:create {--name= : Admin display name} {--email= : Admin email address}')]
#[Description('Create an admin using a hidden password prompt; existing users are never overwritten')]
class CreateAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AdminProvisioningService $admins): int
    {
        if (! $this->input->isInteractive()) {
            $this->components->error('Run this command interactively to enter a hidden password.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?? $this->ask('Admin name');
        $email = $this->option('email') ?? $this->ask('Admin email');
        $password = $this->secret('Password (at least 12 characters)', false);
        $confirmation = $this->secret('Confirm password', false);

        try {
            $admin = $admins->create((string) $name, (string) $email, (string) $password, (string) $confirmation);
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $this->components->info("Admin {$admin->email} created.");

        return self::SUCCESS;
    }
}
