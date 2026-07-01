<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys.auth' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        $user->pushSubscriptions()
            ->where('endpoint', '!=', $validated['endpoint'])
            ->delete();

        $user->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['contentEncoding'] ?? null,
        );

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $validated = $request->validate([
            'endpoint' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        if (filled($validated['endpoint'] ?? null)) {
            $user->deletePushSubscription($validated['endpoint']);
        } else {
            $user->pushSubscriptions()->delete();
        }

        return response()->noContent();
    }
}
