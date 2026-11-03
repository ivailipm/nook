<?php

namespace App\Console\Commands;

use App\Actions\RollSubscriptions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('nook:roll-subscriptions')]
#[Description('Move passed next payment dates on by their billing cycle and turn unanswered trials paid')]
class RollSubscriptionsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RollSubscriptions $rollSubscriptions): int
    {
        ['converted' => $converted, 'moved' => $moved] = $rollSubscriptions->handle();

        $this->components->info(
            "Turned {$converted} ".Str::plural('trial', $converted)." paid and moved {$moved} ".Str::plural('subscription', $moved).' on.'
        );

        return self::SUCCESS;
    }
}
