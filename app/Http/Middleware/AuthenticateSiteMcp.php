<?php

namespace App\Http\Middleware;

use App\Models\SiteMcpToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateSiteMcp
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->getHost() !== parse_url(config('app.url'), PHP_URL_HOST)) {
            return response()->json(['error' => 'Invalid host'], 400);
        }
        if (strlen($request->getContent()) > 6 * 1024 * 1024) {
            return response()->json(['error' => 'Request too large'], 413);
        }
        $origin = $request->header('Origin');
        if ($origin && rtrim($origin, '/') !== rtrim(config('app.url'), '/')) {
            return response()->json(['error' => 'Invalid origin'], 403);
        }
        $plain = $request->bearerToken();
        $token = is_string($plain) && preg_match('/^rv_mcp_[a-f0-9]{64}$/D', $plain) ? SiteMcpToken::where('token_hash', hash('sha256', $plain))->first() : null;
        $user = $token ? User::find($token->user_id) : null;
        if (! $token || $token->revoked_at || $token->expires_at->isPast() || ! $user?->is_admin) {
            return response()->json(['error' => 'Valid MCP bearer token required'], 401)->header('WWW-Authenticate', 'Bearer realm="ridnavira-mcp"');
        }
        $request->attributes->set('site.mcp.token', $token);
        $request->setUserResolver(fn () => $user);
        Auth::setUser($user);
        $token->update(['last_used_at' => now()]);
        try {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'private, no-store');
            // Byte length is known for ordinary JSON; leave streaming responses to the transport.
            if (is_string($body = $response->getContent())) {
                $response->headers->set('Content-Length', (string) strlen($body));
            }

            return $response;
        } finally {
            Auth::forgetGuards();
        }
    }
}
