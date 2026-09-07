<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

it('includes attachments in the Graph payload', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/v1.0/users/sender@example.com/sendMail' => Http::response('', 202),
    ]);

    $tempFile = tempnam(sys_get_temp_dir(), 'graphmail_');
    file_put_contents($tempFile, 'file contents here');

    Mail::raw('See attached', function ($message) use ($tempFile) {
        $message->to('recipient@example.com')
            ->subject('With Attachment')
            ->attach($tempFile, ['as' => 'notes.txt', 'mime' => 'text/plain']);
    });

    Http::assertSent(function ($request) {
        if (!str_contains($request->url(), 'sendMail')) {
            return true;
        }

        $data = $request->data();
        $attachments = $data['message']['attachments'] ?? [];

        return count($attachments) === 1
            && $attachments[0]['name'] === 'notes.txt'
            && $attachments[0]['@odata.type'] === '#microsoft.graph.fileAttachment'
            && base64_decode($attachments[0]['contentBytes']) === 'file contents here';
    });

    @unlink($tempFile);
});
