<?php
namespace Core\Mail;

/**
 * Development-safe mailer: validates and logs messages without network I/O.
 *
 * Default driver when [mail] driver is "null" or unset.
 */
class NullMailer extends AbstractMailer
{
    public function isConfigured(): bool
    {
        return true;
    }

    public function send(MailMessage $message): MailSendResult
    {
        try {
            $message->validate();
            $from = $this->resolveFrom($message);
        } catch (\InvalidArgumentException $e) {
            return MailSendResult::fail($e->getMessage());
        }

        $messageId = $message->getMessageId() ?? $this->generateMessageId();
        $recipients = $this->formatAddressList(array_merge(
            $message->getTo(),
            $message->getCc(),
            $message->getBcc(),
        ));

        $parts = [
            sprintf('[NullMailer] subject=%s', $message->getSubject()),
            sprintf('from=%s', $from->format()),
            sprintf('to=%s', $recipients),
        ];
        if ($message->getHtmlBody() !== null) {
            $parts[] = 'body=html';
        }
        if ($message->getTextBody() !== null) {
            $parts[] = 'body=text';
        }
        if ($message->getAttachments() !== []) {
            $parts[] = 'attachments=' . count($message->getAttachments());
        }

        $this->logDelivery('INFO', implode(' | ', $parts));
        return MailSendResult::ok($messageId);
    }
}
