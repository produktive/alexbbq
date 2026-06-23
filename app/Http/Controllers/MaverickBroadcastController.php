<?php

namespace App\Http\Controllers;

use App\Models\Cook;
use App\Models\Reading;
use App\Services\CookBroadcastService;
use Illuminate\Http\Response;

class MaverickBroadcastController extends Controller
{
    public function __construct(private CookBroadcastService $broadcasts) {}

    public function reading(Reading $reading): Response
    {
        $this->broadcasts->readingAdded($reading);

        return response()->noContent();
    }

    public function cookStarted(Cook $cook): Response
    {
        abort_unless($cook->isActive(), 422, 'Cook is not active.');

        $this->broadcasts->cookStarted($cook);

        return response()->noContent();
    }

    public function cookEnded(Cook $cook): Response
    {
        abort_if($cook->isActive(), 422, 'Cook is still active.');

        $this->broadcasts->cookEnded($cook);

        return response()->noContent();
    }
}
