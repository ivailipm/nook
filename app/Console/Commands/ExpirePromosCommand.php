<?php

namespace App\Console\Commands;

use App\Actions\ExpirePromos;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('nook:expire-promos')]
#[Description('Unlink grocery items from expired promos and delete promos 30 days after they ended')]
class ExpirePromosCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ExpirePromos $expirePromos): int
    {
        ['unlinked' => $unlinked, 'deleted' => $deleted] = $expirePromos->handle();

        $this->components->info(
            "Unlinked {$unlinked} ".Str::plural('item', $unlinked)." from expired promos and deleted {$deleted} ".Str::plural('promo', $deleted).'.'
        );

        return self::SUCCESS;
    }
}
