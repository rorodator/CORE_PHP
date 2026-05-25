<?php
namespace Core\Mail;

/**
 * Contract for outbound email delivery.
 *
 * Applications may register a concrete implementation via [services] mailer in INI,
 * or use {@see Mailer} which selects a driver from the [mail] section.
 * Third-party providers (SendGrid, SES, Mailgun, Postmark, …) should implement
 * this interface in the app layer without adding vendor deps to CORE_PHP.
 */
interface MailerInterface
{
    /**
     * Whether the driver has enough configuration to attempt delivery.
     */
    public function isConfigured(): bool;

    /**
     * Send a fully built message.
     *
     * @param MailMessage $message
     * @return MailSendResult Outcome with optional provider message id or error detail.
     */
    public function send(MailMessage $message): MailSendResult;
}
