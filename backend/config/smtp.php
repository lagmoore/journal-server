<?php
// backend/config/smtp.php
namespace Vyper\Config;

class SMTP
{
    /**
     * Get SMTP configuration
     *
     * @return array SMTP configuration
     */
    public static function getConfig(): array
    {
        return [
            'host' => $_ENV['MAIL_HOST'] ?? 'smtp.mailtrap.io',
            'port' => $_ENV['MAIL_PORT'] ?? 2525,
            'username' => $_ENV['MAIL_USERNAME'] ?? '',
            'password' => $_ENV['MAIL_PASSWORD'] ?? '',
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
            'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@vyperjournal.com',
            'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Vyper Journal',
            'reply_to' => $_ENV['MAIL_REPLY_TO'] ?? 'noreply@vyperjournal.com',
            'debug' => $_ENV['MAIL_DEBUG'] ?? 0, // 0 = off, 1 = client, 2 = client and server
        ];
    }
}