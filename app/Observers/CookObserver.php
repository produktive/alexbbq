<?php

namespace App\Observers;

use App\Models\Cook;
use App\Support\CookDescriptionAttachments;

class CookObserver
{
    public function __construct(private CookDescriptionAttachments $attachments) {}

    public function updating(Cook $cook): void
    {
        if (! $cook->isDirty('description')) {
            return;
        }

        $this->attachments->deleteRemovedPaths(
            $cook->getOriginal('description'),
            $cook->description,
        );
    }

    public function deleting(Cook $cook): void
    {
        $this->attachments->deleteAllReferenced($cook->description);
    }
}
