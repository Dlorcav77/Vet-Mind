<?php
require_once __DIR__ . '/funciones/emailService.php';

$mailer = new EmailService();

echo $mailer->sendEmail(
    'diego.lorcaveliz@gmail.com',
    'Prueba desde PHP + Brevo',
    '<p>Hola, esto salió de la app PHP 👍</p>'
);
