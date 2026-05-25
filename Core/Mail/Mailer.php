<?php
namespace Core\Mail;

/**
 * Configurable mailer facade registered as core()->mailer.
 *
 * Driver selection via [mail] driver in INI:
 * - null   → {@see NullMailer} (default, safe for dev)
 * - php    → {@see PhpNativeMailer} (PHP mail() + MIME)
 *
 * Apps may override [services] mailer with any {@see MailerInterface} implementation
 * (e.g. SendGrid, SES, Mailgun) without changing call sites.
 */
class Mailer implements MailerInterface
{
    private MailerInterface $driver;

    public function __construct()
    {
        $this->driver = self::createDriver(self::resolveDriverName());
    }

    public function isConfigured(): bool
    {
        return $this->driver->isConfigured();
    }

    public function send(MailMessage $message): MailSendResult
    {
        return $this->driver->send($message);
    }

    /**
     * Instantiate a driver by name (used by the facade and tests).
     */
    public static function createDriver(string $driver): MailerInterface
    {
        return match (strtolower(trim($driver))) {
            'php', 'native', 'mail' => new PhpNativeMailer(),
            default => new NullMailer(),
        };
    }

    private static function resolveDriverName(): string
    {
        if (!function_exists('core')) {
            return 'null';
        }
        return (string)core()->getConfigValue('mail', 'driver', 'null');
    }
}
