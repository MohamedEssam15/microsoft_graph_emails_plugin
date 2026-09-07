<?php

namespace GraphMail\LaravelGraphMail\Exceptions;

use Exception;

class GraphMailException extends Exception
{
    public function __construct(string $message, private readonly int $httpStatus = 0)
    {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public static function tokenRequestFailed(string $body): self
    {
        return new self("Failed to acquire Microsoft Graph access token: {$body}");
    }

    public static function sendFailed(string $body, int $status): self
    {
        return new self("Microsoft Graph sendMail request failed ({$status}): {$body}", $status);
    }

    public static function missingSender(): self
    {
        return new self(
            'No sender mailbox resolved. Set "from" on the Mailable, pass it to Mail::to()->from(...), '
            . 'or configure graph-mail.default_sender in your .env as MS_SENDER.'
        );
    }
}
