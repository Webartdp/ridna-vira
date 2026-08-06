<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ContactFormMessage extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @param array<string, mixed> $formData */
    public function __construct(public array $formData)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [
                new Address(
                    (string) $this->formData['email'],
                    (string) $this->formData['name'],
                ),
            ],
            subject: 'Звернення з сайту: ' . (string) $this->formData['topic_label'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-form',
        );
    }

    /** @return array<int, mixed> */
    public function attachments(): array
    {
        return [];
    }
}
