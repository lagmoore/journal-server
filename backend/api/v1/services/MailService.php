<?php
// backend/api/v1/services/MailService.php
namespace Vyper\Api\V1\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Vyper\Config\SMTP;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class MailService
{
    /**
     * @var PHPMailer
     */
    private $mailer;
    
    /**
     * @var Logger
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        // Set up logger
        $this->logger = new Logger('mail');
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/../../../logs/mail.log', Logger::DEBUG));
        
        // Set up mailer
        $this->mailer = new PHPMailer(true);
        
        // Get SMTP config
        $config = SMTP::getConfig();
        
        try {
            // Server settings
            $this->mailer->SMTPDebug = $config['debug'];
            $this->mailer->isSMTP();
            $this->mailer->Host = $config['host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $config['username'];
            $this->mailer->Password = $config['password'];
            $this->mailer->SMTPSecure = $config['encryption'];
            $this->mailer->Port = $config['port'];
            
            // Set default sender
            $this->mailer->setFrom($config['from_address'], $config['from_name']);
            $this->mailer->addReplyTo($config['reply_to'], $config['from_name']);
            
            // Set character encoding
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';
            
            // Use HTML by default
            $this->mailer->isHTML(true);
        } catch (Exception $e) {
            $this->logger->error('Mailer setup error: ' . $e->getMessage());
        }
    }
    
    /**
     * Send email
     *
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $htmlBody HTML body content
     * @param string|null $textBody Plain text body (optional)
     * @param array $attachments Array of attachments (optional)
     * @return bool Success status
     */
    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null, array $attachments = []): bool
    {
        try {
            // Reset all recipients and attachments
            $this->mailer->clearAllRecipients();
            $this->mailer->clearAttachments();
            
            // Add recipient
            $this->mailer->addAddress($to);
            
            // Set subject
            $this->mailer->Subject = $subject;
            
            // Set body
            $this->mailer->Body = $htmlBody;
            
            // Set plain text alternative if provided
            if ($textBody) {
                $this->mailer->AltBody = $textBody;
            } else {
                // Generate plain text from HTML if not provided
                $this->mailer->AltBody = strip_tags($htmlBody);
            }
            
            // Add attachments if any
            foreach ($attachments as $attachment) {
                if (isset($attachment['path'])) {
                    $name = $attachment['name'] ?? basename($attachment['path']);
                    $this->mailer->addAttachment($attachment['path'], $name);
                }
            }
            
            // Send email
            $this->mailer->send();
            
            $this->logger->info("Email sent successfully to: $to");
            return true;
        } catch (Exception $e) {
            $this->logger->error("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send password reset email
     *
     * @param string $to Recipient email
     * @param string $resetToken Reset token
     * @param string $username Username
     * @return bool Success status
     */
    public function sendPasswordResetEmail(string $to, string $resetToken, string $username): bool
    {
        $subject = 'Återställ ditt lösenord | Vyper Journal';
        
        // Get app URL from environment
        $appUrl = $_ENV['APP_URL'] ?? 'http://localhost:3000';
        
        // Create reset URL
        $resetUrl = "$appUrl/reset-password?email=" . urlencode($to) . "&token=" . urlencode($resetToken);
        
        // HTML email body
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Återställ ditt lösenord</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
                .container { padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                .header { background-color: #1f5b8e; color: white; padding: 10px; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; }
                .button { display: inline-block; background-color: #1f5b8e; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; margin: 20px 0; }
                .footer { font-size: 12px; color: #777; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Återställ ditt lösenord</h1>
                </div>
                <div class='content'>
                    <p>Hej $username,</p>
                    <p>Vi har tagit emot en begäran om att återställa lösenordet för ditt konto på Vyper Journal.</p>
                    <p>Klicka på knappen nedan för att återställa ditt lösenord:</p>
                    <p><a href='$resetUrl' class='button'>Återställ lösenord</a></p>
                    <p>Om du inte begärde att återställa ditt lösenord, kan du ignorera detta e-postmeddelande eller kontakta support.</p>
                    <p>Denna länk är giltig i 1 timme och kan endast användas en gång.</p>
                    <p>Om knappen ovan inte fungerar, kopiera och klistra in följande URL i din webbläsares adressfält:</p>
                    <p>$resetUrl</p>
                    <div class='footer'>
                        <p>Detta är ett automatiskt utskickat e-postmeddelande, vänligen svara inte på detta.</p>
                        <p>&copy; " . date('Y') . " Vyper Journal. Alla rättigheter förbehållna.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Plain text version
        $textBody = "
        Återställ ditt lösenord | Vyper Journal
        
        Hej $username,
        
        Vi har tagit emot en begäran om att återställa lösenordet för ditt konto på Vyper Journal.
        
        För att återställa ditt lösenord, gå till följande adress:
        $resetUrl
        
        Om du inte begärde att återställa ditt lösenord, kan du ignorera detta e-postmeddelande eller kontakta support.
        
        Denna länk är giltig i 1 timme och kan endast användas en gång.
        
        Detta är ett automatiskt utskickat e-postmeddelande, vänligen svara inte på detta.
        
        © " . date('Y') . " Vyper Journal. Alla rättigheter förbehållna.
        ";
        
        return $this->send($to, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Send account created email
     *
     * @param string $to Recipient email
     * @param string $username Username
     * @param string $password Initial password
     * @return bool Success status
     */
    public function sendAccountCreatedEmail(string $to, string $username, string $password): bool
    {
        $subject = 'Välkommen till Vyper Journal';
        
        // Get app URL from environment
        $appUrl = $_ENV['APP_URL'] ?? 'http://localhost:3000';
        
        // Create login URL
        $loginUrl = "$appUrl/login";
        
        // HTML email body
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Välkommen till Vyper Journal</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
                .container { padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                .header { background-color: #1f5b8e; color: white; padding: 10px; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; }
                .credentials { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .button { display: inline-block; background-color: #1f5b8e; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; margin: 20px 0; }
                .footer { font-size: 12px; color: #777; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Välkommen till Vyper Journal</h1>
                </div>
                <div class='content'>
                    <p>Hej $username,</p>
                    <p>Ett konto har skapats för dig på Vyper Journal. Nedan hittar du dina inloggningsuppgifter:</p>
                    <div class='credentials'>
                        <p><strong>Användarnamn:</strong> $username</p>
                        <p><strong>Lösenord:</strong> $password</p>
                    </div>
                    <p>Av säkerhetsskäl rekommenderar vi att du ändrar ditt lösenord efter första inloggningen.</p>
                    <p><a href='$loginUrl' class='button'>Logga in nu</a></p>
                    <div class='footer'>
                        <p>Detta är ett automatiskt utskickat e-postmeddelande, vänligen svara inte på detta.</p>
                        <p>&copy; " . date('Y') . " Vyper Journal. Alla rättigheter förbehållna.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Plain text version
        $textBody = "
        Välkommen till Vyper Journal
        
        Hej $username,
        
        Ett konto har skapats för dig på Vyper Journal. Nedan hittar du dina inloggningsuppgifter:
        
        Användarnamn: $username
        Lösenord: $password
        
        Av säkerhetsskäl rekommenderar vi att du ändrar ditt lösenord efter första inloggningen.
        
        För att logga in, gå till följande adress:
        $loginUrl
        
        Detta är ett automatiskt utskickat e-postmeddelande, vänligen svara inte på detta.
        
        © " . date('Y') . " Vyper Journal. Alla rättigheter förbehållna.
        ";
        
        return $this->send($to, $subject, $htmlBody, $textBody);
    }
}