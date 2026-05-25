<?php
namespace Core\Mail;

/**
 * RFC 5322 mailbox (email + optional display name).
 */
final class MailAddress
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $name = null,
    ) {
    }

    /**
     * Parse "Name <user@example.com>" or bare "user@example.com".
     */
    public static function parse(string $value): self
    {
        $value = trim($value);
        if (preg_match('/^(.+?)\s*<([^>]+)>$/', $value, $matches) === 1) {
            $name = trim($matches[1], " \t\"'");
            return new self(trim($matches[2]), $name !== '' ? $name : null);
        }
        return new self($value);
    }

    /**
     * Format for MIME / mail headers.
     */
    public function format(): string
    {
        if ($this->name === null || $this->name === '') {
            return $this->email;
        }
        return sprintf('"%s" <%s>', $this->encodeDisplayName($this->name), $this->email);
    }

    private function encodeDisplayName(string $name): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $name);
    }
}
