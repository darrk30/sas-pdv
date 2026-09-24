<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CodigoVerificacionRegistro extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $nombre,
        public readonly string $codigo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu código de verificación — ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.codigo-verificacion-registro',
        );
    }
}
