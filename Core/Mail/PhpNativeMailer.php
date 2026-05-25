<?php
namespace Core\Mail;

/**
 * Sends email via PHP mail() with MIME multipart support.
 *
 * Suitable for simple deployments with a local MTA (sendmail/postfix).
 * For production at scale, prefer a third-party provider implementing {@see MailerInterface}.
 */
class PhpNativeMailer extends AbstractMailer
{
    public function isConfigured(): bool
    {
        $from = trim((string)($this->config['from_address'] ?? ''));
        return $from !== '' || function_exists('mail');
    }

    public function send(MailMessage $message): MailSendResult
    {
        try {
            $message->validate();
            $from = $this->resolveFrom($message);
        } catch (\InvalidArgumentException $e) {
            return MailSendResult::fail($e->getMessage());
        }

        if (!function_exists('mail')) {
            return MailSendResult::fail('PHP mail() is not available.');
        }

        $mime = (new MailMimeBuilder($message->getCharset()))->build($message);
        $messageId = $message->getMessageId() ?? $this->generateMessageId();

        $headers = array_merge(
            [
                'MIME-Version: 1.0',
                'From: ' . $from->format(),
                'Message-ID: ' . $messageId,
                'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
                'X-Mailer: CORE_PHP',
            ],
            $mime['headers'],
        );

        $replyTo = $this->resolveReplyTo($message);
        if ($replyTo !== null) {
            $headers[] = 'Reply-To: ' . $replyTo->format();
        }
        if ($message->getCc() !== []) {
            $headers[] = 'Cc: ' . $this->formatAddressList($message->getCc());
        }
        if ($message->getBcc() !== []) {
            $headers[] = 'Bcc: ' . $this->formatAddressList($message->getBcc());
        }
        foreach ($message->getHeaders() as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }

        $toHeader = $this->formatAddressList($message->getTo());
        $subject = $this->encodeSubject($message->getSubject());
        $headerString = implode("\r\n", $headers);

        $sent = @mail($toHeader, $subject, $mime['body'], $headerString);
        if (!$sent) {
            $this->logDelivery('ERROR', '[PhpNativeMailer] mail() returned false for subject=' . $message->getSubject());
            return MailSendResult::fail('mail() delivery failed.');
        }

        $this->logDelivery('DEBUG', '[PhpNativeMailer] sent subject=' . $message->getSubject() . ' id=' . $messageId);
        return MailSendResult::ok($messageId);
    }

    private function encodeSubject(string $subject): string
    {
        if (preg_match('/[^\x20-\x7E]/', $subject) === 1) {
            return '=?UTF-8?B?' . base64_encode($subject) . '?=';
        }
        return $subject;
    }
}