<?php

namespace App\Http\Controllers;

use App\Services\AdminTelegramWebhook;
use App\Services\TelegramAlerts;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AdminTelegramWebhookController
{
    public function __invoke(Request $request, AdminTelegramWebhook $webhook, TelegramAlerts $alerts): Response
    {
        abort_unless($webhook->accepts($request->header('X-Telegram-Bot-Api-Secret-Token')), 403);

        $alerts->acceptUpdate($request->json()->all());

        return response()->noContent();
    }
}
