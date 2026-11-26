<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Identity\Actions\EnsurePlatformAccount;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Creates the platform's own account once, so sales can list brokered properties for owners who
 * have no account. Safe to run again.
 */
#[Signature('awez:platform-account')]
#[Description('Create the platform account that owns brokered properties of owners without an account')]
final class PlatformAccountCommand extends Command
{
    public function handle(EnsurePlatformAccount $ensurePlatformAccount): int
    {
        [$account, $created] = $ensurePlatformAccount->handle();

        $this->components->info(sprintf('%s the platform account %s (%s).', $created ? 'Created' : 'Found', $account->email, $account->id));

        return self::SUCCESS;
    }
}
