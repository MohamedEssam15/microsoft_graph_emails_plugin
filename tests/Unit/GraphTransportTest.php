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

it('always sends as the configured MS_SENDER_EMAIL, ignoring any from() on the mailable', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/v1.0/users/sender@example.com/sendMail' => Http::response('', 202),
    ]);

    Mail::raw('Hello world', function ($message) {
        $message->to('recipient@example.com')
            ->from('someone-else@example.com')
            ->subject('From Override Attempt');
    });

    // The configured sender (sender@example.com, set in TestCase) is used —
    // not the "from" address set on the message.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'users/sender@example.com/sendMail'));
});

it('throws when MS_SENDER_EMAIL is not configured', function () {
    config(['graph-mail.default_sender' => null]);

    expect(function () {
        Mail::raw('Hello world', function ($message) {
            $message->to('recipient@example.com')->subject('No Sender');
        });
    })->toThrow(GraphMailException::class, 'No sender mailbox configured');
});

it('throws when MS_SENDER_EMAIL is an invalid email like a leftover placeholder', function () {
    config(['graph-mail.default_sender' => 'user@host']);

    expect(function () {
        Mail::raw('Hello world', function ($message) {
            $message->to('recipient@example.com')->subject('Bad Sender');
        });
    })->toThrow(GraphMailException::class, 'not a valid email address');
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
