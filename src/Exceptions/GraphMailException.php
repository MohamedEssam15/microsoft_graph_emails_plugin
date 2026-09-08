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
            'No sender mailbox configured. Set MS_SENDER_EMAIL in your .env '
            . '(graph-mail.default_sender). The transport always sends as this '
            . 'address — it does not read the "from" address on Mailables or '
            . 'config/mail.php.'
        );
    }

    public static function invalidSender(string $sender): self
    {
        return new self(
            "MS_SENDER_EMAIL is set to \"{$sender}\", which is not a valid email address. "
            . 'Check your .env for a leftover placeholder (e.g. "user@host") or a typo, '
            . 'then run php artisan config:clear.'
        );
    }
}
