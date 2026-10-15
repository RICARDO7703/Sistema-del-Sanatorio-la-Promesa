<?php

namespace App\Mail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

class ResendHttpTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $key = config('services.resend.key');
        if (! is_string($key) || $key === '') {
            throw new TransportException('Falta configurar la clave del servicio de correo.');
        }
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        if ($email->getAttachments() !== [] || $email->getCc() !== [] || $email->getBcc() !== []) {
            throw new TransportException('Este transporte admite mensajes de recuperación sin adjuntos ni copias.');
        }
        $payload = array_filter([
            'from' => $message->getEnvelope()->getSender()->toString(),
            'to' => array_map(fn (Address $address): string => $address->getAddress(), $message->getEnvelope()->getRecipients()),
            'subject' => $email->getSubject(),
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
            'reply_to' => array_map(fn (Address $address): string => $address->getAddress(), $email->getReplyTo()),
        ], fn ($value): bool => $value !== null && $value !== []);
        try {
            $response = Http::withToken($key)->acceptJson()->connectTimeout(5)->timeout(15)
                ->withOptions(['allow_redirects' => false])->post('https://api.resend.com/emails', $payload);
            if (! $response->successful() || ! is_string($response->json('id'))) {
                throw new TransportException('El servicio de correo no aceptó el mensaje.');
            }
        } catch (\Throwable $error) {
            // Evitar incluir contenido del correo, códigos o credenciales en excepciones.
            throw new TransportException('No se pudo completar el envío del correo.');
        }
    }

    public function __toString(): string
    {
        return 'resend_http';
    }
}
