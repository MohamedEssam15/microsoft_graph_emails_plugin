<?php

namespace GraphMail\LaravelGraphMail\Transport;

use GraphMail\LaravelGraphMail\Exceptions\GraphMailException;
use GraphMail\LaravelGraphMail\Services\MicrosoftGraphTokenService;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class GraphTransport extends AbstractTransport
{
    public function __construct(
        private readonly MicrosoftGraphTokenService $tokenService,
        private readonly ?string $defaultSender,
        private readonly bool $saveToSentItems = true,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $sender = $this->resolveSender($email);
        $payload = $this->buildPayload($email);
        $token = $this->tokenService->getAccessToken();

        $response = Http::withToken($token)
            ->post("https://graph.microsoft.com/v1.0/users/{$sender}/sendMail", $payload);

        if ($response->failed()) {
            throw GraphMailException::sendFailed($response->body(), $response->status());
        }
    }

    private function resolveSender(Email $email): string
    {
        $from = $email->getFrom();

        if (!empty($from)) {
            return $from[0]->getAddress();
        }

        $sender = $this->defaultSender;

        if (empty($sender)) {
            throw GraphMailException::missingSender();
        }

        return $sender;
    }

    private function buildPayload(Email $email): array
    {
        $message = [
            'subject' => $email->getSubject() ?? '',
            'body' => [
                'contentType' => $email->getHtmlBody() ? 'HTML' : 'Text',
                'content' => $email->getHtmlBody() ?? $email->getTextBody() ?? '',
            ],
            'toRecipients' => $this->mapAddresses($email->getTo()),
        ];

        if ($cc = $this->mapAddresses($email->getCc())) {
            $message['ccRecipients'] = $cc;
        }

        if ($bcc = $this->mapAddresses($email->getBcc())) {
            $message['bccRecipients'] = $bcc;
        }

        if ($replyTo = $this->mapAddresses($email->getReplyTo())) {
            $message['replyTo'] = $replyTo;
        }

        if ($attachments = $this->buildAttachments($email)) {
            $message['attachments'] = $attachments;
        }

        $payload = ['message' => $message];

        // Per the Graph API docs, only specify saveToSentItems when false;
        // true is the default. We still allow forcing false via config.
        if (!$this->saveToSentItems) {
            $payload['saveToSentItems'] = false;
        }

        return $payload;
    }

    /**
     * @param Address[] $addresses
     */
    private function mapAddresses(array $addresses): array
    {
        return array_map(
            fn (Address $address) => ['emailAddress' => ['address' => $address->getAddress()]],
            $addresses
        );
    }

    private function buildAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment';
            $contentType = $attachment->getMediaType() . '/' . $attachment->getMediaSubtype();

            $attachments[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $filename,
                'contentType' => $contentType,
                'contentBytes' => base64_encode($attachment->getBody()),
            ];
        }

        return $attachments;
    }

    public function __toString(): string
    {
        return 'graph';
    }
}
