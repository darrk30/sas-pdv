<?php

namespace App\Mail;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ListaDeseosRecordatorio extends Mailable
{
    public function __construct(
        public readonly string     $clienteNombre,
        public readonly Collection $items,
        public readonly Empresa    $empresa,
        public readonly ?string    $mensaje = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from:    new Address(config('mail.from.address'), $this->empresa->name),
            replyTo: $this->empresa->email
                ? [new Address($this->empresa->email, $this->empresa->name)]
                : [],
            subject: "¡{$this->empresa->name} tiene lo que guardaste! 💛",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.lista-deseos-recordatorio',
        );
    }
}
