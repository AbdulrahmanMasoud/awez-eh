<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Identity\Actions\CreateAdmin;
use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Exceptions\AccountNotActive;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Creates an admin, or promotes the account with that email: the only way to make the first
 * super_admin, in every environment. The password is asked for twice, hidden, and never
 * accepted as an option, so it stays out of the shell's history; nothing secret is printed.
 */
#[Signature('awez:create-admin {--name= : The admin\'s name} {--email= : The admin\'s email} {--role= : super_admin, moderator, data_entry or sales}')]
#[Description('Create an admin account, or give an existing account an admin role')]
final class CreateAdminCommand extends Command
{
    public function handle(CreateAdmin $createAdmin): int
    {
        $name = $this->optionOrAsk('name', fn (): string => text('Name', required: true));
        $email = $this->optionOrAsk('email', fn (): string => text('Email', required: true, validate: ['email' => ['email']]));
        $role = AdminRole::tryFrom($this->optionOrAsk('role', fn (): string => (string) select(
            'Role',
            array_combine(array_column(AdminRole::cases(), 'value'), array_column(AdminRole::cases(), 'value')),
            default: AdminRole::SuperAdmin->value,
        )));

        if (! $this->input->isInteractive()) {
            $this->components->error('Run this in a terminal: the password is typed, never passed as an option.');

            return self::FAILURE;
        }

        $password = password('Password (at least 12 characters)', required: true);
        $confirmation = password('Password again', required: true);

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'role' => $role?->value, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'role' => ['required'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ],
        );

        if ($validator->fails() || ! $role instanceof AdminRole) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        try {
            [$admin, $isNew] = $createAdmin->handle($name, $email, $password, $role);
        } catch (AccountNotActive) {
            $this->components->error('That account is suspended or deleted. Reactivate it in the panel first.');

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '%s %s as %s. They set up two-factor authentication when they first sign in at %s.',
            $isNew ? 'Created' : 'Promoted',
            $admin->email,
            $role->value,
            url('/admin'),
        ));

        return self::SUCCESS;
    }

    /**
     * @param  callable(): string  $ask
     */
    private function optionOrAsk(string $option, callable $ask): string
    {
        $value = $this->option($option);

        return is_string($value) && $value !== '' ? $value : $ask();
    }
}
