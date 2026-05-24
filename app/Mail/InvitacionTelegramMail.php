<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitacionTelegramMail extends Mailable
{
    use Queueable, SerializesModels;

    public $tutor;
    public $enlaceMagico;

    /**
     * Create a new message instance.
     */
    public function __construct($tutor, $enlaceMagico)
    {
        $this->tutor = $tutor;
        $this->enlaceMagico = $enlaceMagico;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Vincular Telegram - Notificaciones CAM #48',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.invitacion_telegram',
            with: [
                'tutor' => $this->tutor,
                'enlaceMagico' => $this->enlaceMagico
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
