<?php

use GraphMail\LaravelGraphMail\Exceptions\GraphMailException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
    ]);
});

it('builds a correct JSON payload and posts to the right sender mailbox', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/v1.0/users/sender@example.com/sendMail' => Http::response('', 202),
    ]);

    Mail::raw('Hello world', function ($message) {
        $message->to('recipient@example.com')->subject('Test Subject');
    });

    Http::assertSent(function ($request) {
        if (!str_contains($request->url(), 'sendMail')) {
            return true;
        }

        $data = $request->data();

        return $data['message']['subject'] === 'Test Subject'
            && $data['message']['toRecipients'][0]['emailAddress']['address'] === 'recipient@example.com'
            && $data['message']['body']['content'] === 'Hello world';
    });
});

it('uses the from address on the mailable over the default sender', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/v1.0/users/custom@example.com/sendMail' => Http::response('', 202),
    ]);

    Mail::raw('Hello world', function ($message) {
        $message->to('recipient@example.com')
            ->from('custom@example.com')
            ->subject('Custom Sender Test');
    });

    Http::assertSent(fn ($request) => str_contains($request->url(), 'users/custom@example.com/sendMail'));
});

it('throws when the Graph sendMail call fails', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/v1.0/users/*/sendMail' => Http::response([
            'error' => ['code' => 'ErrorAccessDenied', 'message' => 'Access is denied.'],
        ], 403),
    ]);

    expect(function () {
        Mail::raw('Hello world', function ($message) {
            $message->to('recipient@example.com')->subject('Should Fail');
        });
    })->toThrow(GraphMailException::class);
});
