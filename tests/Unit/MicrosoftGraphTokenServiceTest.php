<?php

use GraphMail\LaravelGraphMail\Exceptions\GraphMailException;
use GraphMail\LaravelGraphMail\Services\MicrosoftGraphTokenService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('acquires and caches an access token', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token-123'], 200),
    ]);

    $service = app(MicrosoftGraphTokenService::class);

    $token = $service->getAccessToken();

    expect($token)->toBe('fake-token-123');

    Http::assertSentCount(1);

    // Second call should hit the cache, not fire another request.
    $service->getAccessToken();
    Http::assertSentCount(1);
});

it('throws a GraphMailException when the token request fails', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'error' => 'invalid_client',
            'error_description' => 'Invalid client secret provided.',
        ], 401),
    ]);

    $service = app(MicrosoftGraphTokenService::class);

    expect(fn () => $service->getAccessToken())
        ->toThrow(GraphMailException::class);
});

it('forgets the cached token', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token-123'], 200),
    ]);

    $service = app(MicrosoftGraphTokenService::class);
    $service->getAccessToken();

    $service->forgetToken();

    expect(Cache::has('ms_graph_token_test'))->toBeFalse();
});
