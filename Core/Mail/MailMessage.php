<?php
namespace Core\Mail;

/**
 * Immutable-style fluent builder for outbound email.
 */
final class MailMessage
{
    private ?MailAddress $from = null;

    /** @var MailAddress[] */
    private array $to = [];

    /** @var MailAddress[] */
    private array $cc = [];

    /** @var MailAddress[] */
    private array $bcc = [];

    private ?MailAddress $replyTo = null;

    private string $subject = '';

    private ?string $textBody = null;

    private ?string $htmlBody = null;

    /** @var MailAttachment[] */
    private array $attachments = [];

    /** @var array<string, string> */
    private array $headers = [];

    private string $charset = 'UTF-8';

    private ?string $messageId = null;

    public static function create(): self
    {
        return new self();
    }

    public function from(string $email, ?string $name = null): self
    {
        $clone = clone $this;
        $clone->from = new MailAddress($email, $name);
        return $clone;
    }

    public function to(string $email, ?string $name = null): self
    {
        $clone = clone $this;
        $clone->to = [new MailAddress($email, $name)];
        return $clone;
    }

    /**
     * @param MailAddress|array{email:string,name?:string|null}|string ...$recipients
     */
    public function addTo(MailAddress|string|array ...$recipients): self
    {
        $clone = clone $this;
        $clone->to = array_merge($clone->to, self::normalizeRecipients($recipients));
        return $clone;
    }

    /**
     * @param MailAddress|array{email:string,name?:string|null}|string ...$recipients
     */
    public function cc(MailAddress|string|array ...$recipients): self
    {
        $clone = clone $this;
        $clone->cc = array_merge($clone->cc, self::normalizeRecipients($recipients));
        return $clone;
    }

    /**
     * @param MailAddress|array{email:string,name?:string|null}|string ...$recipients
     */
    public function bcc(MailAddress|string|array ...$recipients): self
    {
        $clone = clone $this;
        $clone->bcc = array_merge($clone->bcc, self::normalizeRecipients($recipients));
        return $clone;
    }

    public function replyTo(string $email, ?string $name = null): self
    {
        $clone = clone $this;
        $clone->replyTo = new MailAddress($email, $name);
        return $clone;
    }

    public function subject(string $subject): self
    {
        $clone = clone $this;
        $clone->subject = $subject;
        return $clone;
    }

    public function text(string $body): self
    {
        $clone = clone $this;
        $clone->textBody = $body;
        return $clone;
    }

    public function html(string $body): self
    {
        $clone = clone $this;
        $clone->htmlBody = $body;
        return $clone;
    }

    public function attach(MailAttachment $attachment): self
    {
        $clone = clone $this;
        $clone->attachments = array_merge($clone->attachments, [$attachment]);
        return $clone;
    }

    public function attachFile(
        string $path,
        ?string $filename = null,
        ?string $mimeType = null,
        string $disposition = MailAttachment::DISPOSITION_ATTACHMENT,
        ?string $contentId = null,
    ): self {
        return $this->attach(MailAttachment::fromPath($path, $filename, $mimeType, $disposition, $contentId));
    }

    public function attachString(
        string $content,
        string $filename,
        ?string $mimeType = null,
        string $disposition = MailAttachment::DISPOSITION_ATTACHMENT,
        ?string $contentId = null,
    ): self {
        return $this->attach(MailAttachment::fromString($content, $filename, $mimeType, $disposition, $contentId));
    }

    public function header(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function charset(string $charset): self
    {
        $clone = clone $this;
        $clone->charset = $charset;
        return $clone;
    }

    public function messageId(?string $messageId): self
    {
        $clone = clone $this;
        $clone->messageId = $messageId;
        return $clone;
    }

    public function getFrom(): ?MailAddress
    {
        return $this->from;
    }

    /** @return MailAddress[] */
    public function getTo(): array
    {
        return $this->to;
    }

    /** @return MailAddress[] */
    public function getCc(): array
    {
        return $this->cc;
    }

    /** @return MailAddress[] */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function getReplyTo(): ?MailAddress
    {
        return $this->replyTo;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getTextBody(): ?string
    {
        return $this->textBody;
    }

    public function getHtmlBody(): ?string
    {
        return $this->htmlBody;
    }

    /** @return MailAttachment[] */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function validate(): void
    {
        if ($this->to === [] && $this->cc === [] && $this->bcc === []) {
            throw new \InvalidArgumentException('MailMessage requires at least one recipient (to, cc, or bcc).');
        }
        if ($this->subject === '') {
            throw new \InvalidArgumentException('MailMessage subject is required.');
        }
        if ($this->textBody === null && $this->htmlBody === null) {
            throw new \InvalidArgumentException('MailMessage requires a text and/or html body.');
        }
    }

    /**
     * @param array<int, MailAddress|string|array{email:string,name?:string|null}> $recipients
     * @return MailAddress[]
     */
    private static function normalizeRecipients(array $recipients): array
    {
        $out = [];
        foreach ($recipients as $recipient) {
            if ($recipient instanceof MailAddress) {
                $out[] = $recipient;
                continue;
            }
            if (is_array($recipient)) {
                $out[] = new MailAddress($recipient['email'], $recipient['name'] ?? null);
                continue;
            }
            $out[] = MailAddress::parse((string)$recipient);
        }
        return $out;
    }
}
