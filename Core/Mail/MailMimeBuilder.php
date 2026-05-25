<?php
namespace Core\Mail;

/**
 * Builds MIME multipart bodies for {@see PhpNativeMailer}.
 */
final class MailMimeBuilder
{
    private string $charset;

    public function __construct(string $charset = 'UTF-8')
    {
        $this->charset = $charset;
    }

    /**
     * @return array{headers: string[], body: string}
     */
    public function build(MailMessage $message): array
    {
        $text = $message->getTextBody();
        $html = $message->getHtmlBody();
        $attachments = $message->getAttachments();

        if ($attachments === []) {
            if ($text !== null && $html !== null) {
                return $this->buildMultipartAlternative($text, $html);
            }
            if ($html !== null) {
                return $this->buildSinglePart('text/html', $html);
            }
            return $this->buildSinglePart('text/plain', (string)$text);
        }

        $mixedBoundary = $this->boundary();
        $parts = [];

        if ($text !== null && $html !== null) {
            $alt = $this->buildMultipartAlternative($text, $html);
            $parts[] = $this->part(
                $mixedBoundary,
                'multipart/alternative; boundary="' . $this->extractBoundary($alt['headers']) . '"',
                $alt['body'],
            );
        } elseif ($html !== null) {
            $parts[] = $this->part($mixedBoundary, 'text/html; charset="' . $this->charset . '"', $html, 'quoted-printable');
        } else {
            $parts[] = $this->part($mixedBoundary, 'text/plain; charset="' . $this->charset . '"', (string)$text, 'quoted-printable');
        }

        foreach ($attachments as $attachment) {
            $headers = [
                'Content-Type: ' . $attachment->resolvedMimeType() . '; name="' . $this->encodeFilename($attachment->filename) . '"',
                'Content-Transfer-Encoding: base64',
                'Content-Disposition: ' . $attachment->disposition . '; filename="' . $this->encodeFilename($attachment->filename) . '"',
            ];
            if ($attachment->contentId !== null && $attachment->contentId !== '') {
                $headers[] = 'Content-ID: <' . $attachment->contentId . '>';
            }
            $parts[] = '--' . $mixedBoundary . "\r\n"
                . implode("\r\n", $headers) . "\r\n\r\n"
                . chunk_split(base64_encode($attachment->content), 76, "\r\n");
        }

        $body = implode("\r\n", $parts) . '--' . $mixedBoundary . "--\r\n";
        return [
            'headers' => ['Content-Type: multipart/mixed; boundary="' . $mixedBoundary . '"'],
            'body'    => $body,
        ];
    }

    /**
     * @return array{headers: string[], body: string}
     */
    private function buildMultipartAlternative(string $text, string $html): array
    {
        $boundary = $this->boundary();
        $body = $this->part($boundary, 'text/plain; charset="' . $this->charset . '"', $text, 'quoted-printable')
            . $this->part($boundary, 'text/html; charset="' . $this->charset . '"', $html, 'quoted-printable')
            . '--' . $boundary . "--\r\n";

        return [
            'headers' => ['Content-Type: multipart/alternative; boundary="' . $boundary . '"'],
            'body'    => $body,
        ];
    }

    /**
     * @return array{headers: string[], body: string}
     */
    private function buildSinglePart(string $mimeType, string $content): array
    {
        return [
            'headers' => [
                'Content-Type: ' . $mimeType . '; charset="' . $this->charset . '"',
                'Content-Transfer-Encoding: quoted-printable',
            ],
            'body' => quoted_printable_encode($content),
        ];
    }

    private function part(string $boundary, string $contentType, string $content, ?string $encoding = null): string
    {
        $headers = ['Content-Type: ' . $contentType];
        if ($encoding === 'quoted-printable') {
            $headers[] = 'Content-Transfer-Encoding: quoted-printable';
            $content = quoted_printable_encode($content);
        }
        return '--' . $boundary . "\r\n"
            . implode("\r\n", $headers) . "\r\n\r\n"
            . $content . "\r\n";
    }

    private function boundary(): string
    {
        return 'CoreMail_' . bin2hex(random_bytes(12));
    }

    /**
     * @param string[] $headers
     */
    private function extractBoundary(array $headers): string
    {
        foreach ($headers as $header) {
            if (preg_match('/boundary="([^"]+)"/', $header, $matches) === 1) {
                return $matches[1];
            }
        }
        throw new \RuntimeException('MIME boundary not found.');
    }

    private function encodeFilename(string $filename): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $filename);
    }
}
