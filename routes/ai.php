<?php

use App\Mcp\Servers\MaildunServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Middleware\CheckToken;

Mcp::local('maildun', MaildunServer::class);

Route::middleware('throttle:mcp-oauth')->group(function (): void {
    Route::get('/.well-known/oauth-authorization-server', static fn () => response()->json([
        'issuer' => config('mcp.authorization_server') ?? url('/'),
        'authorization_endpoint' => route('passport.authorizations.authorize'),
        'token_endpoint' => route('passport.token'),
        'registration_endpoint' => url('oauth/register'),
        'response_types_supported' => ['code'],
        'token_endpoint_auth_methods_supported' => ['none'],
        'code_challenge_methods_supported' => ['S256'],
        'scopes_supported' => ['mcp:use'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
    ]))->name('mcp.oauth.authorization-server');

    Mcp::oauthRoutes();
});

Mcp::web('/mcp/maildun', MaildunServer::class)->middleware([
    'throttle:mcp-auth',
    'auth:api',
    CheckToken::using('mcp:use'),
]);
