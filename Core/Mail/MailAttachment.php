<?php
namespace Core\Mail;

/**
 * Binary or inline email attachment.
 */
final class MailAttachment
{
    public const DISPOSITION_ATTACHMENT = 'attachment';
    public const DISPOSITION_INLINE = 'inline';

    public function __construct(
        public readonly string $filename,
        public readonly string $content,
        public readonly ?string $mimeType = null,
        public readonly string $disposition = self::DISPOSITION_ATTACHMENT,
        public readonly ?string $contentId = null,
    ) {
    }

    /**
     * @param string $path Absolute or project-relative readable file path.
     */
    public static function fromPath(
        string $path,
        ?string $filename = null,
        ?string $mimeType = null,
        string $disposition = self::DISPOSITION_ATTACHMENT,
        ?string $contentId = null,
    ): self {
        if (!is_readable($path)) {
            throw new \InvalidArgumentException("Attachment file not readable: {$path}");
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Failed to read attachment: {$path}");
        }
        $name = $filename ?? basename($path);
        $type = $mimeType ?? self::guessMimeType($name, $content);
        return new self($name, $content, $type, $disposition, $contentId);
    }

    public static function fromString(
        string $content,
        string $filename,
        ?string $mimeType = null,
        string $disposition = self::DISPOSITION_ATTACHMENT,
        ?string $contentId = null,
    ): self {
        $type = $mimeType ?? self::guessMimeType($filename, $content);
        return new self($filename, $content, $type, $disposition, $contentId);
    }

    public function resolvedMimeType(): string
    {
        return $this->mimeType ?? 'application/octet-stream';
    }

    private static function guessMimeType(string $filename, string $content): string
    {
        if (function_exists('mime_content_type')) {
            $tmp = tempnam(sys_get_temp_dir(), 'core_mail_');
            if ($tmp !== false) {
                file_put_contents($tmp, $content);
                $detected = mime_content_type($tmp);
                @unlink($tmp);
                if (is_string($detected) && $detected !== '') {
                    return $detected;
                }
            }
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match ($ext) {
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'txt'  => 'text/plain',
            'html', 'htm' => 'text/html',
            'csv'  => 'text/csv',
            'json' => 'application/json',
            'xml'  => 'application/xml',
            'zip'  => 'application/zip',
            default => 'application/octet-stream',
        };
    }
}
