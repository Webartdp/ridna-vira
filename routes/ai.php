<?php

use App\Http\Middleware\AuthenticateSiteMcp;
use App\Mcp\Servers\SiteContentServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::middleware(['throttle:60,1', AuthenticateSiteMcp::class])->group(function () {
    Mcp::web('/mcp', SiteContentServer::class);
});
