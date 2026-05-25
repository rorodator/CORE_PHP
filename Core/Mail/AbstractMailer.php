<?php
namespace Core\Mail;

/**
 * Shared helpers for mail drivers (defaults from [mail] config, validation, logging).
 */
abstract class AbstractMailer implements MailerInterface
{
    /** @var array<string, mixed> */
    protected array $config;

    public function __construct()
    {
        $this->config = function_exists('core') ? core()->getConfigSection('mail') : [];
    }

    /**
     * Resolve sender: message override, then [mail] from_address / from_name.
     */
    protected function resolveFrom(MailMessage $message): MailAddress
    {
        $from = $message->getFrom();
        if ($from !== null) {
            return $from;
        }
        $email = trim((string)($this->config['from_address'] ?? ''));
        if ($email === '') {
            throw new \InvalidArgumentException('Mail sender is required: set MailMessage::from() or [mail] from_address.');
        }
        $name = trim((string)($this->config['from_name'] ?? ''));
        return new MailAddress($email, $name !== '' ? $name : null);
    }

    /**
     * Resolve reply-to: message override, then [mail] reply_to / reply_to_name.
     */
    protected function resolveReplyTo(MailMessage $message): ?MailAddress
    {
        if ($message->getReplyTo() !== null) {
            return $message->getReplyTo();
        }
        $email = trim((string)($this->config['reply_to'] ?? ''));
        if ($email === '') {
            return null;
        }
        $name = trim((string)($this->config['reply_to_name'] ?? ''));
        return new MailAddress($email, $name !== '' ? $name : null);
    }

    protected function generateMessageId(): string
    {
        $host = (string)($this->config['message_id_host'] ?? 'localhost');
        return sprintf('<%s@%s>', bin2hex(random_bytes(16)), $host);
    }

    /**
     * @param MailAddress[] $addresses
     */
    protected function formatAddressList(array $addresses): string
    {
        return implode(', ', array_map(static fn (MailAddress $a) => $a->format(), $addresses));
    }

    protected function logDelivery(string $level, string $message): void
    {
        if (!function_exists('core')) {
            return;
        }
        $log = core()->log ?? null;
        if ($log === null) {
            return;
        }
        match (strtoupper($level)) {
            'ERROR' => $log->error($message),
            'DEBUG' => $log->debug($message),
            default => $log->info($message),
        };
    }
}
