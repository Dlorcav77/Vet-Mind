<?php
// /funciones/EmailService.php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private PHPMailer $mail;
    private array $configEmail;


    public function __construct()
    {
        require __DIR__ . '/../configP.php';
        $this->configEmail = $configEmail;

        $this->mail = new PHPMailer(true);

        $this->mail->isSMTP();
        $this->mail->Host       = $configEmail['host'];
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = $configEmail['username'];
        $this->mail->Password   = $configEmail['password'];
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = $configEmail['port'];

        $this->mail->CharSet = 'UTF-8';
        $this->mail->setFrom($configEmail['from_email'], $configEmail['from_name']);
    }

    public function overrideFrom(?string $name = null): void
    {
        if ($name) {
            $this->mail->setFrom($this->configEmail['from_email'], $name);
        }
    }

    /**
     * $to puede ser string o array
     * $attachments puede ser array de rutas absolutas
     */
    public function send(string|array $to, string $subject, string $body, array $attachments = []): array
    {
        try {
            // limpiar por si se reutiliza
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();

            // destinatarios
            if (is_array($to)) {
                foreach ($to as $addr) {
                    $addr = trim($addr);
                    if ($addr !== '') {
                        $this->mail->addAddress($addr);
                    }
                }
            } else {
                $this->mail->addAddress($to);
            }

            // adjuntos
            foreach ($attachments as $file) {
                if ($file && file_exists($file)) {
                    $this->mail->addAttachment($file);
                }
            }

            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            $this->mail->send();

            return ['status' => 'success', 'message' => 'Correo enviado'];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al enviar el correo: ' . $this->mail->ErrorInfo
            ];
        }
    }
}
