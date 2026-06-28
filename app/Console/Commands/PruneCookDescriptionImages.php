<?php

namespace App\Console\Commands;

use App\Support\CookDescriptionAttachments;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:prune-cook-description-images')]
#[Description('Delete cook description images that are no longer referenced in any cook')]
class PruneCookDescriptionImages extends Command
{
    public function handle(CookDescriptionAttachments $attachments): int
    {
        $deleted = $attachments->pruneUnreferenced();

        $this->info("Deleted {$deleted} unreferenced cook description ".str('image')->plural($deleted).'.');

        return self::SUCCESS;
    }
}
