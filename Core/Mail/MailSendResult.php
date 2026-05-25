<?php
namespace Core\Mail;

/**
 * Outcome of a mail delivery attempt.
 */
final class MailSendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
    ) {
    }

    public static function ok(?string $messageId = null): self
    {
        return new self(true, $messageId);
    }

    public static function fail(string $error): self
    {
        return new self(false, null, $error);
    }
}
